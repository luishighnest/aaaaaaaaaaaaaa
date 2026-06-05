<?php
/**
 * save_profiles.php
 * Riceve i nuovi profili via AJAX e li salva in user_profiles.json
 */
session_start();

header('Content-Type: application/json');

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

if (!isset($data['profiles']) || !is_array($data['profiles'])) {
    echo json_encode(['success' => false, 'error' => 'Dati non validi']);
    exit;
}

$new_profiles = $data['profiles'];

// Controllo base per evitare che un utente resti senza profili
if (count($new_profiles) === 0) {
    echo json_encode(['success' => false, 'error' => 'Devi avere almeno un profilo.']);
    exit;
}

// Assicuriamoci che ogni profilo abbia i campi necessari
foreach ($new_profiles as &$profile) {
    if (empty($profile['id'])) {
        $profile['id'] = substr($username, 0, 3) . '_' . uniqid();
    }
    if (empty($profile['name'])) {
        $profile['name'] = 'Nuovo Profilo';
    }
    if (empty($profile['avatar'])) {
        $profile['avatar'] = 'ph-user-circle';
    }
    if (empty($profile['color'])) {
        $profile['color'] = '#00f2fe';
    }
}
unset($profile);

$profiles_file = __DIR__ . '/user_profiles.json';
$all_profiles = [];

if (file_exists($profiles_file)) {
    $raw = file_get_contents($profiles_file);
    $all_profiles = json_decode($raw, true) ?? [];
}

$all_profiles[$username] = $new_profiles;

if (file_put_contents($profiles_file, json_encode($all_profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    
    // Aggiorniamo il profilo attivo in sessione se il nome/colore/avatar è cambiato
    if (isset($_SESSION['active_profile'])) {
        $active_id = $_SESSION['active_profile']['id'];
        $found = false;
        foreach ($new_profiles as $p) {
            if ($p['id'] === $active_id) {
                $_SESSION['active_profile'] = $p;
                $found = true;
                break;
            }
        }
        // Se il profilo attivo è stato eliminato, resettiamo e tornerà a select_profile.php al ricaricamento
        if (!$found) {
            unset($_SESSION['active_profile']);
        }
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Impossibile salvare il file.']);
}
