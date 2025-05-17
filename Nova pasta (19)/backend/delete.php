<?php
require_once 'config.php';

// Verificar se é uma requisição DELETE ou POST com parâmetro delete
$isDeleteRequest = $_SERVER['REQUEST_METHOD'] === 'DELETE' || 
                  ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'DELETE');

if (!$isDeleteRequest) {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Inicializar o banco de dados
$db = initDatabase();

try {
    // Obter ID do arquivo a ser excluído
    $fileId = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Para requisições DELETE reais, pegar dados do corpo
        $data = json_decode(file_get_contents('php://input'), true);
        $fileId = isset($data['id']) ? $data['id'] : null;
    } else {
        // Para requisições POST simulando DELETE
        $fileId = isset($_POST['id']) ? $_POST['id'] : null;
    }
    
    if (!$fileId) {
        throw new Exception('ID do arquivo não fornecido.');
    }
    
    // Iniciar transação
    $db->exec('BEGIN TRANSACTION');
    
    // Obter informações do arquivo
    $stmt = $db->prepare('SELECT path, size FROM files WHERE id = :id');
    $stmt->bindValue(':id', $fileId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $file = $result->fetchArray();
    
    if (!$file) {
        throw new Exception('Arquivo não encontrado.');
    }
    
    // Caminho completo para o arquivo
    $filePath = UPLOAD_DIR . $file['path'];
    
    // Excluir o arquivo físico se existir
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception('Falha ao excluir o arquivo físico.');
        }
    }
    
    // Excluir registro do banco de dados
    $stmt = $db->prepare('DELETE FROM files WHERE id = :id');
    $stmt->bindValue(':id', $fileId, SQLITE3_INTEGER);
    if (!$stmt->execute()) {
        throw new Exception('Falha ao excluir o registro do arquivo no banco de dados.');
    }
    
    // Atualizar uso de armazenamento
    $storage = getStorageUsage($db);
    $newUsage = max(0, $storage['used_bytes'] - $file['size']); // Evitar valores negativos
    updateStorageUsage($db, $newUsage);
    
    // Confirmar transação
    $db->exec('COMMIT');
    
    // Retornar sucesso
    $response = [
        'success' => true,
        'message' => 'Arquivo excluído com sucesso.',
        'storage' => [
            'used' => $newUsage,
            'total' => MAX_STORAGE_BYTES,
            'percent' => ($newUsage / MAX_STORAGE_BYTES) * 100
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Em caso de erro, desfazer a transação
    $db->exec('ROLLBACK');
    
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

// Função para atualizar o uso de armazenamento
function updateStorageUsage($db, $bytes) {
    $stmt = $db->prepare('UPDATE storage_usage SET used_bytes = :bytes, last_update = CURRENT_TIMESTAMP WHERE id = 1');
    $stmt->bindValue(':bytes', $bytes, SQLITE3_INTEGER);
    $stmt->execute();
}
?> 