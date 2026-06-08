<?php
// Avvia sessione e verifica autenticazione
session_start();

// Sincronizza il profilo attivo in sessione con user_profiles.json se esiste
if (isset($_SESSION['username']) && isset($_SESSION['active_profile']['id'])) {
    $username = $_SESSION['username'];
    $profile_id = $_SESSION['active_profile']['id'];
    $profiles_file = file_exists(__DIR__ . '/user_profiles.json') 
        ? __DIR__ . '/user_profiles.json' 
        : (file_exists(dirname(__DIR__) . '/user_profiles.json') ? dirname(__DIR__) . '/user_profiles.json' : '');
    if ($profiles_file && file_exists($profiles_file)) {
        $profiles_data = json_decode(file_get_contents($profiles_file), true);
        if (isset($profiles_data[$username]) && is_array($profiles_data[$username])) {
            foreach ($profiles_data[$username] as $p) {
                if ($p['id'] === $profile_id) {
                    $_SESSION['active_profile'] = $p;
                    break;
                }
            }
        }
    }
}

// Previeni il caching della pagina
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

$config_file = __DIR__ . '/users_config.php';
$config = file_exists($config_file) ? require $config_file : [];
$subscription_expiry = $config['subscription_expiry'] ?? '2027-12-31';

if (time() > strtotime($subscription_expiry . ' 23:59:59')) {
    session_destroy();
    header('Location: expired.php');
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['active_profile'])) {
    header('Location: select_profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Film & Serie TV - VOD</title>
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
    window.addEventListener('error', function(e) {
      const msg = 'JS Error: ' + e.message + ' at ' + e.filename + ':' + e.lineno + ':' + e.colno;
      const displayError = () => {
        const div = document.createElement('div');
        div.style.position = 'fixed';
        div.style.top = '0';
        div.style.left = '0';
        div.style.width = '100%';
        div.style.background = 'red';
        div.style.color = 'white';
        div.style.zIndex = '999999';
        div.style.padding = '15px';
        div.style.fontFamily = 'monospace';
        div.style.whiteSpace = 'pre-wrap';
        div.textContent = msg;
        document.body.appendChild(div);
      };
      if (document.body) displayError();
      else window.addEventListener('DOMContentLoaded', displayError);
    });
    window.addEventListener('unhandledrejection', function(e) {
      const msg = 'Promise Rejection: ' + e.reason;
      const displayError = () => {
        const div = document.createElement('div');
        div.style.position = 'fixed';
        div.style.top = '60px';
        div.style.left = '0';
        div.style.width = '100%';
        div.style.background = 'orange';
        div.style.color = 'black';
        div.style.zIndex = '999999';
        div.style.padding = '15px';
        div.style.fontFamily = 'monospace';
        div.style.whiteSpace = 'pre-wrap';
        div.textContent = msg;
        document.body.appendChild(div);
      };
      if (document.body) displayError();
      else window.addEventListener('DOMContentLoaded', displayError);
    });

    (function() {
      const accent = localStorage.getItem('accent_color');
      const glow = localStorage.getItem('accent_glow');
      if (accent && glow) {
        document.documentElement.style.setProperty('--accent', accent);
        document.documentElement.style.setProperty('--accent-glow', glow);
      }
    })();
    document.documentElement.classList.add('no-transitions');
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light-mode');
    }
    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => {
        document.documentElement.classList.remove('no-transitions');
      }, 50);
    });
  </script>
  <!-- Iniezione CSRF, Preferiti e Cronologia VOD da PHP -->
  <?php
  if (!isset($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $active_profile = $_SESSION['active_profile'] ?? [];
  $vod_favs = $active_profile['vod_favorites'] ?? [];
  $vod_history = $active_profile['watch_history'] ?? [];
  ?>
  <script>
    window.__CSRF_TOKEN__ = "<?= $_SESSION['csrf_token'] ?>";
    window.__ACTIVE_PROFILE_VOD_FAVORITES__ = <?= json_encode($vod_favs) ?>;
    window.__ACTIVE_PROFILE_VOD_HISTORY__ = <?= json_encode($vod_history) ?>;
  </script>
  <style>
    body {
      margin: 0;
      height: 100vh;
      overflow: hidden;
      background: #020617;
      background-image: radial-gradient(circle at top right, rgba(15, 23, 42, 0.5) 0%, transparent 40%),
                        radial-gradient(circle at bottom left, rgba(2, 6, 23, 0.8) 0%, transparent 40%);
      color: var(--text-primary);
      font-family: var(--font-main);
    }
    
    .vod-page-layout {
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    /* Top Navbar Floating Premium */
    .vod-navbar {
      position: fixed;
      top: 15px;
      left: 20px;
      right: 20px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 25px;
      background: rgba(15, 23, 42, 0.5);
      backdrop-filter: blur(20px) saturate(1.8);
      -webkit-backdrop-filter: blur(20px) saturate(1.8);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 18px;
      z-index: 1000;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
      transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .vod-navbar.scrolled {
      top: 0;
      left: 0;
      right: 0;
      border-radius: 0;
      height: 70px;
      background: rgba(2, 6, 23, 0.85);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      border-left: none;
      border-right: none;
      border-top: none;
    }

    .vod-navbar.nav-hidden {
      transform: translateY(-120%);
      opacity: 0;
    }

    .vod-brand {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      cursor: pointer;
    }
    .vod-brand-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, var(--accent) 0%, #4facfe 100%);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      color: #000;
      box-shadow: 0 0 20px var(--accent-glow);
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .vod-brand:hover .vod-brand-icon {
      transform: scale(1.1) rotate(-5deg);
      box-shadow: 0 0 30px var(--accent-glow);
    }
    .vod-brand-text {
      font-size: 1.4rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      color: #fff;
      display: flex;
      align-items: center;
      text-transform: uppercase;
    }
    .vod-brand-text span.brand-num {
      color: var(--accent);
      margin-left: 2px;
    }
    .vod-brand-text span.brand-sub {
      font-size: 0.65rem;
      font-weight: 900;
      letter-spacing: 1.5px;
      background: rgba(255, 255, 255, 0.1);
      color: #fff !important;
      padding: 3px 8px;
      border-radius: 6px;
      margin-left: 10px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(5px);
    }
    
    .vod-nav-links {
      display: flex;
      gap: 5px;
      margin-left: 30px;
      flex: 1;
    }
    
    .vod-navbar .nav-link {
      padding: 0.55rem 1.2rem;
      border-radius: 11px;
      font-size: 0.8rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: rgba(255, 255, 255, 0.55);
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      align-items: center;
      gap: 0.6rem;
      cursor: pointer;
      border: 1px solid transparent;
      background: transparent;
    }
    
    .vod-navbar .nav-link i {
      font-size: 1.05rem;
      transition: transform 0.3s ease;
    }
    
    .vod-navbar .nav-link:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.06);
      border-color: rgba(255, 255, 255, 0.08);
    }
    
    .vod-navbar .nav-link:hover i {
      transform: translateY(-1px) scale(1.1);
    }
    
    .vod-navbar .nav-link.active {
      color: #000;
      background: linear-gradient(135deg, var(--accent) 0%, #4facfe 100%);
      font-weight: 900;
      border-color: var(--accent);
      box-shadow: 0 8px 25px rgba(0, 242, 254, 0.35), inset 0 2px 2px rgba(255, 255, 255, 0.3);
    }
    
    .vod-navbar .nav-link.active i {
      color: #000;
    }
    
    /* Barra di Ricerca High-End - Precision Alignment */
    .vod-navbar .nav-search {
      position: relative;
      width: 44px;
      height: 44px;
      margin-right: 15px;
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      background: rgba(255, 255, 255, 0.05);
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .vod-navbar .nav-search:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.15);
    }
    
    .vod-navbar .nav-search:focus-within {
      width: 320px;
      background: rgba(0, 0, 0, 0.4);
      border-color: var(--accent);
      box-shadow: 0 0 20px var(--accent-glow);
      cursor: default;
    }
    
    .vod-navbar .nav-search input {
      width: 100%;
      height: 100%;
      background: transparent;
      border: none;
      padding: 0 44px 0 1.2rem; /* Spazio esatto per le icone a destra */
      color: var(--text-primary);
      font-size: 0.95rem;
      font-weight: 600;
      outline: none;
      opacity: 0;
      transition: opacity 0.3s;
      cursor: pointer;
    }
    
    .vod-navbar .nav-search:focus-within input {
      opacity: 1;
      cursor: text;
    }
    
    /* Icona Lente e Icona X - Optical Centering & Precision */
    .vod-search-icon-wrapper {
      position: absolute;
      top: 0;
      right: 0;
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
      z-index: 5;
    }
    
    .vod-navbar .nav-search .search-icon {
      color: #fff;
      font-size: 1.25rem;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      margin-right: 2px; /* Spostamento millimetrico a sinistra per centratura ottica (perfezione visiva) */
    }
    
    .vod-navbar .nav-search:focus-within .search-icon {
      opacity: 0;
      transform: scale(0.5);
    }
    
    .vod-navbar .nav-search .clear-icon {
      position: absolute;
      right: 0; /* All'estremità destra esatta */
      width: 44px;
      height: 44px;
      display: none; /* Gestito via JS */
      align-items: center;
      justify-content: center;
      color: #fff;
      cursor: pointer;
      font-size: 1.15rem;
      z-index: 10;
      pointer-events: auto;
      transition: all 0.2s ease;
    }
    
    .vod-navbar .nav-search .clear-icon:hover {
      color: var(--danger);
      transform: scale(1.15);
    }

    /* ─── AUTOCOMPLETE DROPDOWN ORIGINALE ─── */
    .vod-search-dropdown {
      position: absolute;
      top: calc(100% + 15px);
      right: 0;
      width: 380px; /* Larghezza fissa per stabilità */
      background: rgba(2, 6, 23, 0.92);
      backdrop-filter: blur(28px) saturate(1.4);
      -webkit-backdrop-filter: blur(28px) saturate(1.4);
      border: 1px solid rgba(255, 255, 255, 0.09);
      border-radius: 16px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.03);
      overflow: hidden;
      z-index: 9999;
      opacity: 0;
      transform: translateY(-8px) scale(0.98);
      pointer-events: none;
      transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      max-height: 450px;
      overflow-y: auto;
    }
    .vod-search-dropdown.open {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: auto;
    }
    .vod-search-dropdown::-webkit-scrollbar {
      width: 4px;
    }
    .vod-search-dropdown::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 4px;
    }

    /* Header del dropdown con query */
    .vod-dropdown-header {
      padding: 12px 18px;
      font-size: 0.75rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      background: rgba(255, 255, 255, 0.02);
    }
    .vod-dropdown-header i {
      color: var(--accent);
      font-size: 0.9rem;
    }

    /* Riga suggerimento ORIGINALE */
    .vod-suggestion-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 10px 18px;
      cursor: pointer;
      transition: background 0.15s ease;
      border-bottom: 1px solid rgba(255, 255, 255, 0.03);
      position: relative;
    }
    .vod-suggestion-item:last-child {
      border-bottom: none;
    }
    .vod-suggestion-item:hover {
      background: rgba(255, 255, 255, 0.05);
    }

    .vod-suggestion-thumb {
      width: 38px;
      height: 56px;
      object-fit: cover;
      border-radius: 6px;
      background: #1a1a24;
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .vod-suggestion-thumb-placeholder {
      width: 38px;
      height: 56px;
      background: rgba(255,255,255,0.05);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--text-muted);
    }
    .vod-suggestion-info { flex: 1; min-width: 0; }
    .vod-suggestion-title {
      font-weight: 700;
      font-size: 0.9rem;
      color: #fff;
      margin-bottom: 3px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .vod-suggestion-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.75rem;
      color: var(--text-muted);
    }
    .vod-suggestion-type {
      text-transform: uppercase;
      font-weight: 800;
      font-size: 0.65rem;
      padding: 1px 5px;
      border-radius: 4px;
      background: rgba(255,255,255,0.08);
    }
    .vod-suggestion-type.movie { color: var(--accent); }
    .vod-suggestion-type.tv { color: #f43f5e; }
    .vod-suggestion-rating { 
      display: flex;
      align-items: center;
      gap: 3px;
      color: #fbbf24; 
      font-weight: 800; 
    }
    .vod-suggestion-arrow { 
      font-size: 0.9rem; 
      color: var(--text-muted); 
      opacity: 0.4;
    }

    .vod-suggestion-item.keyboard-active {
      background: rgba(255, 255, 255, 0.07);
    }
    .vod-suggestion-item:hover .vod-suggestion-title,
    .vod-suggestion-item.keyboard-active .vod-suggestion-title {
      color: #fff;
    }

    /* Thumbnail suggerimento */
    .vod-suggestion-thumb {
      width: 44px;
      height: 62px;
      border-radius: 7px;
      object-fit: cover;
      flex-shrink: 0;
      background: rgba(15, 23, 42, 0.6);
      display: block;
    }
    .vod-suggestion-thumb-placeholder {
      width: 44px;
      height: 62px;
      border-radius: 7px;
      background: rgba(15, 23, 42, 0.6);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255, 255, 255, 0.15);
      font-size: 1.2rem;
    }

    /* Info testo suggerimento */
    .vod-suggestion-info {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .vod-suggestion-title {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text-primary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      transition: color 0.15s ease;
    }
    .vod-suggestion-meta {
      display: flex;
      align-items: center;
      gap: 7px;
    }
    .vod-suggestion-type {
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 2px 6px;
      border-radius: 4px;
    }
    .vod-suggestion-type.movie {
      background: rgba(99, 102, 241, 0.2);
      color: #818cf8;
      border: 1px solid rgba(99, 102, 241, 0.2);
    }
    .vod-suggestion-type.tv {
      background: rgba(16, 185, 129, 0.15);
      color: #34d399;
      border: 1px solid rgba(16, 185, 129, 0.15);
    }
    .vod-suggestion-year {
      font-size: 0.72rem;
      color: var(--text-muted);
      font-weight: 600;
    }
    .vod-suggestion-rating {
      font-size: 0.72rem;
      color: #fbbf24;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 3px;
    }

    /* Freccia di accesso rapido */
    .vod-suggestion-arrow {
      font-size: 0.9rem;
      color: rgba(255, 255, 255, 0.2);
      flex-shrink: 0;
      transition: color 0.15s ease, transform 0.15s ease;
    }
    .vod-suggestion-item:hover .vod-suggestion-arrow,
    .vod-suggestion-item.keyboard-active .vod-suggestion-arrow {
      color: var(--accent);
      transform: translateX(3px);
    }

    /* Stato loading nel dropdown */
    .vod-dropdown-loading {
      padding: 18px 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--text-muted);
      font-size: 0.82rem;
      font-weight: 600;
    }
    .vod-dropdown-loading-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--accent);
      animation: dd-pulse 1.2s ease-in-out infinite;
    }
    .vod-dropdown-loading-dot:nth-child(2) { animation-delay: 0.2s; }
    .vod-dropdown-loading-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes dd-pulse {
      0%, 100% { opacity: 0.3; transform: scale(0.8); }
      50% { opacity: 1; transform: scale(1.1); }
    }

    /* Footer "mostra tutti i risultati" */
    .vod-dropdown-footer {
      padding: 10px 14px;
      text-align: center;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--accent);
      cursor: pointer;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      transition: background 0.15s ease, color 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .vod-dropdown-footer:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #fff;
    }

    /* Bottone Torna Indietro (Fisso a destra) - Matching Style */
    .vod-back-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.55rem 1.2rem;
      background: rgba(15, 23, 42, 0.35);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 11px;
      color: rgba(255, 255, 255, 0.55);
      font-size: 0.8rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      backdrop-filter: blur(10px);
      flex-shrink: 0;
    }
    .vod-back-btn i {
      font-size: 1.05rem;
      transition: transform 0.3s ease;
    }
    .vod-back-btn:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #fff;
      border-color: rgba(255, 255, 255, 0.25);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    .vod-back-btn:hover i {
      transform: translateX(-3px);
    }
    .vod-back-btn:active {
      transform: scale(0.96);
    }

    /* VOD Main Area (Scrollable) */
    .dash-main {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding-top: 70px; /* Spazio per navbar fissa */
      scroll-behavior: smooth;
    }
    .dash-main::-webkit-scrollbar {
      width: 8px;
    }
    .dash-main::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.1);
    }
    .dash-main::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.08);
      border-radius: 10px;
    }
    .dash-main::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.15);
    }

    /* Hero Banner Premium */
    .vod-hero-banner {
      position: relative;
      width: 100%;
      height: 55vh;
      min-height: 380px;
      max-height: 600px;
      overflow: hidden;
      display: flex;
      align-items: center;
      padding: 0 40px;
      margin-bottom: 2rem;
      border-bottom: 1px solid var(--border-subtle);
      margin-top: -70px; /* Estende lo sfondo sotto la navbar */
      padding-top: 70px;
    }
    
    .vod-hero-bg {
      position: absolute;
      inset: 0;
      z-index: 0;
      background: #000;
    }
    .vod-hero-bg img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top; /* Evita il taglio della parte superiore della copertina */
      opacity: 0.65;
    }
    
    .vod-hero-overlay-horizontal {
      position: absolute;
      inset: 0;
      background: linear-gradient(to right, rgba(2, 6, 23, 0.95) 0%, rgba(2, 6, 23, 0.75) 30%, rgba(2, 6, 23, 0.3) 65%, transparent 100%);
      z-index: 1;
    }
    
    .vod-hero-overlay-vertical {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, var(--bg-base) 0%, transparent 60%, rgba(2, 6, 23, 0.2) 100%);
      z-index: 1;
    }
    
    .vod-hero-content {
      position: relative;
      z-index: 2;
      max-width: 650px;
      display: flex;
      flex-direction: column;
      gap: 1.1rem;
      animation: fadeInHero 0.8s ease-out;
    }
    
    @keyframes fadeInHero {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .vod-hero-title {
      font-family: var(--font-main);
      font-size: 3.2rem;
      font-weight: 900;
      line-height: 1.1;
      color: #fff;
      text-shadow: 0 4px 15px rgba(0,0,0,0.5);
      letter-spacing: -1.5px;
    }
    
    .vod-hero-desc {
      font-size: 1.02rem;
      line-height: 1.5;
      color: var(--text-secondary);
      text-shadow: 0 2px 4px rgba(0,0,0,0.5);
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .vod-hero-meta {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .vod-hero-type-badge {
      background: var(--border-strong);
      color: var(--text-primary);
      font-weight: 700;
      font-size: 0.75rem;
      padding: 3px 8px;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .vod-hero-buttons {
      display: flex;
      gap: 15px;
      margin-top: 0.4rem;
    }
    
    .vod-hero-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 0.75rem 1.6rem;
      border-radius: 99px;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      transition: var(--transition);
      border: none;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .vod-hero-btn.play {
      background: linear-gradient(135deg, var(--accent) 0%, #4facfe 100%);
      color: #000;
      box-shadow: 0 4px 15px var(--accent-glow);
    }
    .vod-hero-btn.play:hover {
      box-shadow: 0 8px 25px var(--accent-glow);
      transform: translateY(-2px);
    }
    
    .vod-hero-btn.info {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid var(--border-subtle);
      color: #fff;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    .vod-hero-btn.info:hover {
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(255, 255, 255, 0.2);
      transform: translateY(-2px);
    }

    /* Stile Righe Netflix */
    .vod-row-container {
      margin-bottom: 1.5rem;
      padding-left: 0;
      position: relative; 
      z-index: 1;
      transition: z-index 0.3s step-end;
    }
    
    .vod-row-container:hover {
      z-index: 100;
      transition: z-index 0s;
    }
    
    .vod-row-title { 
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text-primary);
      margin-bottom: 5px; 
      display: flex;
      align-items: center;
      gap: 10px;
      letter-spacing: -0.5px;
      text-transform: uppercase;
      padding-left: 40px;
    }
    .vod-row-title::before {
      content: '';
      display: block;
      width: 4px;
      height: 1.35rem;
      background: var(--accent);
      border-radius: 2px;
      box-shadow: 0 0 8px var(--accent-glow);
    }
    
    .vod-row {
      display: flex;
      gap: 20px;
      overflow-x: hidden; /* Annulla lo scrolling orizzontale manuale */
      padding: 45px 40px 45px 40px;
      scroll-behavior: smooth;
      margin-top: -15px;
    }
    
    /* Frecce di navigazione per le righe */
    .vod-row-arrow-left,
    .vod-row-arrow-right {
      position: absolute;
      top: calc(50% + 15px); /* Centrato in base alle locandine, considerando l'altezza del titolo */
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(15, 23, 42, 0.7);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: rgba(255, 255, 255, 0.8);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      opacity: 0;
      z-index: 10;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
      transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1), 
                  transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), 
                  background-color 0.2s, 
                  color 0.2s, 
                  box-shadow 0.2s;
    }
    .vod-row-arrow-left {
      left: 15px;
      transform: translateY(-50%);
    }
    .vod-row-arrow-right {
      right: 15px;
      transform: translateY(-50%);
    }
    .vod-row-container:hover .vod-row-arrow-left,
    .vod-row-container:hover .vod-row-arrow-right {
      opacity: 1;
    }
    .vod-row-arrow-left:hover,
    .vod-row-arrow-right:hover {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
      box-shadow: 0 0 15px var(--accent-glow);
      transform: translateY(-50%) scale(1.1);
    }
    .vod-row-arrow-left:active,
    .vod-row-arrow-right:active {
      transform: translateY(-50%) scale(0.92);
    }

    /* Poster Card Minimalist Premium */
    .vod-card {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
      background: #000;
      flex-shrink: 0;
      border: 1px solid rgba(255, 255, 255, 0.03);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
    }
    
    /* Overlay Superiore Soft per badge */
    .vod-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 35%;
      background: linear-gradient(to bottom, rgba(0, 0, 0, 0.5) 0%, transparent 100%);
      z-index: 2;
      opacity: 0.6;
      transition: opacity 0.4s ease;
    }

    .vod-card.landscape { width: 280px; aspect-ratio: 16 / 9; }
    .vod-card.portrait { width: 160px; aspect-ratio: 2 / 3; }
    
    .vod-card:hover { 
      transform: scale(1.05) translateY(-5px);
      z-index: 50;
      border-color: rgba(255, 255, 255, 0.15);
      box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8);
    }
    
    .vod-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), filter 0.8s ease;
      filter: brightness(0.9);
    }
    
    .vod-card:hover img {
      transform: scale(1.08);
      filter: brightness(1);
    }
    
    .vod-card-badge {
      position: absolute;
      top: 12px;
      right: 12px;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      padding: 4px 8px;
      border-radius: 6px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: #fbbf24;
      font-weight: 700;
      font-size: 0.72rem;
      display: flex;
      align-items: center;
      gap: 4px;
      z-index: 10;
      transition: all 0.3s ease;
    }

    .vod-card-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.2) 60%, transparent 100%);
      padding: 16px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      opacity: 0;
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      z-index: 6;
    }
    
    .vod-card:hover .vod-card-overlay {
      opacity: 1;
    }
    
    .vod-card-title {
      font-weight: 700;
      font-size: 0.9rem;
      color: #fff;
      line-height: 1.2;
      text-align: left;
      font-family: var(--font-main);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      transform: translateY(8px);
      transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .vod-card:hover .vod-card-title {
      transform: translateY(0);
    }
    
    .vod-card-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-start;
      margin-top: 12px;
      transform: translateY(12px);
      opacity: 0;
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .vod-card:hover .vod-card-actions {
      transform: translateY(0);
      opacity: 1;
    }
    
    .vod-card-btn {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s ease;
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }
    
    .vod-card-btn:hover {
      background: #fff;
      color: #000;
      transform: scale(1.1);
    }
    
    .vod-card-btn.play {
      background: #fff;
      color: #000;
      border: none;
    }
    
    .vod-card-btn.fav i {
      color: var(--danger);
    }

    /* Episode Badge sulla Card (Serie TV) - Minimalist */
    .vod-card-episode-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      color: #fff;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 0.65rem;
      font-weight: 800;
      z-index: 10;
      border: 1px solid rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      gap: 5px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .vod-card-episode-badge i {
      font-size: 0.8rem;
      color: #fff;
    }

    /* Minimalist Integrated Progress Bar */
    .vod-card-progress-container {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: rgba(255, 255, 255, 0.1);
      z-index: 15;
    }
    
    .vod-card-progress-bar {
      height: 100%;
      background: var(--accent);
      transition: width 0.3s ease;
    }
    
    .vod-card:hover .vod-card-progress-container {
      height: 3px;
    }

    
    .vod-loading, .vod-empty {
      grid-column: 1 / -1;
      text-align: center;
      padding: 4rem;
      color: var(--text-muted);
      font-size: 1.2rem;
      font-weight: 600;
      font-family: var(--font-main);
    }

    /* Griglia Ricerca */
    .vod-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 1.8rem;
      padding: 20px 40px;
    }

    /* Catalogo Filtri & Grid Styling */
    .vod-catalog-header {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      padding: 20px 40px 0 40px;
    }
    .vod-filter-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 1.2rem;
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid var(--border-subtle);
      padding: 1rem 1.5rem;
      border-radius: 16px;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
      flex: 1;
      min-width: 150px;
    }
    .filter-group label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-secondary);
    }
    .filter-group select {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--border-subtle);
      border-radius: 8px;
      padding: 0.55rem 0.8rem;
      color: var(--text-primary);
      font-family: var(--font-main);
      font-size: 0.85rem;
      font-weight: 600;
      outline: none;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .filter-group select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 2px var(--accent-glow);
    }
    .filter-group select option {
      background: #0f172a;
      color: #fff;
    }
    .vod-filter-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      background: var(--accent);
      color: #000;
      border: none;
      border-radius: 8px;
      padding: 0.55rem 1.2rem;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 0 10px var(--accent-glow);
      height: 38px;
      width: 100%;
      box-sizing: border-box;
    }
    .vod-filter-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 18px var(--accent-glow);
      background: #fff;
      color: #000;
    }
    .vod-filter-btn:active {
      transform: translateY(0);
    }
    .vod-catalog-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 1.5rem;
      padding: 20px 40px;
    }
    @media (max-width: 1200px) {
      .vod-catalog-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }
    
    .vod-search-section-title {
      font-size: 1.8rem;
      font-weight: 800;
      color: #fff;
      padding: 20px 40px 0 40px;
      font-family: var(--font-main);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .vod-search-section-title::before {
      content: '';
      display: block;
      width: 4px;
      height: 1.8rem;
      background: var(--accent);
      border-radius: 2px;
      box-shadow: 0 0 8px var(--accent-glow);
    }

    /* VOD MODAL ULTRA-PREMIUM */
    .vod-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(2, 6, 23, 0.6);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease;
    }
    .vod-modal.open {
      opacity: 1;
      pointer-events: auto;
    }
    
    .vod-modal-content {
      background: rgba(2, 6, 23, 0.88);
      backdrop-filter: blur(30px);
      -webkit-backdrop-filter: blur(30px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 24px;
      width: 90%;
      max-width: 900px;
      position: relative; 
      transform: scale(0.95) translateY(20px);
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      overflow: hidden;
      box-shadow: 0 30px 60px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.05); 
      display: flex;
      max-height: 85vh;
    }
    .vod-modal.open .vod-modal-content {
      transform: scale(1) translateY(0);
    }
    
    .vod-modal-topbar {
      display: flex;
      justify-content: flex-end;
      margin: -1rem -1.25rem -0.25rem 0;
      pointer-events: none;
    }
    .vod-modal-close {
      background: rgba(15, 23, 42, 0.78);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #fff;
      width: 35px;
      height: 35px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1rem;
      pointer-events: auto;
      transition: var(--transition);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255,255,255,0.08);
    }
    .vod-modal-close:hover {
      background: rgba(255, 255, 255, 0.12);
      color: var(--accent);
      border-color: rgba(255, 255, 255, 0.22);
      transform: translateY(-1px);
      box-shadow: 0 14px 32px rgba(0, 0, 0, 0.36);
    }
    .vod-modal-close:active {
      transform: scale(0.96);
    }
    
    .vod-modal-poster {
      width: 300px;
      flex-shrink: 0;
      padding: 2.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border-right: none;
      transition: width 0.25s ease;
    }
    .vod-modal-poster.is-landscape {
      width: 380px;
    }
    .vod-modal-poster.is-square {
      width: 320px;
    }
    .vod-modal-poster img {
      width: auto;
      max-width: 100%;
      height: auto;
      max-height: calc(85vh - 5rem);
      object-fit: contain;
      border-radius: 12px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.5);
      border: 1px solid rgba(255,255,255,0.05);
      display: block;
      background: rgba(15, 23, 42, 0.35);
    }
    .vod-modal-poster.is-portrait img {
      max-width: 230px;
    }
    .vod-modal-poster.is-landscape img {
      max-width: 320px;
    }
    .vod-modal-poster.is-square img {
      max-width: 260px;
    }
    
    .vod-modal-info {
      padding: 3rem;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 1.2rem;
      overflow-y: auto;
    }
    .vod-modal-info::-webkit-scrollbar {
      width: 6px;
    }
    .vod-modal-info::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 10px;
    }
    
    #vod-modal-title {
      font-family: var(--font-main);
      font-size: 2.2rem;
      margin: 0;
      line-height: 1.1;
      color: #fff;
      text-shadow: 0 2px 10px rgba(0,0,0,0.5);
      font-weight: 800;
      letter-spacing: -1px;
    }
    
    #vod-modal-tagline {
      font-style: italic;
      color: var(--accent);
      font-size: 1.05rem;
      margin-top: -5px;
      font-weight: 500;
    }
    
    .vod-modal-meta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    
    .vod-meta-badge { 
      background: rgba(255,255,255,0.04);
      padding: 5px 12px;
      border-radius: 30px; 
      font-size: 0.8rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px; 
      border: 1px solid rgba(255,255,255,0.05);
      color: var(--text-secondary);
      font-family: var(--font-alt);
    }
    .vod-meta-badge.rating {
      color: #fbbf24;
      background: rgba(251, 191, 36, 0.12);
      border-color: rgba(251, 191, 36, 0.25);
    }
    
    .vod-modal-desc {
      font-size: 0.95rem;
      line-height: 1.6;
      color: var(--text-secondary);
      margin-top: 5px;
    }
    
    .vod-modal-genres {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 5px;
    }
    .vod-genre-tag {
      background: rgba(255,255,255,0.03);
      padding: 4px 12px;
      border-radius: 8px;
      font-size: 0.8rem;
      color: var(--text-primary);
      border: 1px solid rgba(255,255,255,0.05);
      font-weight: 500;
      transition: var(--transition);
    }
    .vod-genre-tag:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.15);
    }

    /* Theater Video Player Overlay */
    .vod-player-overlay {
      position: fixed;
      inset: 0;
      background: #000;
      z-index: 10000;
      display: flex;
      flex-direction: column;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease;
    }
    .vod-player-overlay.open {
      opacity: 1;
      pointer-events: auto;
    }
    .vod-player-close,
    .vod-player-fullscreen,
    .vod-player-next-ep {
      position: absolute;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      cursor: pointer;
      z-index: 10001;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s, background-color 0.2s, border-color 0.2s, box-shadow 0.2s !important;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    .vod-player-close {
      top: 20px;
      left: 20px;
    }
    .vod-player-close:hover {
      background: #ef4444;
      border-color: #ef4444;
      box-shadow: 0 4px 20px rgba(239, 68, 68, 0.45);
      transform: translateY(-2px);
    }
    .vod-player-close:active {
      transform: translateY(0);
    }
    .vod-player-fullscreen {
      bottom: 15px;
      right: 20px;
      width: 38px;
      height: 38px;
      font-size: 1.1rem;
    }
    .vod-player-fullscreen:hover {
      background: var(--accent);
      border-color: var(--accent);
      box-shadow: 0 4px 20px var(--accent-glow);
      transform: translateY(-2px);
    }
    .vod-player-fullscreen:active {
      transform: translateY(0);
    }
    .vod-player-next-ep {
      top: 20px;
      right: 20px;
      display: none;
    }
    .vod-player-next-ep:hover {
      background: var(--accent);
      border-color: var(--accent);
      box-shadow: 0 4px 20px var(--accent-glow);
      transform: translateY(-2px);
    }
    .vod-player-next-ep:active {
      transform: translateY(0);
    }
    .vod-player-title-header {
      position: absolute;
      top: 25px;
      left: 50%;
      transform: translateX(-50%);
      text-align: center;
      z-index: 10001;
      pointer-events: none;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.95), 0 1px 4px rgba(0, 0, 0, 0.8);
      font-family: var(--font-main);
      opacity: 1;
      transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      width: 80%;
      max-width: 600px;
      display: block; /* Sempre visibile quando i controlli sono attivi */
    }
    #vod-player-title {
      font-size: 1.9rem;
      font-weight: 800;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: -0.5px;
      margin: 0;
    }
    #vod-player-subtitle {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-secondary);
      margin-top: 4px;
    }
    .vod-player-overlay.controls-hidden .vod-player-close,
    .vod-player-overlay.controls-hidden .vod-player-fullscreen,
    .vod-player-overlay.controls-hidden .vod-player-next-ep,
    .vod-player-overlay.controls-hidden .vod-player-title-header {
      opacity: 0;
      pointer-events: none;
    }
    .vod-player-mouse-tracker {
      position: absolute;
      inset: 0;
      z-index: 10000;
      background: transparent;
      pointer-events: none;
    }
    .vod-player-overlay.controls-hidden .vod-player-mouse-tracker {
      pointer-events: auto;
    }
    .vod-player-wrapper {
      flex: 1;
      width: 100%;
      height: 100%;
      position: relative;
    }
    .vod-player-wrapper iframe {
      width: 100%;
      height: 100%;
      border: none;
    }

    /* Serie TV Stagioni ed Episodi */
    #vod-season-select {
      background: rgba(15, 23, 42, 0.55);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 8px;
      padding: 0.55rem 2.2rem 0.55rem 1rem;
      color: var(--text-primary);
      font-family: var(--font-main);
      font-size: 0.88rem;
      font-weight: 700;
      outline: none;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='rgba(255,255,255,0.75)' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'%3E%3C/path%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 0.95rem;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      min-width: 140px;
    }
    #vod-season-select:hover {
      background-color: rgba(15, 23, 42, 0.85);
      border-color: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
    }
    #vod-season-select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 15px var(--accent-glow);
    }
    #vod-season-select option {
      background: #020617;
      color: #fff;
      font-weight: 600;
      padding: 10px;
    }
    #vod-episodes-list::-webkit-scrollbar {
      width: 6px;
    }
    #vod-episodes-list::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.08);
      border-radius: 10px;
    }
    #vod-episodes-list::-webkit-scrollbar-thumb:hover {
      background: var(--accent);
    }

    /* ─── EPISODE ROW CON THUMBNAIL ─── */
    .vod-episode-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 10px 14px 10px 10px;
      background: rgba(15, 23, 42, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.04);
      border-radius: 14px;
      transition: background 0.25s ease, border-color 0.25s ease, transform 0.2s ease;
      cursor: pointer;
      min-height: 80px;
    }
    .vod-episode-row:hover {
      background: rgba(15, 23, 42, 0.6);
      border-color: rgba(255, 255, 255, 0.1);
      transform: translateX(4px);
    }
    .vod-episode-row.watched {
      opacity: 0.55;
    }
    .vod-episode-row.watched:hover {
      opacity: 1;
    }
    .vod-episode-row.last-played {
      background: rgba(15, 23, 42, 0.45);
      border-color: rgba(255, 255, 255, 0.12);
      box-shadow: inset 3px 0 0 var(--accent);
    }
    @keyframes ep-last-played-pulse {
      0%, 100% { box-shadow: inset 3px 0 0 var(--accent); }
    }

    /* Badge "Riprendi qui" */
    .vod-ep-resume-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--accent);
      background: rgba(var(--accent-rgb, 0, 170, 255), 0.12);
      border: 1px solid rgba(var(--accent-rgb, 0, 170, 255), 0.25);
      padding: 2px 7px;
      border-radius: 4px;
      margin-top: 3px;
      width: fit-content;
    }
    .vod-ep-resume-badge i {
      font-size: 0.75rem;
    }

    /* Thumbnail Container */
    .vod-ep-thumb {
      position: relative;
      flex-shrink: 0;
      width: 140px;
      aspect-ratio: 16 / 9;
      border-radius: 9px;
      overflow: hidden;
      background: rgba(0, 0, 0, 0.5);
    }
    .vod-ep-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .vod-episode-row:hover .vod-ep-thumb img {
      transform: scale(1.06);
    }

    /* Overlay play sul thumbnail */
    .vod-ep-thumb-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.25s ease;
    }
    .vod-episode-row:hover .vod-ep-thumb-overlay {
      background: rgba(0, 0, 0, 0.45);
    }
    .vod-ep-play-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.92);
      color: #000;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      opacity: 0;
      transform: scale(0.7);
      transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
    }
    .vod-episode-row:hover .vod-ep-play-icon {
      opacity: 1;
      transform: scale(1);
    }

    /* Progress bar sovrapposta al thumbnail */
    .vod-ep-progress-bar {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: rgba(255, 255, 255, 0.15);
      border-radius: 0 0 9px 9px;
      overflow: hidden;
    }
    .vod-ep-progress-fill {
      height: 100%;
      background: var(--accent);
      border-radius: 0 0 9px 9px;
    }

    /* Placeholder se no thumbnail */
    .vod-ep-thumb-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255, 255, 255, 0.12);
      font-size: 1.8rem;
    }

    /* Numero episodio badge */
    .vod-ep-num-badge {
      position: absolute;
      bottom: 6px;
      left: 7px;
      font-size: 0.68rem;
      font-weight: 800;
      color: rgba(255, 255, 255, 0.8);
      background: rgba(0, 0, 0, 0.6);
      padding: 2px 6px;
      border-radius: 4px;
      letter-spacing: 0.4px;
      backdrop-filter: blur(4px);
      pointer-events: none;
    }

    /* Info Area */
    .vod-episode-info {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 4px;
      min-width: 0;
    }
    .vod-episode-title {
      font-weight: 700;
      font-size: 0.9rem;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.3;
    }
    .vod-episode-overview {
      font-size: 0.78rem;
      color: var(--text-muted);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.45;
    }

    /* Actions */
    .vod-episode-actions {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }
    .vod-episode-play-btn {
      width: 34px;
      height: 34px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      cursor: pointer;
      transition: var(--transition);
      flex-shrink: 0;
      outline: none;
    }
    .vod-episode-status-btn {
      width: 28px;
      height: 28px;
      background: transparent;
      border: none;
      color: rgba(255, 255, 255, 0.35);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      cursor: pointer;
      transition: var(--transition);
      flex-shrink: 0;
      outline: none;
    }
    .vod-episode-row:hover .vod-episode-play-btn {
      background: var(--accent);
      color: #000;
      border-color: var(--accent);
      box-shadow: 0 0 12px var(--accent-glow);
    }
    .vod-episode-status-btn:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
      transform: scale(1.15);
    }
    @media (max-width: 500px) {
      .vod-ep-thumb { width: 90px; }
    }
    
    /* Pop-up Menu Stato Visione Episodio */
    #vod-episode-status-menu {
      position: fixed;
      z-index: 10000;
      background: rgba(15, 23, 42, 0.85);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 6px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.03);
      min-width: 190px;
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      animation: vod-menu-fade-in 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      transform-origin: top center;
    }
    @keyframes vod-menu-fade-in {
      from {
        opacity: 0;
        transform: scale(0.95) translateY(-4px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }
    #vod-episode-status-menu .menu-item {
      padding: 10px 14px;
      font-size: 0.85rem;
      color: #94a3b8;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 600;
      border-radius: 8px;
      transition: all 0.2s ease;
    }
    #vod-episode-status-menu .menu-item i {
      font-size: 1.1rem;
      transition: transform 0.2s ease;
    }
    #vod-episode-status-menu .menu-item:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #fff;
    }
    #vod-episode-status-menu .menu-item:hover i {
      transform: scale(1.1);
    }

    /* --- RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
      .vod-card-overlay {
        opacity: 1 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        background: linear-gradient(to top, rgba(2, 6, 23, 0.95) 0%, rgba(2, 6, 23, 0.15) 70%, transparent 100%) !important;
      }
      .vod-navbar {
        height: auto;
        min-height: 100px;
        flex-wrap: wrap;
        padding: 10px 15px;
        gap: 10px;
        background: linear-gradient(to bottom, rgba(2, 6, 23, 0.95) 0%, rgba(2, 6, 23, 0.75) 100%);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        border-bottom: 1px solid transparent;
      }
      .vod-brand-text {
        display: none; /* Nascondi logo di testo su schermi piccoli */
      }
      .vod-nav-links {
        order: 3;
        width: 100%;
        margin-left: 0;
        justify-content: space-around;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: 8px;
        margin-top: 2px;
      }
      .vod-navbar .nav-link {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
        gap: 0.3rem;
      }
      .vod-navbar .nav-search {
        order: 1;
        width: 150px;
        margin-right: 0;
        flex: 1;
      }
      .vod-navbar .nav-search:focus-within {
        width: 180px;
      }
      .vod-back-btn {
        order: 2;
        padding: 0.4rem 0.9rem;
        font-size: 0.8rem;
      }
      .dash-main {
        padding-top: 115px; /* Più spazio per la navbar responsive a 2 righe */
      }
      .vod-row-container {
        padding-left: 0;
        margin-bottom: 1.5rem;
      }
      .vod-row-arrow-left,
      .vod-row-arrow-right {
        opacity: 0.8; /* Sempre visibile su mobile */
        width: 32px;
        height: 32px;
        font-size: 1.1rem;
        top: calc(50% + 10px);
      }
      .vod-row-arrow-left {
        left: 5px;
        transform: translateY(-50%);
      }
      .vod-row-arrow-right {
        right: 5px;
        transform: translateY(-50%);
      }
      .vod-row-title {
        font-size: 1.1rem;
        padding-left: 15px;
      }
      .vod-row {
        padding: 10px 15px 20px 15px;
      }
      .vod-grid {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 1rem;
        padding: 15px;
      }
      .vod-search-section-title {
        padding: 15px 15px 0 15px;
        font-size: 1.4rem;
      }
      .vod-hero-banner {
        padding: 0 15px;
        height: 45vh;
        min-height: 300px;
        margin-top: -115px; /* Estende lo sfondo sotto la navbar mobile */
        padding-top: 115px;
      }
      .vod-hero-title {
        font-size: 2rem;
      }
      .vod-hero-desc {
        font-size: 0.9rem;
        -webkit-line-clamp: 2;
      }
      .vod-modal-content {
        flex-direction: column;
        max-height: 90vh;
      }
      .vod-modal-poster {
        width: 100%;
        border-right: none;
        border-bottom: none;
        padding: 1.5rem;
      }
      .vod-modal-poster.is-landscape,
      .vod-modal-poster.is-square {
        width: 100%;
      }
      .vod-modal-poster img {
        max-height: 220px;
      }
      .vod-modal-poster.is-portrait img {
        max-width: 130px;
      }
      .vod-modal-poster.is-landscape img {
        max-width: 260px;
      }
      .vod-modal-poster.is-square img {
        max-width: 170px;
      }
      .vod-modal-info {
        padding: 1.5rem;
      }
      .vod-catalog-header {
        padding: 15px 15px 0 15px;
        gap: 1rem;
      }
      .vod-filter-bar {
        padding: 0.8rem;
        gap: 0.8rem;
        border-radius: 12px;
      }
      .filter-group {
        min-width: 120px;
      }
      .vod-catalog-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        padding: 15px;
      }
      /* Player Overlay Mobile adjustments */
      .vod-player-close {
        top: 15px;
        left: 15px;
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
      }
      .vod-player-fullscreen {
        bottom: 10px;
        right: 15px;
        top: auto;
        width: 32px;
        height: 32px;
        font-size: 0.95rem;
      }
      .vod-player-next-ep {
        top: 15px;
        right: 15px;
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
      }
      .vod-player-title-header {
        top: 15px;
        width: 65%;
      }
      #vod-player-title {
        font-size: 1.15rem;
      }
      #vod-player-subtitle {
        font-size: 0.75rem;
      }
    }

    @media (min-width: 769px) and (max-width: 1024px) {
      .vod-navbar {
        padding: 0 20px;
      }
      .vod-brand-text {
        font-size: 1.1rem;
      }
      .vod-brand-sub {
        display: none;
      }
      .vod-navbar .nav-link {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
      }
      .vod-navbar .nav-search {
        width: 180px;
      }
      .vod-navbar .nav-search:focus-within {
        width: 220px;
      }
      .vod-catalog-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 1.2rem;
      }
    }
  </style>
</head>
<body class="dashboard-body">
  <div class="vod-page-layout">
    
    <!-- Navbar Superiore Stile Netflix -->
    <header class="vod-navbar">
      <div class="vod-brand" onclick="window.location.reload()">
        <div class="vod-brand-icon"><i class="ph-fill ph-play"></i></div>
        <div class="vod-brand-text">PZ<span class="brand-num">8</span><span class="brand-sub">VOD</span></div>
      </div>
      <nav class="vod-nav-links">
        <div class="nav-link active" id="nav-item-home" onclick="changeSection('home')"><i class="ph ph-house"></i> Home</div>
        <div class="nav-link" id="nav-item-movies" onclick="changeSection('movies')"><i class="ph ph-film-strip"></i> Film</div>
        <div class="nav-link" id="nav-item-tv" onclick="changeSection('tv')"><i class="ph ph-television"></i> Serie TV</div>
        <div class="nav-link" id="nav-item-catalog" onclick="changeSection('catalog')"><i class="ph ph-folder-open"></i> Catalogo</div>
        <div class="nav-link" id="nav-item-library" onclick="changeSection('library')"><i class="ph ph-heart"></i> Libreria</div>
      </nav>
      <div class="nav-search">
        <input type="text" id="vod-search-input" placeholder="Cerca film o serie tv..." autocomplete="off">
        <div class="vod-search-icon-wrapper">
          <i class="ph ph-magnifying-glass search-icon"></i>
          <div class="clear-icon" id="vod-search-clear"><i class="ph ph-x"></i></div>
        </div>
        <div class="vod-search-dropdown" id="vod-search-dropdown"></div>
      </div>
      <a href="index.php" class="vod-back-btn" title="Torna alla pagina precedente"><i class="ph ph-arrow-left"></i> Indietro</a>
    </header>

    <!-- Main Content (Vertical Scroll) -->
    <main class="dash-main" id="dash-main">
      
      <!-- Hero Banner -->
      <div class="vod-hero-banner" id="vod-hero-banner" style="display: none;">
        <div class="vod-hero-bg">
          <img id="vod-hero-backdrop" src="" alt="Backdrop">
          <div class="vod-hero-overlay-horizontal"></div>
          <div class="vod-hero-overlay-vertical"></div>
        </div>
        <div class="vod-hero-content">
          <div class="vod-hero-meta">
            <span class="vod-meta-badge rating" id="vod-hero-rating"><i class="ph-fill ph-star"></i> N/A</span>
            <span class="vod-meta-badge" id="vod-hero-year"><i class="ph ph-calendar"></i> N/A</span>
            <span class="vod-hero-type-badge" id="vod-hero-type">Film</span>
          </div>
          <h1 class="vod-hero-title" id="vod-hero-title">Titolo in Evidenza</h1>
          <p class="vod-hero-desc" id="vod-hero-desc">Descrizione del film o serie TV...</p>
          <div class="vod-hero-buttons">
            <button class="vod-hero-btn play" id="vod-hero-play-btn"><i class="ph-fill ph-play"></i> Guarda Ora</button>
            <button class="vod-hero-btn info" id="vod-hero-info-btn"><i class="ph ph-info"></i> Dettagli</button>
            <button class="vod-hero-btn info" id="vod-hero-fav-btn" style="padding: 0.75rem 1rem;"><i class="ph ph-heart" style="font-size: 1.2rem; color: var(--danger);"></i></button>
          </div>
        </div>
      </div>

      <!-- Container Continua a Guardare -->
      <div id="vod-continue-container" style="display: none; margin-bottom: 2rem;"></div>

      <!-- Container Home (Righe Netflix) -->
      <div id="vod-home-container">
        <!-- Righe generate via JS -->
      </div>

      <!-- Container Film (Righe Netflix Film) -->
      <div id="vod-movies-container" style="display: none;">
        <!-- Righe generate via JS -->
      </div>

      <!-- Container Serie TV (Righe Netflix Serie TV) -->
      <div id="vod-tv-container" style="display: none;">
        <!-- Righe generate via JS -->
      </div>

      <!-- Container Ricerca -->
      <div id="vod-search-container" style="display: none;">
        <h2 class="vod-search-section-title" id="vod-search-title">Risultati della Ricerca</h2>
        <div class="vod-grid" id="vod-search-grid"></div>
      </div>

      <!-- Container Catalogo Infinito -->
      <div id="vod-catalog-container" style="display: none;">
        <div class="vod-catalog-header">
          <h2 class="vod-search-section-title" style="padding: 0;"><i class="ph-fill ph-folder-open" style="color: var(--accent);"></i> Catalogo Completo</h2>
          
          <!-- Filtri Catalogo -->
          <div class="vod-filter-bar">
            <div class="filter-group">
              <label for="filter-type">Tipo</label>
              <select id="filter-type">
                <option value="all">Tutti</option>
                <option value="movie">Film</option>
                <option value="tv">Serie TV</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="filter-genre">Genere</label>
              <select id="filter-genre">
                <option value="">Tutti i generi</option>
                <!-- Popolato dinamicamente da TMDB via JS -->
              </select>
            </div>
            
            <div class="filter-group">
              <label for="filter-year">Anno</label>
              <select id="filter-year">
                <option value="">Qualsiasi anno</option>
                <option value="2026">2026</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
                <option value="2022">2022</option>
                <option value="2021">2021</option>
                <option value="2020">2020</option>
                <option value="2019">2019</option>
                <option value="2018">2018</option>
                <option value="2017">2017</option>
                <option value="2016">2016</option>
                <option value="2015">2015</option>
                <option value="2010">2010</option>
                <option value="2005">2005</option>
                <option value="2000">2000</option>
                <option value="1995">1995</option>
                <option value="1990">1990</option>
                <option value="1980">1980</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="filter-lang">Lingua Orig.</label>
              <select id="filter-lang">
                <option value="">Tutte</option>
                <option value="it">Italiano</option>
                <option value="en">Inglese</option>
                <option value="es">Spagnolo</option>
                <option value="fr">Francese</option>
                <option value="de">Tedesco</option>
                <option value="ja">Giapponese</option>
                <option value="ko">Coreano</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="filter-vote">Voto Minimo</label>
              <select id="filter-vote">
                <option value="">Qualsiasi</option>
                <option value="8">8+ (Eccezionale)</option>
                <option value="7">7+ (Ottimo)</option>
                <option value="6">6+ (Buono)</option>
                <option value="5">5+ (Sufficiente)</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="filter-sort">Ordina Per</label>
              <select id="filter-sort">
                <option value="popularity.desc">Popolarità</option>
                <option value="vote_average.desc">Più Votati</option>
                <option value="primary_release_date.desc">Più Recenti</option>
              </select>
            </div>

            <div class="filter-group submit-group" style="flex: 0; min-width: 130px; justify-content: flex-end;">
              <label style="display: block;">&nbsp;</label>
              <button class="vod-filter-btn" onclick="resetCatalogAndLoad()">
                <i class="ph ph-magnifying-glass"></i> Cerca
              </button>
            </div>
          </div>
        </div>
        
        <div class="vod-catalog-grid" id="vod-catalog-grid"></div>
        <div id="vod-catalog-loading-indicator" style="text-align: center; padding: 2rem 0; display: none;">
          <div class="vod-loading">Caricamento altri contenuti...</div>
        </div>
      </div>

      <!-- Container Libreria -->
      <div id="vod-library-container" style="display: none;">
        <h2 class="vod-search-section-title"><i class="ph-fill ph-heart" style="color: var(--danger);"></i> La Mia Libreria</h2>
        <div class="vod-grid" id="vod-library-grid"></div>
        
        <!-- Stato Vuoto Libreria -->
        <div class="dash-empty-state" id="vod-library-empty" style="display: none; padding: 6rem 1rem;">
          <i class="ph ph-heart-break" style="color: var(--danger); font-size: 3.5rem; opacity: 0.8;"></i>
          <div class="dash-empty-title" style="font-family: var(--font-main); font-size: 1.3rem; font-weight: 800; color: #fff; margin-top: 10px;">La tua Libreria è vuota</div>
          <div class="dash-empty-hint" style="color: var(--text-muted); font-size: 0.95rem; margin-top: 5px;">Aggiungi film e serie TV ai preferiti per ritrovarli rapidamente qui.</div>
        </div>
      </div>
      
    </main>
  </div>

  <!-- MODAL VOD -->
  <div class="vod-modal" id="vod-modal">
    <div class="vod-modal-content">
      <div class="vod-modal-poster">
        <img id="vod-modal-img" src="" alt="Poster">
      </div>
      <div class="vod-modal-info">
        <div class="vod-modal-topbar">
          <button class="vod-modal-close" onclick="closeVodModal()" aria-label="Chiudi dettagli"><i class="ph ph-arrow-left"></i></button>
        </div>
        <h2 id="vod-modal-title">Titolo</h2>
        <div id="vod-modal-tagline"></div>
        
        <div class="vod-modal-meta-row">
          <span class="vod-meta-badge rating" id="vod-modal-rating"><i class="ph-fill ph-star"></i> N/A</span>
          <span class="vod-meta-badge" id="vod-modal-date"><i class="ph ph-calendar"></i> N/A</span>
          <span class="vod-meta-badge" id="vod-modal-duration"><i class="ph ph-clock"></i> N/A</span>
          <span class="vod-meta-badge" id="vod-modal-status"><i class="ph ph-info"></i> N/A</span>
        </div>

        <div class="vod-modal-genres" id="vod-modal-genres"></div>
        <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
          <button class="vod-hero-btn play" id="vod-modal-play-btn" style="display: none; margin-top: 0; width: fit-content;"><i class="ph-fill ph-play"></i> Guarda Ora</button>
          <button class="vod-hero-btn play" id="vod-modal-resume-btn" style="display: none; margin-top: 0; width: fit-content;"><i class="ph-fill ph-play"></i> Riprendi</button>
          <button class="vod-hero-btn info" id="vod-modal-fav-btn" style="padding: 0.75rem 1.1rem; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.85rem;"><i class="ph ph-heart" style="font-size: 1.1rem; color: var(--danger);"></i> <span>Aggiungi ai Preferiti</span></button>
        </div>

        <p class="vod-modal-desc" id="vod-modal-overview">Caricamento dettagli...</p>

        <!-- Sezione Serie TV: Stagioni ed Episodi -->
        <div id="vod-modal-tv-section" style="display: none; margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 1.5rem;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h3 style="font-family: var(--font-main); font-size: 1.25rem; font-weight: 800; color: #fff; margin: 0; text-transform: uppercase; letter-spacing: -0.5px;">Episodi</h3>
            <select id="vod-season-select">
              <!-- Popolato via JS -->
            </select>
          </div>
          <div id="vod-episodes-list">
            <!-- Popolato via JS -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PLAYER OVERLAY -->
  <div class="vod-player-overlay" id="vod-player-overlay">
    <button class="vod-player-close" onclick="closePlayer()"><i class="ph ph-arrow-left"></i></button>
    <button class="vod-player-fullscreen" id="vod-player-fullscreen-btn" onclick="togglePlayerFullscreen()"><i class="ph ph-corners-out"></i></button>
    <button class="vod-player-next-ep" id="vod-player-next-btn" onclick="playNextEpisode()" style="display: none;"><i class="ph ph-skip-forward"></i></button>
    <div class="vod-player-mouse-tracker" id="vod-player-mouse-tracker"></div>
    <div class="vod-player-title-header" id="vod-player-title-header">
      <h2 id="vod-player-title"></h2>
      <div id="vod-player-subtitle"></div>
    </div>
    <div class="vod-player-wrapper">
      <iframe id="vod-player-frame" src="about:blank" allow="autoplay; fullscreen" allowfullscreen></iframe>
    </div>
  </div>

  <script src="js/vod.js?v=<?= time() ?>"></script>
</body>
</html>
