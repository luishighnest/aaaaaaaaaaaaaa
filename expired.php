<?php
/**
 * expired.php
 * Mostrato quando l'abbonamento premium PZ8 è realmente scaduto.
 */
session_start();

$config_file = __DIR__ . '/users_config.php';
$config = file_exists($config_file) ? require $config_file : [];
$subscription_expiry = $config['subscription_expiry'] ?? '2027-12-31';

// Se l'abbonamento NON è ancora scaduto, reindirizza alla home o al login
if (time() <= strtotime($subscription_expiry . ' 23:59:59')) {
    header('Location: login.php');
    exit;
}

// Se è scaduto, pulisci tutto per sicurezza
session_destroy();
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
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PZ8 - Abbonamento Scaduto</title>
  <link rel="stylesheet" href="css/style.css?v=1.2">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light-mode');
    }
  </script>
  <style>
    body {
      background: var(--bg-base);
      background-image: var(--bg-login-gradient, radial-gradient(circle at top right, rgba(239, 68, 68, 0.08) 0%, transparent 40%), radial-gradient(circle at bottom left, rgba(239, 68, 68, 0.03) 0%, transparent 50%));
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }

    .expired-container {
      width: 100%;
      max-width: 440px;
      perspective: 1000px;
    }

    .expired-card {
      background: var(--bg-card-glass, rgba(15, 15, 25, 0.65));
      backdrop-filter: blur(24px) saturate(1.3);
      -webkit-backdrop-filter: blur(24px) saturate(1.3);
      border: 1px solid rgba(239, 68, 68, 0.2);
      border-radius: 24px;
      padding: 3rem 2.5rem;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 
                  0 0 40px rgba(239, 68, 68, 0.05);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.5rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .expired-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: linear-gradient(90deg, #ef4444, #b91c1c);
    }

    .expired-icon-box {
      width: 72px;
      height: 72px;
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      color: #ef4444;
      box-shadow: 0 8px 24px rgba(239, 68, 68, 0.2);
      margin-bottom: 0.5rem;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { transform: scale(1); box-shadow: 0 8px 24px rgba(239, 68, 68, 0.2); }
      50% { transform: scale(1.05); box-shadow: 0 8px 32px rgba(239, 68, 68, 0.4); }
      100% { transform: scale(1); box-shadow: 0 8px 24px rgba(239, 68, 68, 0.2); }
    }

    .expired-title {
      font-size: 1.8rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      color: var(--text-primary);
      margin: 0;
    }

    .expired-desc {
      font-size: 0.95rem;
      color: var(--text-secondary);
      line-height: 1.6;
      margin: 0;
    }

    .expired-date-badge {
      background: rgba(239, 68, 68, 0.08);
      border: 1px solid rgba(239, 68, 68, 0.2);
      color: #ef4444;
      padding: 0.5rem 1rem;
      border-radius: 99px;
      font-weight: 700;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      margin: 0.5rem 0;
    }

    .expired-action {
      margin-top: 1rem;
      width: 100%;
    }

    .btn-contact {
      width: 100%;
      background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
      color: #ffffff;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-size: 0.9rem;
      border: none;
      border-radius: 12px;
      padding: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
      box-shadow: 0 8px 24px rgba(239, 68, 68, 0.2);
      transition: all 0.2s ease;
    }

    .btn-contact:hover {
      box-shadow: 0 12px 30px rgba(239, 68, 68, 0.4);
      transform: translateY(-1px);
    }

    /* Light mode override */
    :root.light-mode .expired-card {
      background: rgba(255, 255, 255, 0.95);
      border-color: rgba(239, 68, 68, 0.25);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05);
    }
  </style>
</head>
<body>
  <div class="expired-container">
    <div class="expired-card">
      <div class="expired-icon-box">
        <i class="ph ph-lock-keyhole"></i>
      </div>
      <h1 class="expired-title">Abbonamento Scaduto</h1>
      
      <div class="expired-date-badge">
        <i class="ph ph-calendar-x"></i>
        <span>Scaduto il <?= date('d/m/Y', strtotime($subscription_expiry)) ?></span>
      </div>

      <p class="expired-desc">
        Il tuo abbonamento premium alla piattaforma <strong>PZ8</strong> è terminato. 
        Tutti i profili associati a questo account sono al momento disabilitati.
      </p>

      <p class="expired-desc" style="font-size: 0.85rem; color: var(--text-muted);">
        Per rinnovare il servizio e ripristinare immediatamente l'accesso alla dashboard e a tutti i canali televisivi, contatta il gestore dell'abbonamento.
      </p>

      <div class="expired-action">
        <a href="mailto:support@pz8.tv?subject=Rinnovo%20Abbonamento%20PZ8" class="btn-contact">
          <i class="ph ph-envelope-simple"></i>
          <span>Richiedi Rinnovo</span>
        </a>
      </div>
    </div>
  </div>
</body>
</html>
