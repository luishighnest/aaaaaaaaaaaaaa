<?php
/**
 * epg.php — Endpoint JSON per l'EPG
 * Restituisce il contenuto aggiornato di guida_tv_sky.json come JSON puro.
 * Usato dal fetch() in background di index.php e guida.php.
 */

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

$epg_file = __DIR__ . '/guida_tv_sky.json';

// Header HTTP
header('Content-Type: application/json; charset=utf-8');

// Cache: il browser può tenere la risposta per 60 secondi
header('Cache-Control: public, max-age=60');

if (!file_exists($epg_file)) {
    http_response_code(404);
    echo json_encode(['error' => 'EPG file not found']);
    exit;
}

// Legge e restituisce direttamente il JSON (già formattato dallo scraper)
$json = file_get_contents($epg_file);

// Verifica che sia JSON valido
$data = json_decode($json);
if ($data === null) {
    http_response_code(500);
    echo json_encode(['error' => 'EPG file is not valid JSON']);
    exit;
}

// Aggiunge header con timestamp ultimo aggiornamento
header('X-EPG-Updated: ' . date('H:i', filemtime($epg_file)));
header('X-EPG-Channels: ' . count($data));

echo $json;
