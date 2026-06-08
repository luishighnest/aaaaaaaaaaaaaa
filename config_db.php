<?php
// config_db.php

$database_url = getenv('DATABASE_URL');

if (!$database_url) {
    // Per ora, se non trova la variabile su Render, usa una stringa vuota 
    // per non bloccare il sito. In futuro dovremo gestire meglio questo caso.
    return; 
}

try {
    $url_parts = parse_url($database_url);
    
    $host = $url_parts['host'];
    $port = $url_parts['port'] ?? 5432;
    $dbname = ltrim($url_parts['path'], '/');
    $user = $url_parts['user'];
    $password = $url_parts['pass'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
} catch (PDOException $e) {
    // Silenziamo l'errore per ora, per evitare che il sito crashi 
    // se il DB non è raggiungibile durante i test.
}
?>
