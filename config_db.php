<?php
// config_db.php

// Recupera le credenziali dalle variabili d'ambiente di Render
$db_url = getenv('SUPABASE_URL');
$db_key = getenv('SUPABASE_KEY');

// Supabase usa PostgreSQL
// NOTA: Per il DSN, utilizziamo l'host estratto dall'URL
$url_parts = parse_url($db_url);
$host = $url_parts['host'];
$dbname = 'postgres'; // Database standard su Supabase
$port = 5432;

try {
    // Connessione a PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    // NOTA: Per PostgreSQL, il "password" è la chiave API su Supabase
    $pdo = new PDO($dsn, 'postgres', $db_key, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Nota: La creazione delle tabelle tramite `CREATE TABLE IF NOT EXISTS` 
    // andrebbe fatta via migrazioni o console Supabase, non ad ogni richiesta web.
    // Il codice di creazione tabelle è stato rimosso per ottimizzare.
    
} catch (PDOException $e) {
    // In produzione non mostrare dettagli del database
    error_log("Errore connessione database: " . $e->getMessage());
    die("Errore di connessione al database.");
}
?>
