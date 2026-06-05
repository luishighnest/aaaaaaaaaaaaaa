<?php
/**
 * toggle_favorite.php
 * Endpoint backend AJAX per aggiungere/rimuovere un canale dai preferiti del profilo attivo.
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

// 1. Verifica che l'utente sia loggato
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

$username = $_SESSION['username'] ?? '';
$active_profile = $_SESSION['active_profile'] ?? null;

if (!$username || !$active_profile) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Profilo attivo non configurato']);
    exit;
}

// 2. Controllo scadenza abbonamento (per bloccare operazioni se scaduto)
$config_file = __DIR__ . '/users_config.php';
$config = file_exists($config_file) ? require $config_file : [];
$subscription_expiry = $config['subscription_expiry'] ?? '2027-12-31';
if (time() > strtotime($subscription_expiry . ' 23:59:59')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Abbonamento scaduto']);
    exit;
}

// 3. Leggi il payload JSON inviato dal client
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['channel_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID canale mancante']);
    exit;
}

$channel_id = (int)$data['channel_id'];

// 4. Protezione anti-CSRF rigorosa
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Richiesta non valida (CSRF fallito)']);
    exit;
}

// 5. Carica i profili salvati in user_profiles.json
$profiles_file = __DIR__ . '/user_profiles.json';
$all_profiles = [];

if (file_exists($profiles_file)) {
    $raw = file_get_contents($profiles_file);
    $all_profiles = json_decode($raw, true) ?? [];
}

// Se non ci sono profili personalizzati salvati, inizializziamo caricandoli da users_config.php
if (!isset($all_profiles[$username]) || !is_array($all_profiles[$username])) {
    $all_profiles[$username] = $config['users'][$username]['profiles'] ?? [];
}

$active_id = $active_profile['id'];
$updated_favorites = [];
$profile_found = false;

// Cerca il profilo attivo e fai il toggle del canale preferito
foreach ($all_profiles[$username] as &$profile) {
    if ($profile['id'] === $active_id) {
        if (!isset($profile['favorites']) || !is_array($profile['favorites'])) {
            $profile['favorites'] = [];
        }
        
        $idx = array_search($channel_id, $profile['favorites']);
        if ($idx !== false) {
            // Se c'è già, lo rimuove (unfavorite)
            array_splice($profile['favorites'], $idx, 1);
        } else {
            // Se non c'è, lo aggiunge (favorite)
            $profile['favorites'][] = $channel_id;
        }
        
        // Ordina gli ID numericamente per pulizia
        sort($profile['favorites'], SORT_NUMERIC);
        
        $updated_favorites = $profile['favorites'];
        
        // Sincronizza la sessione attiva
        $_SESSION['active_profile'] = $profile;
        $profile_found = true;
        break;
    }
}
unset($profile);

if (!$profile_found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Profilo non trovato nel database']);
    exit;
}

// 6. Salva su file JSON
if (file_put_contents($profiles_file, json_encode($all_profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode([
        'success' => true,
        'favorites' => $updated_favorites
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Impossibile persistere i dati su server']);
}
