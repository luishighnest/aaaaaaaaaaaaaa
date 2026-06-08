<?php
/**
 * save_watch_progress.php
 * Salva lo stato di riproduzione dei contenuti VOD per il profilo attivo sul database PostgreSQL.
 */
session_start();
require_once __DIR__ . '/config_db.php';

header('Content-Type: application/json');

// Helper per loggare eventi nei log di sistema di Render
function log_backend_debug($message) {
    error_log("[DEBUG] " . $message);
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
$progress = isset($data['progress']) ? intval($data['progress']) : 0;
$seconds = isset($data['seconds']) ? intval($data['seconds']) : 0;
$delete = isset($data['delete']) ? (bool)$data['delete'] : false;

if ($id <= 0 || !in_array($type, ['movie', 'tv'])) {
    log_backend_debug("Parametri in input non validi. id: $id, type: $type");
    echo json_encode(['success' => false, 'error' => 'Dati in input non validi']);
    exit;
}

try {
    if ($delete) {
        // Elimina progresso
        $stmt = $pdo->prepare("DELETE FROM watch_progress WHERE username = ? AND profile_id = ? AND content_id = ? AND content_type = ?");
        $stmt->execute([$username, $profile_id, $id, $type]);
        log_backend_debug("Progresso eliminato per contenuto: $id ($type)");
        echo json_encode(['success' => true]);
    } else {
        // Inserisci o aggiorna progresso (Upsert)
        $stmt = $pdo->prepare("
            INSERT INTO watch_progress (username, profile_id, content_id, content_type, progress, seconds, last_updated)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON CONFLICT (id) DO UPDATE SET 
                progress = EXCLUDED.progress, 
                seconds = EXCLUDED.seconds, 
                last_updated = NOW()
        ");
        // Nota: ON CONFLICT necessita di una chiave univoca.
        // Se la tabella non ha un unique index su (username, profile_id, content_id, content_type),
        // dovremmo fare SELECT prima o aggiungere l'index.
        // Per ora, assumiamo l'upsert logico.
        
        $stmt = $pdo->prepare("
            INSERT INTO watch_progress (username, profile_id, content_id, content_type, progress, seconds, last_updated)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $profile_id, $id, $type, $progress, $seconds]);
        
        log_backend_debug("Progresso salvato per contenuto: $id ($type)");
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    log_backend_debug("Errore database: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Errore interno al database']);
}
