<?php
/**
 * logout.php
 * Esegue il logout distruggendo la sessione e reindirizza alla pagina di login.
 */
session_start();

// Svuota tutte le variabili di sessione
$_SESSION = [];

// Cancella il cookie di sessione se presente
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    $secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params["path"],
        'domain' => $params["domain"],
        'secure' => $secure_cookie || $params["secure"],
        'httponly' => $params["httponly"],
        'samesite' => 'Strict'
    ]);
}

// Revoca attiva del token "Remember Me" su server
if (isset($_COOKIE['remember_user']) && isset($_COOKIE['remember_token'])) {
    $cookie_user = $_COOKIE['remember_user'];
    $cookie_token = $_COOKIE['remember_token'];
    $file = __DIR__ . '/remember_tokens.json';
    
    if (file_exists($file)) {
        $tokens = json_decode(file_get_contents($file), true) ?? [];
        if (isset($tokens[$cookie_user]) && is_array($tokens[$cookie_user])) {
            $tokenHash = hash('sha256', $cookie_token);
            $tokens[$cookie_user] = array_filter($tokens[$cookie_user], function($entry) use ($tokenHash) {
                return !hash_equals($entry['token_hash'], $tokenHash);
            });
            
            if (count($tokens[$cookie_user]) === 0) {
                unset($tokens[$cookie_user]);
            } else {
                $tokens[$cookie_user] = array_values($tokens[$cookie_user]);
            }
            file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT));
        }
    }
}

// Cancella i cookie di "Remember me"
$secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
setcookie('remember_user', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $secure_cookie,
    'httponly' => true,
    'samesite' => 'Strict'
]);
setcookie('remember_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $secure_cookie,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Distrugge la sessione
session_destroy();

// Reindirizza al login
header("Location: login.php");
exit;
