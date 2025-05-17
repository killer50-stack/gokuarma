<?php
require_once 'config.php';

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Inicializar o banco de dados
$db = initDatabase();

try {
    // Parâmetros opcionais de filtro
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    
    // Construir a consulta SQL com base no filtro
    $query = 'SELECT id, name, type, size, path, upload_date FROM files';
    
    // Aplicar filtro se não for "all"
    if ($filter !== 'all') {
        $query .= ' WHERE type = :filter';
    }
    
    // Ordenar por data de upload (mais recente primeiro)
    $query .= ' ORDER BY upload_date DESC';
    
    // Preparar e executar a consulta
    $stmt = $db->prepare($query);
    
    if ($filter !== 'all') {
        $stmt->bindValue(':filter', $filter, SQLITE3_TEXT);
    }
    
    $result = $stmt->execute();
    
    // Recuperar todos os arquivos
    $files = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $files[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'size' => (int)$row['size'],
            'path' => 'uploads/' . $row['path'],
            'date' => $row['upload_date']
        ];
    }
    
    // Obter informações de armazenamento
    $storage = getStorageUsage($db);
    
    // Retornar os dados
    $response = [
        'success' => true,
        'files' => $files,
        'storage' => [
            'used' => $storage['used_bytes'],
            'total' => MAX_STORAGE_BYTES,
            'percent' => ($storage['used_bytes'] / MAX_STORAGE_BYTES) * 100
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => $e->getMessage()]);
}

// Função para obter o uso de armazenamento atual
function getStorageUsage($db) {
    $result = $db->query('SELECT used_bytes FROM storage_usage WHERE id = 1');
    $row = $result->fetchArray();
    
    return [
        'used_bytes' => $row['used_bytes']
    ];
}
?> 