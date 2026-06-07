<?php
/**
 * log_debug.php
 * Endpoint per registrare i log diagnostici del client in progress_debug.log
 */
session_start();
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$message = $data['message'] ?? 'No message';
$context = $data['context'] ?? [];

$log_file = __DIR__ . '/progress_debug.log';
$log_entry = sprintf(
    "[%s] [CLIENT] %s | Session: %s | Context: %s\n",
    date('Y-m-d H:i:s'),
    $message,
    json_encode([
        'username' => $_SESSION['username'] ?? 'none',
        'active_profile_id' => $_SESSION['active_profile']['id'] ?? 'none'
    ]),
    json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

file_put_contents($log_file, $log_entry, FILE_APPEND);
echo json_encode(['success' => true]);
