<?php
/**
 * save_profiles.php
 * Riceve i nuovi profili via AJAX e li salva nel database PostgreSQL.
 */
session_start();
require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/db_helper.php';

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

if (count($new_profiles) === 0) {
    echo json_encode(['success' => false, 'error' => 'Devi avere almeno un profilo.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Cancelliamo i vecchi profili per questo username
    $stmt = $pdo->prepare("DELETE FROM user_profiles WHERE username = ?");
    $stmt->execute([$username]);

    // 2. Inseriamo i nuovi profili
    $stmt = $pdo->prepare("
        INSERT INTO user_profiles (id, username, name, avatar, color, allowed_categories, allowed_channels, favorites, vod_favorites)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($new_profiles as $p) {
        $stmt->execute([
            $p['id'] ?? (substr($username, 0, 3) . '_' . uniqid()),
            $username,
            $p['name'] ?? 'Nuovo Profilo',
            $p['avatar'] ?? 'ph-user-circle',
            $p['color'] ?? '#00f2fe',
            json_encode($p['allowed_categories'] ?? []),
            json_encode($p['allowed_channels'] ?? []),
            json_encode($p['favorites'] ?? []),
            json_encode($p['vod_favorites'] ?? [])
        ]);
    }

    $pdo->commit();

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
        if (!$found) {
            unset($_SESSION['active_profile']);
        }
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Errore salvataggio profili: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Impossibile salvare i profili nel database.']);
}
