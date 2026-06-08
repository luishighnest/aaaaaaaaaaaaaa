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

$profiles_file = __DIR__ . '/user_profiles.json';
$all_profiles = [];

if (file_exists($profiles_file)) {
    $raw = file_get_contents($profiles_file);
    $all_profiles = json_decode($raw, true) ?? [];
}

// Assicuriamoci che ogni profilo abbia i campi necessari e preserviamo i dati esistenti
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

    // Cerca se il profilo esisteva già nel database per preservare i suoi dati (preferiti, cronologia, ecc.)
    $existing_profile = null;
    if (isset($all_profiles[$username]) && is_array($all_profiles[$username])) {
        foreach ($all_profiles[$username] as $existing) {
            if ($existing['id'] === $profile['id']) {
                $existing_profile = $existing;
                break;
            }
        }
    }

    if ($existing_profile) {
        foreach (['favorites', 'vod_favorites', 'watch_history'] as $key) {
            if (isset($existing_profile[$key]) && !empty($existing_profile[$key])) {
                if (!isset($profile[$key]) || empty($profile[$key])) {
                    $profile[$key] = $existing_profile[$key];
                }
            }
        }
    }
}
unset($profile);

$all_profiles[$username] = $new_profiles;

// 4. Scrittura su Supabase (API)
$supabase_url = getenv('SUPABASE_URL') . '/rest/v1/user_profiles';
$supabase_key = getenv('SUPABASE_KEY');

if ($supabase_url && $supabase_key) {
    // 1. Cancelliamo i vecchi profili per questo username
    $delete_url = getenv('SUPABASE_URL') . '/rest/v1/user_profiles?username=eq.' . urlencode($username);
    $ch_del = curl_init($delete_url);
    curl_setopt($ch_del, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch_del, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabase_key,
        'Authorization: Bearer ' . $supabase_key
    ]);
    curl_setopt($ch_del, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch_del);
    curl_close($ch_del);

    // 2. Prepariamo i nuovi dati
    $profiles_to_send = [];
    foreach ($new_profiles as $p) {
        $profiles_to_send[] = [
            'id' => $p['id'],
            'username' => $username,
            'name' => $p['name'],
            'avatar' => $p['avatar'],
            'color' => $p['color'],
            'allowed_categories' => $p['allowed_categories'] ?? [],
            'allowed_channels' => $p['allowed_channels'] ?? [],
            'favorites' => $p['favorites'] ?? [],
            'vod_favorites' => $p['vod_favorites'] ?? []
        ];
    }

    // 3. Inseriamo i nuovi profili
    $ch = curl_init($supabase_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($profiles_to_send));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $supabase_key,
        'Authorization: Bearer ' . $supabase_key,
        'Prefer: return=minimal'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_exec($ch);
    curl_close($ch);
}

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
