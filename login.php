<?php
/**
 * login.php
 * Gestisce l'autenticazione degli utenti.
 */
session_start();

$config_file = __DIR__ . '/users_config.php';
$config = file_exists($config_file) ? require $config_file : [];
$users = $config['users'] ?? [];

// Controllo scadenza abbonamento PZ8 (reale e bloccante)
$subscription_expiry = $config['subscription_expiry'] ?? '2027-12-31';
if (time() > strtotime($subscription_expiry . ' 23:59:59')) {
    $secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (isset($_COOKIE['remember_user'])) {
        setcookie('remember_user', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure_cookie,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure_cookie,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    session_destroy();
    header('Location: expired.php');
    exit;
}

// Funzioni helper per la gestione sicura del "Remember Me"
function getRememberTokensFile() {
    return __DIR__ . '/remember_tokens.json';
}

function verifyRememberToken($username, $rawToken) {
    $file = getRememberTokensFile();
    if (!file_exists($file)) {
        return false;
    }
    $tokens = json_decode(file_get_contents($file), true) ?? [];
    if (!isset($tokens[$username]) || !is_array($tokens[$username])) {
        return false;
    }
    
    $tokenHash = hash('sha256', $rawToken);
    $now = time();
    $valid = false;
    $updatedUserTokens = [];
    
    foreach ($tokens[$username] as $entry) {
        if ($entry['expires'] < $now) {
            continue; // Rimuove token scaduti
        }
        
        if (hash_equals($entry['token_hash'], $tokenHash)) {
            $valid = true;
        }
        $updatedUserTokens[] = $entry;
    }
    
    if (count($updatedUserTokens) > 0) {
        $tokens[$username] = $updatedUserTokens;
    } else {
        unset($tokens[$username]);
    }
    file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT));
    
    return $valid;
}

function createRememberToken($username) {
    $file = getRememberTokensFile();
    $tokens = [];
    if (file_exists($file)) {
        $tokens = json_decode(file_get_contents($file), true) ?? [];
    }
    
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expires = time() + (86400 * 30); // 30 giorni
    
    if (!isset($tokens[$username]) || !is_array($tokens[$username])) {
        $tokens[$username] = [];
    }
    
    // Limita il numero di sessioni ricordate contemporaneamente per utente (es. max 5 dispositivi)
    if (count($tokens[$username]) >= 5) {
        array_shift($tokens[$username]);
    }
    
    $tokens[$username][] = [
        'token_hash' => $tokenHash,
        'expires' => $expires
    ];
    
    file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT));
    return $rawToken;
}

// Controllo per auto-login tramite cookie "Remember me"
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (isset($_COOKIE['remember_user']) && isset($_COOKIE['remember_token'])) {
        $cookie_user = $_COOKIE['remember_user'];
        $cookie_token = $_COOKIE['remember_token'];
        
        if (isset($users[$cookie_user])) {
            if (verifyRememberToken($cookie_user, $cookie_token)) {
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $cookie_user;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Genera CSRF token per la sessione
                header('Location: select_profile.php');
                exit;
            }
        }
    }
}

// Se l'utente è già loggato, reindirizza alla selezione profili o alla dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['active_profile'])) {
        header('Location: index.php');
    } else {
        header('Location: select_profile.php');
    }
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']);

    if ($username !== '' && $password !== '') {
        if (isset($users[$username])) {
            $user_data = $users[$username];
            if (password_verify($password, $user_data['password'])) {
                // Login riuscito
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Genera CSRF token per la sessione
                
                // Se remember me è spuntato, salva i cookie per 30 giorni
                if ($remember) {
                    $token = createRememberToken($username);
                    $secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                    setcookie('remember_user', $username, [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'secure' => $secure_cookie,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                    setcookie('remember_token', $token, [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'secure' => $secure_cookie,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }
                
                header('Location: select_profile.php');
                exit;
            }
        }
        $error_message = 'Credenziali non valide. Riprova.';
    } else {
        $error_message = 'Inserisci sia lo username che la password.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PZ8 - Accedi</title>
  <link rel="stylesheet" href="css/style.css?v=1.1">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light-mode');
    }
  </script>
  <style>
    body {
      background: var(--bg-base);
      background-image: var(--bg-login-gradient, radial-gradient(circle at top right, rgba(0, 242, 254, 0.08) 0%, transparent 40%), radial-gradient(circle at bottom left, rgba(13, 110, 253, 0.05) 0%, transparent 50%));
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }

    .login-container {
      width: 100%;
      max-width: 420px;
      perspective: 1000px;
    }

    .login-card {
      background: var(--bg-card-glass, rgba(15, 15, 25, 0.65));
      backdrop-filter: blur(24px) saturate(1.3);
      -webkit-backdrop-filter: blur(24px) saturate(1.3);
      border: 1px solid var(--border-subtle);
      border-radius: 24px;
      padding: 3rem 2.5rem;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 
                  0 0 40px rgba(0, 242, 254, 0.02);
      display: flex;
      flex-direction: column;
      gap: 2rem;
      position: relative;
      overflow: hidden;
    }

    .login-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: linear-gradient(90deg, #00f2fe, #4facfe);
    }

    .login-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      text-align: center;
    }

    .login-logo {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #00f2fe 0%, #0072ff 100%);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      color: #020205;
      font-weight: 900;
      box-shadow: 0 8px 24px rgba(0, 242, 254, 0.25);
    }

    .login-title {
      font-size: 1.8rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      margin-top: 0.5rem;
      color: var(--text-primary);
    }

    .login-subtitle {
      font-size: 0.9rem;
      color: #94a3b8;
    }

    .login-form {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .form-label {
      font-size: 0.85rem;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .input-wrapper {
      position: relative;
    }

    .input-wrapper i.left-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      font-size: 1.1rem;
      pointer-events: none;
      transition: color 0.2s;
    }

    /* NUOVI STILI PER L'OCCHIO */
    .toggle-password {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      font-size: 1.2rem;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
    }

    .toggle-password:hover {
      color: #00f2fe;
    }

    .form-input {
      width: 100%;
      background: var(--bg-input, rgba(0, 0, 0, 0.2));
      border: 1px solid var(--border-subtle);
      border-radius: 12px;
      padding: 12px 42px 12px 42px; /* Cambiato padding-right a 42px per fare spazio all'occhio */
      color: var(--text-primary);
      font-size: 0.95rem;
      outline: none;
      transition: all 0.2s ease;
      box-sizing: border-box;
    }

    .form-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 12px var(--accent-glow);
      background: var(--bg-input-focus, rgba(0, 0, 0, 0.3));
    }

    .form-input:focus + i.left-icon {
      color: #00f2fe;
    }

    .error-alert {
      background: rgba(244, 63, 94, 0.1);
      border: 1px solid rgba(244, 63, 94, 0.2);
      color: #f43f5e;
      padding: 0.8rem 1rem;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.6rem;
      animation: shake 0.4s ease;
    }

    .btn-submit {
      margin-top: 0.5rem;
      background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
      color: #020205;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-size: 0.95rem;
      border: none;
      border-radius: 12px;
      padding: 14px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: all 0.2s ease;
      box-shadow: 0 8px 24px rgba(0, 242, 254, 0.15);
    }

    .btn-submit:hover {
      box-shadow: 0 12px 30px rgba(0, 242, 254, 0.3);
      transform: translateY(-1px);
    }

    .btn-submit:active {
      transform: translateY(0);
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-5px); }
      40%, 80% { transform: translateX(5px); }
    }
  </style>
</head>
<body class="dashboard-body">
  <div class="login-container">
    <div class="login-card">
      
      <div class="login-brand">
        <div class="login-logo">
          <i class="ph ph-play-circle"></i>
        </div>
        <h1 class="login-title">PZ8</h1>
        <p class="login-subtitle"></p>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="error-alert">
          <i class="ph ph-warning-circle"></i>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <form action="login.php" method="POST" class="login-form">
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <div class="input-wrapper">
            <input class="form-input" type="text" id="username" name="username" placeholder="Inserisci username" required autocomplete="username">
            <i class="ph ph-user left-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrapper">
            <input class="form-input" type="password" id="password" name="password" placeholder="Inserisci password" required autocomplete="current-password">
            <i class="ph ph-lock left-icon"></i>
            <button type="button" id="togglePasswordBtn" class="toggle-password" tabindex="-1">
              <i id="eyeIcon" class="ph ph-eye"></i>
            </button>
          </div>
        </div>

        <div class="form-group" style="flex-direction: row; align-items: center; gap: 0.5rem; margin-top: -0.5rem;">
          <input type="checkbox" id="remember" name="remember" style="width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer;">
          <label class="form-label" for="remember" style="text-transform: none; font-weight: 500; cursor: pointer; color: var(--text-secondary);">Ricorda le mie credenziali</label>
        </div>

        <button type="submit" class="btn-submit">
          <span>Accedi</span>
          <i class="ph ph-arrow-right"></i>
        </button>
      </form>

    </div>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const passwordInput = document.getElementById('password');
      const togglePasswordBtn = document.getElementById('togglePasswordBtn');
      const eyeIcon = document.getElementById('eyeIcon');

      togglePasswordBtn.addEventListener('click', function () {
        // Controlla il tipo attuale dell'input
        const isPassword = passwordInput.getAttribute('type') === 'password';
        
        // Scambia il tipo (da password a text e viceversa)
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        
        // Cambia l'icona (occhio aperto / occhio sbarrato)
        eyeIcon.className = isPassword ? 'ph ph-eye-slash' : 'ph ph-eye';
      });
    });
  </script>

  <script src="js/theme.js?v=1.1"></script>
</body>
</html>
