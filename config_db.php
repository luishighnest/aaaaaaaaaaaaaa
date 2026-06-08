<?php
// config_db.php

// Recupera le credenziali dalle variabili d'ambiente di Render
$db_url = getenv('SUPABASE_URL');
$db_key = getenv('SUPABASE_KEY');

if (!$db_url || !$db_key) {
    error_log("Errore: Variabili d'ambiente mancanti (SUPABASE_URL o SUPABASE_KEY)");
    die("Errore di configurazione del database.");
}

// Supabase usa PostgreSQL
$url_parts = parse_url($db_url);
$host = $url_parts['host'] ?? '';
$dbname = 'postgres'; 
$port = 5432;

try {
    // Connessione a PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    // NOTA: Per PostgreSQL, il "password" è la chiave API su Supabase
    $pdo = new PDO($dsn, 'postgres', $db_key, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5 // Timeout di 5 secondi
    ]);
    
} catch (PDOException $e) {
    // Logga l'errore completo lato server per debugging
    error_log("Errore connessione database: " . $e->getMessage());
    // Mostra un messaggio generico all'utente
    die("Errore di connessione al database. Si prega di riprovare più tardi.");
}
?>
