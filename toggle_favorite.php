<?php
/**
 * toggle_favorite.php
 * Gestisce l'aggiunta e rimozione dei canali preferiti del profilo attivo sul database PostgreSQL.
 */
session_start();
require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/db_helper.php';

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

// Recupera profilo dal database
$profile = get_user_profile($pdo, $profile_id);
if (!$profile) {
    echo json_encode(['success' => false, 'error' => 'Profilo non trovato']);
    exit;
}

// Decodifica JSON
$favorites = json_decode($profile['favorites'], true) ?? [];

$fav_index = array_search($channel_id, $favorites);
if ($fav_index !== false) {
    array_splice($favorites, $fav_index, 1);
} else {
    $favorites[] = $channel_id;
}
$favorites = array_values($favorites);

// Aggiorna database
$profile['favorites'] = $favorites; // Invia come array, il helper lo codifica
update_user_profile($pdo, $profile_id, $profile);

// Aggiorna sessione
$_SESSION['active_profile'] = $profile;

echo json_encode(['success' => true, 'favorites' => $favorites]);
