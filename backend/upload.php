<?php
require_once 'config.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Inicializar o banco de dados
$db = initDatabase();

try {
    // Verificar se o arquivo foi enviado
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload do arquivo: ' . getFileUploadErrorMessage($_FILES['file']['error']));
    }

    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileType = determineFileType($fileName);
    
    // Verificar o tamanho do arquivo
    if ($fileSize > MAX_FILE_SIZE_BYTES) {
        throw new Exception('O arquivo excede o tamanho máximo permitido de ' . MAX_FILE_SIZE_GB . ' GB.');
    }
    
    // Verificar espaço de armazenamento disponível
    $storage = getStorageUsage($db);
    if ($storage['used_bytes'] + $fileSize > MAX_STORAGE_BYTES) {
        throw new Exception('Espaço de armazenamento insuficiente. Usado: ' . 
            formatSize($storage['used_bytes']) . ', Disponível: ' . 
            formatSize(MAX_STORAGE_BYTES - $storage['used_bytes']));
    }
    
    // Gerar nome de arquivo único para evitar sobrescrita
    $uploadFileName = generateUniqueFileName($fileName);
    $uploadPath = UPLOAD_DIR . $uploadFileName;
    
    // Criar diretório de upload se não existir
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    
    // Mover o arquivo para o diretório de upload
    if (!move_uploaded_file($fileTmp, $uploadPath)) {
        throw new Exception('Falha ao mover o arquivo para o destino.');
    }
    
    // Inserir informações do arquivo no banco de dados
    $stmt = $db->prepare('
        INSERT INTO files (name, type, size, path) 
        VALUES (:name, :type, :size, :path)
    ');
    
    $stmt->bindValue(':name', $fileName, SQLITE3_TEXT);
    $stmt->bindValue(':type', $fileType, SQLITE3_TEXT);
    $stmt->bindValue(':size', $fileSize, SQLITE3_INTEGER);
    $stmt->bindValue(':path', $uploadFileName, SQLITE3_TEXT);
    
    if (!$stmt->execute()) {
        // Se falhar ao inserir no banco, remover o arquivo
        unlink($uploadPath);
        throw new Exception('Erro ao salvar informações do arquivo no banco de dados.');
    }
    
    $fileId = $db->lastInsertRowID();
    
    // Atualizar uso de armazenamento
    updateStorageUsage($db, $storage['used_bytes'] + $fileSize);
    
    // Retornar informações do arquivo
    $response = [
        'success' => true,
        'file' => [
            'id' => $fileId,
            'name' => $fileName,
            'type' => $fileType,
            'size' => $fileSize,
            'path' => 'uploads/' . $uploadFileName,
            'date' => date('Y-m-d H:i:s')
        ],
        'storage' => [
            'used' => $storage['used_bytes'] + $fileSize,
            'total' => MAX_STORAGE_BYTES,
            'percent' => (($storage['used_bytes'] + $fileSize) / MAX_STORAGE_BYTES) * 100
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => $e->getMessage()]);
}

// Função para determinar o tipo de arquivo com base na extensão
function determineFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $videoTypes = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
    $pdfTypes = ['pdf'];
    
    if (in_array($extension, $imageTypes)) {
        return 'image';
    } elseif (in_array($extension, $videoTypes)) {
        return 'video';
    } elseif (in_array($extension, $pdfTypes)) {
        return 'pdf';
    } else {
        return 'other';
    }
}

// Função para gerar um nome de arquivo único
function generateUniqueFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $timestamp = time();
    $random = substr(md5(rand()), 0, 8);
    
    return $baseName . '_' . $timestamp . '_' . $random . '.' . $extension;
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

// Função para obter mensagem de erro de upload
function getFileUploadErrorMessage($error) {
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
            return 'O arquivo enviado excede o limite definido na diretiva upload_max_filesize do php.ini.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'O arquivo enviado excede o limite definido no formulário HTML.';
        case UPLOAD_ERR_PARTIAL:
            return 'O arquivo foi apenas parcialmente carregado.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo foi enviado.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Pasta temporária ausente.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Falha ao escrever arquivo em disco.';
        case UPLOAD_ERR_EXTENSION:
            return 'Uma extensão PHP interrompeu o upload do arquivo.';
        default:
            return 'Erro desconhecido no upload.';
    }
}

// Função para formatar o tamanho de arquivo para exibição
function formatSize($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2) . ' MB';
    } else {
        return round($bytes / 1073741824, 2) . ' GB';
    }
} 