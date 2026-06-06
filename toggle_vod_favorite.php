<?php
/**
 * toggle_vod_favorite.php
 * Gestisce l'aggiunta e la rimozione dei contenuti VOD preferiti del profilo attivo.
 */
session_start();

header('Content-Type: application/json');

// 1. Verifica autenticazione
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 2. Protezione anti-CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Richiesta non valida (CSRF fallito)']);
    exit;
}

$username = $_SESSION['username'] ?? '';
$active_profile = $_SESSION['active_profile'] ?? null;
if (!$active_profile) {
    echo json_encode(['success' => false, 'error' => 'Nessun profilo attivo selezionato']);
    exit;
}
$profile_id = $active_profile['id'];

// 3. Valutazione parametri in input
$id = isset($data['id']) ? intval($data['id']) : 0;
$type = isset($data['type']) ? trim($data['type']) : '';
$title = isset($data['title']) ? trim($data['title']) : '';
$poster_path = isset($data['poster_path']) ? trim($data['poster_path']) : '';

if ($id <= 0 || !in_array($type, ['movie', 'tv']) || empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Dati in input non validi']);
    exit;
}

$profiles_file = __DIR__ . '/user_profiles.json';
$all_profiles = [];
if (file_exists($profiles_file)) {
    $raw = file_get_contents($profiles_file);
    $all_profiles = json_decode($raw, true) ?? [];
}

// Inizializza i profili da users_config.php se non presenti in user_profiles.json
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
        
        if (!isset($profile['vod_favorites']) || !is_array($profile['vod_favorites'])) {
            $profile['vod_favorites'] = [];
        }
        
        // Cerca se l'elemento (stesso ID e tipo) è già preferito
        $found_index = -1;
        for ($i = 0; $i < count($profile['vod_favorites']); $i++) {
            $fav = $profile['vod_favorites'][$i];
            if (intval($fav['id']) === $id && $fav['type'] === $type) {
                $found_index = $i;
                break;
            }
        }
        
        if ($found_index !== -1) {
            // Se esiste già, rimuovilo
            array_splice($profile['vod_favorites'], $found_index, 1);
        } else {
            // Se non esiste, aggiungilo
            $profile['vod_favorites'][] = [
                'id' => $id,
                'type' => $type,
                'title' => $title,
                'poster_path' => $poster_path
            ];
        }
        
        $profile['vod_favorites'] = array_values($profile['vod_favorites']);
        $updated_favorites = $profile['vod_favorites'];
        
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

// 4. Scrittura su user_profiles.json
if (file_put_contents($profiles_file, json_encode($all_profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true, 'vod_favorites' => $updated_favorites]);
} else {
    echo json_encode(['success' => false, 'error' => 'Impossibile salvare i preferiti nel file']);
}
