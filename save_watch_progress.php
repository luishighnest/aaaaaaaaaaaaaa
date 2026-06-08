<?php
/**
 * save_watch_progress.php
 * Salva lo stato di riproduzione dei contenuti VOD per il profilo attivo.
 */
session_start();
require_once __DIR__ . '/config_db.php';

header('Content-Type: application/json');

// Helper per loggare eventi sul server
function log_backend_debug($message) {
    $log_file = __DIR__ . '/progress_debug.log';
    $log_entry = sprintf(
        "[%s] [SERVER] %s | Session: %s\n",
        date('Y-m-d H:i:s'),
        $message,
        json_encode([
            'logged_in' => $_SESSION['logged_in'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'active_profile_id' => $_SESSION['active_profile']['id'] ?? null,
            'csrf_token' => $_SESSION['csrf_token'] ?? null
        ])
    );
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// 1. Verifica autenticazione
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    log_backend_debug("Autenticazione fallita: utente non loggato in sessione.");
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

log_backend_debug("Richiesta ricevuta. Input raw: " . $input);

// 2. Protezione anti-CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    log_backend_debug("CSRF fallito. Atteso: " . ($_SESSION['csrf_token'] ?? 'none') . " | Ricevuto: " . $csrf_token);
    echo json_encode(['success' => false, 'error' => 'Richiesta non valida (CSRF fallito)']);
    exit;
}

$username = $_SESSION['username'] ?? '';
$active_profile = $_SESSION['active_profile'] ?? null;
if (!$active_profile) {
    log_backend_debug("Nessun profilo attivo selezionato in sessione.");
    echo json_encode(['success' => false, 'error' => 'Nessun profilo attivo selezionato']);
    exit;
}
$profile_id = $active_profile['id'];

// 3. Valutazione parametri in input
$id = isset($data['id']) ? intval($data['id']) : 0;
$type = isset($data['type']) ? trim($data['type']) : '';
$title = isset($data['title']) ? trim($data['title']) : '';
$poster_path = isset($data['poster_path']) ? trim($data['poster_path']) : '';
$season = isset($data['season']) ? intval($data['season']) : 0;
$episode = isset($data['episode']) ? intval($data['episode']) : 0;
$progress = isset($data['progress']) ? intval($data['progress']) : 0;
$seconds = isset($data['seconds']) ? intval($data['seconds']) : 0;
$watched_episodes = isset($data['watched_episodes']) ? $data['watched_episodes'] : null;
$delete = isset($data['delete']) ? (bool)$data['delete'] : false;

if ($id <= 0 || !in_array($type, ['movie', 'tv']) || (empty($title) && !$delete)) {
    log_backend_debug("Parametri in input non validi. id: $id, type: $type, title: '$title'");
    echo json_encode(['success' => false, 'error' => 'Dati in input non validi']);
    exit;
}

$profiles_file = __DIR__ . '/user_profiles.json';
$all_profiles = [];
if (file_exists($profiles_file)) {
    $raw = file_get_contents($profiles_file);
    $all_profiles = json_decode($raw, true) ?? [];
}

if (!isset($all_profiles[$username]) || empty($all_profiles[$username])) {
    $config_file = __DIR__ . '/users_config.php';
    $config = file_exists($config_file) ? require $config_file : [];
    $profiles = $config['users'][$username]['profiles'] ?? [];
    $all_profiles[$username] = $profiles;
}

$user_profiles = &$all_profiles[$username];
$found = false;
$updated_history = [];

foreach ($user_profiles as &$profile) {
    if ($profile['id'] === $profile_id) {
        $found = true;
        
        if (!isset($profile['watch_history']) || !is_array($profile['watch_history'])) {
            $profile['watch_history'] = [];
        }
        
        // Cerca se il contenuto (stesso ID e tipo) è già nella cronologia
        $found_index = -1;
        for ($i = 0; $i < count($profile['watch_history']); $i++) {
            $item = $profile['watch_history'][$i];
            if (intval($item['id']) === $id && $item['type'] === $type) {
                $found_index = $i;
                break;
            }
        }

        if ($delete) {
            if ($found_index !== -1) {
                array_splice($profile['watch_history'], $found_index, 1);
            }
            $updated_history = $profile['watch_history'];
            $_SESSION['active_profile'] = $profile;
            break;
        }
        
        // Se c'è un'istanza esistente, preserviamo il suo watched_episodes se non ne passiamo uno nuovo
        $existing_watched = null;
        if ($found_index !== -1 && isset($profile['watch_history'][$found_index]['watched_episodes'])) {
            $existing_watched = $profile['watch_history'][$found_index]['watched_episodes'];
        }
        
        // Crea l'oggetto cronologia
        $history_item = [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'poster_path' => $poster_path,
            'progress' => $progress,
            'seconds' => $seconds,
            'timestamp' => time()
        ];
        
        if ($type === 'tv') {
            $history_item['season'] = $season;
            $history_item['episode'] = $episode;
            
            if ($watched_episodes !== null && is_array($watched_episodes)) {
                $history_item['watched_episodes'] = $watched_episodes;
            } else {
                // Se non c'è, inizializziamo o usiamo quello esistente
                $history_item['watched_episodes'] = $existing_watched ?? new stdClass();
            }
            
            // Se stiamo salvando un progresso normale da riproduzione (quindi non stiamo passando un watched_episodes esplicito),
            // registriamo il progresso dell'episodio corrente in watched_episodes
            if ($watched_episodes === null) {
                // stdClass non supporta l'assegnazione con array style se non convertito, quindi trattiamolo come array
                if (is_object($history_item['watched_episodes'])) {
                    $history_item['watched_episodes'] = (array)$history_item['watched_episodes'];
                }
                $key = "{$season}_{$episode}";
                $history_item['watched_episodes'][$key] = [
                    'progress' => $progress,
                    'seconds' => $seconds
                ];
            }
        }
        
        if ($found_index !== -1) {
            // Rimuovi la vecchia istanza per riposizionarla all'inizio (recency)
            array_splice($profile['watch_history'], $found_index, 1);
        }
        
        // Inserisci all'inizio
        array_unshift($profile['watch_history'], $history_item);
        
        // Mantieni al massimo 10 elementi nella cronologia
        if (count($profile['watch_history']) > 10) {
            $profile['watch_history'] = array_slice($profile['watch_history'], 0, 10);
        }
        
        $updated_history = $profile['watch_history'];
        
        // Aggiorna il profilo attivo in sessione
        $_SESSION['active_profile'] = $profile;
        break;
    }
}
unset($profile);

if (!$found) {
    log_backend_debug("Profilo non trovato nel database profili: ID " . $profile_id);
    echo json_encode(['success' => false, 'error' => 'Profilo non trovato']);
    exit;
}

// 4. Scrittura su database e file
// Salvataggio nel database se la connessione è attiva
if (isset($pdo)) {
    try {
        $stmt_del = $pdo->prepare("DELETE FROM watch_progress WHERE profile_id = :profile_id AND content_id = :id AND content_type = :type");
        $stmt_del->execute(['profile_id' => $profile_id, 'id' => $id, 'type' => $type]);

        if (!$delete) {
            $stmt_ins = $pdo->prepare("
                INSERT INTO watch_progress (username, profile_id, content_id, content_type, progress, seconds, last_updated)
                VALUES (:username, :profile_id, :id, :type, :progress, :seconds, NOW())
            ");
            $stmt_ins->execute([
                'username' => $username,
                'profile_id' => $profile_id,
                'id' => $id,
                'type' => $type,
                'progress' => $progress,
                'seconds' => $seconds
            ]);
        }
        log_backend_debug("Salvataggio su DB avvenuto con successo.");
    } catch (PDOException $e) {
        log_backend_debug("Errore database: " . $e->getMessage());
    }
}

// Scrittura originale su user_profiles.json (per compatibilità)
if (file_put_contents($profiles_file, json_encode($all_profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    log_backend_debug("Scrittura su user_profiles.json avvenuta con successo. Elementi cronologia: " . count($updated_history));
    echo json_encode(['success' => true, 'watch_history' => $updated_history]);
} else {
    log_backend_debug("Scrittura su user_profiles.json fallita.");
    echo json_encode(['success' => false, 'error' => 'Impossibile salvare la cronologia nel file']);
}
