<?php
// Configurações do aplicativo
define('MAX_STORAGE_GB', 999); // 999 GB por usuário
define('MAX_FILE_SIZE_GB', 29); // 29 GB por arquivo
define('GB_TO_BYTES', 1073741824); // 1 GB em bytes
define('MAX_STORAGE_BYTES', MAX_STORAGE_GB * GB_TO_BYTES);
define('MAX_FILE_SIZE_BYTES', MAX_FILE_SIZE_GB * GB_TO_BYTES);

// Caminho para as pastas
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('DATABASE_DIR', __DIR__ . '/../database/');

// Nome do banco de dados SQLite
define('DB_FILE', DATABASE_DIR . 'storage.sqlite');

// Configurações CORS para requisições AJAX
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Função para criar o banco de dados se não existir
function initDatabase() {
    if (!file_exists(DATABASE_DIR)) {
        mkdir(DATABASE_DIR, 0777, true);
    }
    
    $db = new SQLite3(DB_FILE);
    
    // Criar tabela de arquivos se não existir
    $db->exec('
        CREATE TABLE IF NOT EXISTS files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            size INTEGER NOT NULL,
            path TEXT NOT NULL,
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Criar tabela para armazenar o uso de armazenamento
    $db->exec('
        CREATE TABLE IF NOT EXISTS storage_usage (
            id INTEGER PRIMARY KEY,
            used_bytes INTEGER DEFAULT 0,
            last_update DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Inserir registro inicial para armazenamento se não existir
    $result = $db->query('SELECT COUNT(*) as count FROM storage_usage');
    $row = $result->fetchArray();
    
    if ($row['count'] == 0) {
        $db->exec('INSERT INTO storage_usage (id, used_bytes) VALUES (1, 0)');
    }
    
    return $db;
}
?> 