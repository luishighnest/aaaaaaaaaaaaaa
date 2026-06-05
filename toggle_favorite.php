<?php
/**
 * toggle_favorite.php
 * Gestisce l'aggiunta e rimozione dei canali preferiti del profilo attivo.
 */
session_start();

header('Content-Type: application/json');

// Verifica autorizzazione
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Protezione anti-CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Richiesta non valida (CSRF fallito)']);
    exit;
}

$username = $_SESSION['username'];
$active_profile = $_SESSION['active_profile'] ?? null;
if (!$active_profile) {
    echo json_encode(['success' => false, 'error' => 'Nessun profilo attivo selezionato']);
    exit;
}
$profile_id = $active_profile['id'];

$channel_id = isset($data['channel_id']) ? intval($data['channel_id']) : 0;
if ($channel_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID canale non valido']);
    exit;
}

$profiles_file = __DIR__ . '/user_profiles.json';
$all_profiles = [];
if (file_exists($profiles_file)) {
    $raw = file_get_contents($profiles_file);
    $all_profiles = json_decode($raw, true) ?? [];
}

// Se non ci sono profili in user_profiles.json per l'utente, li carica da users_config.php e li inizializza
if (!isset($all_profiles[$username]) || empty($all_profiles[$username])) {
    $config_file = __DIR__ . '/users_config.php';
    $config = file_exists($config_file) ? require $config_file : [];
    $profiles = $config['users'][$username]['profiles'] ?? [];
    $all_profiles[$username] = $profiles;
}

$user_profiles = &$all_profiles[$username];
$found = false;
$updated_favorites = [];

foreach ($user_profiles as &$profile) {
    if ($profile['id'] === $profile_id) {
        $found = true;
        if (!isset($profile['favorites']) || !is_array($profile['favorites'])) {
            $profile['favorites'] = [];
        }
        
        $fav_index = array_search($channel_id, $profile['favorites']);
        if ($fav_index !== false) {
            // Rimuove se già presente
            array_splice($profile['favorites'], $fav_index, 1);
        } else {
            // Aggiunge se non presente
            $profile['favorites'][] = $channel_id;
        }
        
        // Ordina o normalizza gli indici numerici dell'array
        $profile['favorites'] = array_values($profile['favorites']);
        $updated_favorites = $profile['favorites'];
        
        // Aggiorna il profilo attivo in sessione
        $_SESSION['active_profile'] = $profile;
        break;
    }
}
unset($profile);

if (!$found) {
    echo json_encode(['success' => false, 'error' => 'Profilo non trovato']);
    exit;
}

// Salva nel file user_profiles.json
if (file_put_contents($profiles_file, json_encode($all_profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true, 'favorites' => $updated_favorites]);
} else {
    echo json_encode(['success' => false, 'error' => 'Impossibile salvare i preferiti nel file']);
}
