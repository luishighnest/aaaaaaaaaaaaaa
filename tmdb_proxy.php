<?php
/**
 * tmdb_proxy.php
 * Proxy lato server per le chiamate all'API TMDB.
 * Tiene la chiave API nascosta dal client.
 */

session_start();

// 1. Solo utenti autenticati
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

// 2. Recupera e valida l'endpoint richiesto
$endpoint = $_GET['endpoint'] ?? '';

if (empty($endpoint)) {
    http_response_code(400);
    echo json_encode(['error' => 'Endpoint mancante']);
    exit;
}

// 3. Whitelist: accetta solo percorsi TMDB legittimi
//    Blocca caratteri pericolosi e percorsi che non iniziano con /
if (!preg_match('#^/[a-zA-Z0-9/_\-\.]+(\?[^\s]*)?$#', $endpoint)) {
    http_response_code(400);
    echo json_encode(['error' => 'Endpoint non valido']);
    exit;
}

// 4. Sistema di Caching
$cache_dir = __DIR__ . '/cache_tmdb';
$cache_key = md5($endpoint);
$cache_file = $cache_dir . '/' . $cache_key . '.json';
$now = time();

// Determina TTL in secondi in base al tipo di endpoint
$ttl = 86400; // default: 24 ore
if (str_contains($endpoint, '/genre/')) {
    $ttl = 2592000; // 30 giorni per i generi
} elseif (str_contains($endpoint, '/movie/') || str_contains($endpoint, '/tv/')) {
    $ttl = 432000; // 5 giorni per i dettagli di film, serie, stagioni o episodi
} elseif (str_contains($endpoint, '/search/') || str_contains($endpoint, '/trending/') || str_contains($endpoint, '/discover/')) {
    $ttl = 21600; // 6 ore per le liste dinamiche
}

// Controllo validità cache
$cache_valid = false;
if (file_exists($cache_file)) {
    $mtime = filemtime($cache_file);
    if (($now - $mtime) < $ttl) {
        $cache_valid = true;
    }
}

if ($cache_valid) {
    // Cache HIT: restituisce direttamente il JSON salvato e termina
    header('Content-Type: application/json; charset=utf-8');
    // Cache browser più lunga per dettagli e generi rispetto alle liste dinamiche
    if (str_contains($endpoint, '/movie/') || str_contains($endpoint, '/tv/') || str_contains($endpoint, '/genre/')) {
        header('Cache-Control: private, max-age=86400'); // 1 giorno nel browser
    } else {
        header('Cache-Control: private, max-age=300'); // 5 minuti per liste dinamiche
    }
    echo file_get_contents($cache_file);
    exit;
}

// 5. Rate limiting per sessione (solo per chiamate non in cache): max 180 richieste ogni 60 secondi
$rate_limit   = 180;   // richieste massime
$rate_window  = 60;    // secondi della finestra

if (!isset($_SESSION['tmdb_rl_count']) || !isset($_SESSION['tmdb_rl_start'])) {
    $_SESSION['tmdb_rl_count'] = 0;
    $_SESSION['tmdb_rl_start'] = $now;
}

// Reset finestra se scaduta
if ($now - $_SESSION['tmdb_rl_start'] >= $rate_window) {
    $_SESSION['tmdb_rl_count'] = 0;
    $_SESSION['tmdb_rl_start'] = $now;
}

$_SESSION['tmdb_rl_count']++;

if ($_SESSION['tmdb_rl_count'] > $rate_limit) {
    $retry_after = $rate_window - ($now - $_SESSION['tmdb_rl_start']);
    http_response_code(429);
    header('Retry-After: ' . max(1, $retry_after));
    echo json_encode(['error' => 'Troppe richieste. Riprova tra ' . max(1, $retry_after) . ' secondi.']);
    exit;
}

// 6. Carica la chiave API dalla configurazione
$config_file = __DIR__ . '/users_config.php';
$config = file_exists($config_file) ? require $config_file : [];
$api_key = $config['tmdb_api_key'] ?? '';

if (empty($api_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Chiave API non configurata']);
    exit;
}

// 7. Costruisce l'URL finale aggiungendo chiave e lingua
$base = 'https://api.themoviedb.org/3';
$sep  = str_contains($endpoint, '?') ? '&' : '?';
$url  = $base . $endpoint . $sep . 'api_key=' . urlencode($api_key) . '&language=it-IT';

// 8. Esegue la richiesta a TMDB
$ctx = stream_context_create([
    'http' => [
        'timeout'        => 8,
        'ignore_errors'  => true,
        'method'         => 'GET',
        'header'         => "Accept: application/json\r\n",
    ],
]);

$body = @file_get_contents($url, false, $ctx);

if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Impossibile raggiungere TMDB']);
    exit;
}

// 9. Controlla lo status HTTP di TMDB
$status_line = $http_response_header[0] ?? 'HTTP/1.1 200';
preg_match('#HTTP/\d\.\d\s+(\d+)#', $status_line, $m);
$status_code = (int)($m[1] ?? 200);

if ($status_code !== 200) {
    http_response_code($status_code);
    echo $body;
    exit;
}

// 10. Salva la risposta corretta (HTTP 200) in cache
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0777, true);
}
if (is_dir($cache_dir) && is_writable($cache_dir)) {
    @file_put_contents($cache_file, $body, LOCK_EX);
}

// 11. Restituisce il JSON al client
header('Content-Type: application/json; charset=utf-8');
if (str_contains($endpoint, '/movie/') || str_contains($endpoint, '/tv/') || str_contains($endpoint, '/genre/')) {
    header('Cache-Control: private, max-age=86400');
} else {
    header('Cache-Control: private, max-age=300');
}
echo $body;
