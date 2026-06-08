<?php
// config_db.php

// SQLite salva tutto in un file
$db_file = __DIR__ . '/database.sqlite';

try {
    $dsn = "sqlite:" . $db_file;
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Crea la tabella se non esiste
    $pdo->exec("CREATE TABLE IF NOT EXISTS watch_progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50),
        profile_id VARCHAR(50),
        content_id INTEGER,
        content_type VARCHAR(20),
        progress INTEGER,
        seconds INTEGER,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
} catch (PDOException $e) {
    die("Errore SQLite: " . $e->getMessage());
}
?>
