<?php
/**
 * update_channels.php
 * Legge sky_update.json (caricato via FTP da media_uploader.py)
 * e aggiorna channels.js con i nuovi URL Sky Italia.
 *
 * Chiamata: GET /update_channels.php?key=SKY_UPDATE_2026_SECRET
 * Oppure viene eseguito automaticamente ogni volta che sky_update.json
 * viene caricato (tramite cron o chiamata manuale).
 */

// ── Configurazione ─────────────────────────────────────────
define('SECRET_KEY',  'SKY_UPDATE_2026_SECRET');   // <-- cambia con una tua chiave
define('CHANNELS_JS', __DIR__ . '/js/channels.js');
define('UPDATE_JSON', __DIR__ . '/sky_update.json');
define('BACKUP_DIR',  __DIR__ . '/js/backups/');
define('MAX_BACKUPS', 10);
// ───────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

// Autenticazione via GET key
$key = $_GET['key'] ?? '';
if ($key !== SECRET_KEY) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Unauthorized']));
}

// Controlla che sky_update.json esista
if (!file_exists(UPDATE_JSON)) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'sky_update.json not found - run media_uploader.py first']));
}

// Leggi sky_update.json
$json_raw = file_get_contents(UPDATE_JSON);
$data     = json_decode($json_raw, true);

if (!$data || !isset($data['channels']) || !is_array($data['channels'])) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Invalid sky_update.json format']));
}

// Valida ogni canale
foreach ($data['channels'] as $ch) {
    if (!isset($ch['id'], $ch['code'])) {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Each channel needs id and code']));
    }
    if (strpos($ch['code'], 'https://') !== 0) {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => "URL must be https for id {$ch['id']}"]));
    }
}

// Leggi channels.js
if (!file_exists(CHANNELS_JS)) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'channels.js not found']));
}

$content = file_get_contents(CHANNELS_JS);
if ($content === false) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Cannot read channels.js']));
}

// Backup
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}
$backup_file = BACKUP_DIR . 'channels_' . date('Ymd_His') . '.js';
file_put_contents($backup_file, $content);

// Rimuovi backup vecchi
$backups = glob(BACKUP_DIR . 'channels_*.js');
if ($backups) {
    usort($backups, fn($a, $b) => filemtime($a) - filemtime($b));
    while (count($backups) > MAX_BACKUPS) {
        unlink(array_shift($backups));
    }
}

// ── Aggiorna ogni canale ──────────────────────────────────────────────────────
// Lavora riga per riga: trova la riga con id:N e sostituisce solo code:"..."
// Tutto il resto del file rimane INVARIATO.
// ─────────────────────────────────────────────────────────────────────────────
$updated  = [];
$notfound = [];
$lines    = explode("\n", $content);

foreach ($data['channels'] as $ch) {
    $id      = (int)$ch['id'];
    $new_url = $ch['code'];
    $found   = false;

    foreach ($lines as &$line) {
        // Cerca riga con id:26 o id: 26
        if (!preg_match('/\bid\s*:\s*' . $id . '\b/', $line)) {
            continue;
        }
        // Sostituisce solo code:"vecchio_url" → code:"nuovo_url"
        $new_line = preg_replace('/(\bcode\s*:\s*")[^"]*(")/s', '${1}' . $new_url . '${2}', $line);
        if ($new_line !== $line) {
            $line  = $new_line;
            $found = true;
            break;
        }
    }
    unset($line);

    if ($found) {
        $updated[] = $id;
    } else {
        $notfound[] = $id;
    }
}

// Scrivi channels.js aggiornato
if (!empty($updated)) {
    if (file_put_contents(CHANNELS_JS, implode("\n", $lines)) === false) {
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'Cannot write channels.js']));
    }
}

// Elimina sky_update.json dopo l'uso (sicurezza)
unlink(UPDATE_JSON);

echo json_encode([
    'ok'        => true,
    'updated'   => $updated,
    'not_found' => $notfound,
    'backup'    => basename($backup_file),
    'timestamp' => date('Y-m-d H:i:s'),
    'count'     => count($updated),
]);
