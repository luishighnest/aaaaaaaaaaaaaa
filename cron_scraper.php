<?php
/**
 * cron_scraper.php — Endpoint per avviare lo scraper tramite Cron Job esterno.
 * Esempio d'uso: http://tuodominio.it/cron_scraper.php?key=pz8_secret_key
 */

// Sostituisci con una chiave segreta personalizzata
define('CRON_SECRET_KEY', 'pz8_secret_key');

$is_cli = (php_sapi_name() === 'cli');
$key = $_GET['key'] ?? '';

if (!$is_cli && $key !== CRON_SECRET_KEY) {
    http_response_code(403);
    die("Accesso negato: chiave non valida.");
}


// Rimuove limiti di tempo di esecuzione e aumenta la memoria disponibile
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

// Mostra gli errori a schermo in caso di debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Avvio dello scraper in corso...<br>";
flush();

require_once __DIR__ . '/scraper_sky.php';

$start = microtime(true);
$scraper = new SkyScraper();
$success = $scraper->run();
$elapsed = round(microtime(true) - $start, 2);

if ($success) {
    echo "Scraping completato con successo in {$elapsed} secondi!<br>";
} else {
    echo "Errore durante lo scraping.<br>";
}
