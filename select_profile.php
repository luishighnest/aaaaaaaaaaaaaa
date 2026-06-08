<?php
/**
 * select_profile.php
 * Schermata di selezione del profilo attivo (Stile Netflix).
 */
session_start();

// Previeni il caching della pagina da parte del browser
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// Verifica che l'utente sia loggato
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$config_file = __DIR__ . '/users_config.php';
$profiles = [];

if (file_exists($config_file)) {
    $config = require $config_file;
    
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
    
    $profiles = $config['users'][$username]['profiles'] ?? [];
}

// Override with custom user profiles if available
$custom_profiles_file = __DIR__ . '/user_profiles.json';
if (file_exists($custom_profiles_file)) {
    $custom_data = json_decode(file_get_contents($custom_profiles_file), true);
    if (isset($custom_data[$username]) && is_array($custom_data[$username])) {
        $profiles = $custom_data[$username];
    }
}

// Se viene selezionato un profilo
if (isset($_GET['profile_id'])) {
    $profile_id = $_GET['profile_id'];
    
    // Cerca il profilo corrispondente nella configurazione per sicurezza
    $selected_profile = null;
    foreach ($profiles as $profile) {
        if ($profile['id'] === $profile_id) {
            $selected_profile = $profile;
            break;
        }
    }
    
    if ($selected_profile !== null) {
        $_SESSION['active_profile'] = $selected_profile;
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PZ8 - Chi sta guardando?</title>
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
      background-image: var(--bg-profile-gradient, radial-gradient(circle at center, rgba(15, 23, 42, 0.4) 0%, transparent 60%));
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }

    .profile-selection-container {
      width: 100%;
      max-width: 800px;
      text-align: center;
      display: flex;
      flex-direction: column;
      gap: 3.5rem;
      animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .selection-header h1 {
      font-size: 2.8rem;
      font-weight: 800;
      letter-spacing: -1px;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
    }

    .selection-header p {
      font-size: 1.05rem;
      color: #94a3b8;
    }

    .profiles-grid {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 2.5rem;
      flex-wrap: wrap;
    }

    .profile-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.2rem;
      text-decoration: none;
      cursor: pointer;
      width: 150px;
    }

    .profile-avatar-wrapper {
      width: 120px;
      height: 120px;
      border-radius: 24px;
      background: var(--bg-input, rgba(255, 255, 255, 0.03));
      border: 2px solid var(--border-subtle);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
      overflow: hidden;
    }

    .profile-avatar-wrapper::after {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: 22px;
      border: 3px solid transparent;
      transition: all 0.3s;
    }

    .profile-avatar-wrapper i {
      font-size: 3.2rem;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .profile-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: #94a3b8;
      transition: all 0.3s;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }

    /* Hover effects per-profile accent colors */
    .profile-card:hover .profile-avatar-wrapper {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 
                  0 0 30px var(--profile-hover-glow);
      border-color: var(--profile-accent);
      background: var(--profile-bg-hover);
    }

    .profile-card:hover .profile-avatar-wrapper::after {
      border-color: var(--profile-accent);
    }

    .profile-card:hover .profile-avatar-wrapper i {
      color: var(--text-primary) !important;
      transform: scale(1.1);
    }

    .profile-card:hover .profile-name {
      color: var(--text-primary);
    }

    .manage-profiles-btn {
      margin-top: 1rem;
      align-self: center;
      background: transparent;
      border: 1.5px solid var(--border-strong);
      color: var(--text-muted);
      font-size: 0.9rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      padding: 10px 24px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.25s;
    }

    .manage-profiles-btn:hover {
      border-color: var(--accent);
      color: var(--text-primary);
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="dashboard-body">
  <div class="profile-selection-container">
    
    <div class="selection-header">
      <h1>Chi sta guardando?</h1>
      <p>Seleziona un profilo per accedere alla lista dei canali</p>
    </div>

    <div class="profiles-grid">
      <?php foreach ($profiles as $profile): ?>
        <?php 
          $color = $profile['color'] ?? '#ffffff';
          // Genera colore di glow e sfondo dinamico in base al colore del profilo
          $glow = $color . '25';
          $bg_hover = $color . '10';
        ?>
        <a href="?profile_id=<?= urlencode($profile['id']) ?>" 
           class="profile-card" 
           style="--profile-accent: <?= $color ?>; --profile-hover-glow: <?= $glow ?>; --profile-bg-hover: <?= $bg_hover ?>;">
          <div class="profile-avatar-wrapper">
            <i class="ph <?= htmlspecialchars($profile['avatar']) ?>" style="color: <?= $color ?>;"></i>
          </div>
          <span class="profile-name"><?= htmlspecialchars($profile['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <a href="logout.php" class="manage-profiles-btn">Esci dall'Account</a>

  </div>
  <script src="js/theme.js?v=1.1"></script>
</body>
</html>
