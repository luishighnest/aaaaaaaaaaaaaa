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
    /* --- PREMIUM ANIMATION FRAMEWORK --- */
    :root {
      --anim-curve: cubic-bezier(0.16, 1, 0.3, 1);
      --anim-fast: 0.2s;
      --anim-base: 0.4s;
    }

    /* GPU Accelerated Transitions */
    .vod-smooth {
      transition-property: transform, opacity;
      transition-duration: var(--anim-base);
      transition-timing-function: var(--anim-curve);
      will-change: transform, opacity;
    }

    .vod-smooth-fast {
      transition-property: transform, opacity;
      transition-duration: var(--anim-fast);
      transition-timing-function: var(--anim-curve);
      will-change: transform, opacity;
    }
    
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
      /* Eredita .vod-smooth, aggiungendo solo le proprietà specifiche che cambiano */
      transition-property: transform, opacity, top, background, border-radius, height;
    }
    
    .vod-navbar.vod-smooth {
       transition-duration: var(--anim-base);
       transition-timing-function: var(--anim-curve);
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
      transform: translateY(-120%) !important;
      opacity: 0 !important;
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
    
    /* ── BOTTONI HERO & MODAL – REDESIGN ── */
    .vod-hero-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 0 1.8rem;
      height: 48px;
      border-radius: 6px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      text-transform: none;
      letter-spacing: 0;
      position: relative;
      overflow: hidden;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .vod-hero-btn::after { display: none; }

    .vod-hero-btn i {
      font-size: 1.35rem;
      flex-shrink: 0;
      transition: transform 0.2s ease;
    }
    .vod-hero-btn:hover i { transform: scale(1.08); }
    .vod-hero-btn:active { transform: scale(0.97); opacity: 0.9; }

    /* PLAY – bianco Netflix */
    .vod-hero-btn.play {
      background: var(--accent);
      color: #000;
      font-weight: 800;
      box-shadow: none;
      letter-spacing: -0.2px;
    }
    .vod-hero-btn.play:hover {
      background: color-mix(in srgb, var(--accent) 85%, #fff 15%);
      transform: none;
      box-shadow: none;
    }

    /* RESUME – stesso stile play ma più contenuto, con barra progresso */
    #vod-modal-resume-btn,
    #vod-hero-resume-btn {
      background: rgba(255,255,255,0.18);
      color: #fff;
      font-weight: 700;
      position: relative;
      overflow: hidden;
    }
    #vod-modal-resume-btn::before,
    #vod-hero-resume-btn::before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      height: 3px;
      width: 40%;
      background: var(--accent);
      border-radius: 0 2px 2px 0;
    }
    #vod-modal-resume-btn:hover,
    #vod-hero-resume-btn:hover {
      background: rgba(255,255,255,0.26);
    }

    /* INFO – secondario (Dettagli) */
    .vod-hero-btn.info {
      background: rgba(255,255,255,0.07);
      border: 1.5px solid rgba(255,255,255,0.18);
      color: #fff;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }
    .vod-hero-btn.info:hover {
      background: rgba(255,255,255,0.13);
      border-color: rgba(255,255,255,0.35);
      transform: translateY(-2px);
    }

    /* FAV ROUND – bottone +/✓ a cerchio (hero) */
    .vod-hero-btn.fav-round {
      width: 46px;
      height: 46px;
      padding: 0;
      border-radius: 50%;
      background: rgba(255,255,255,0.07);
      border: 1.5px solid rgba(255,255,255,0.18);
      color: #fff;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      justify-content: center;
    }
    .vod-hero-btn.fav-round i {
      font-size: 1.3rem;
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.25s;
    }
    .vod-hero-btn.fav-round:hover {
      background: rgba(255,255,255,0.14);
      border-color: rgba(255,255,255,0.35);
      transform: translateY(-2px);
    }
    .vod-hero-btn.fav-round.is-fav {
      background: rgba(0, 242, 254, 0.12);
      border-color: rgba(255, 255, 255, 0.7);
      box-shadow: 0 0 22px var(--accent-glow), inset 0 0 10px rgba(0,242,254,0.06);
    }
    .vod-hero-btn.fav-round.is-fav i {
      color: var(--accent);
      filter: drop-shadow(0 0 6px var(--accent-glow));
    }
    .vod-hero-btn.fav-round.is-fav:hover {
      background: rgba(0, 242, 254, 0.2);
      box-shadow: 0 0 32px var(--accent-glow), 0 0 0 3px rgba(0,242,254,0.15);
      transform: translateY(-2px);
    }
    .vod-hero-btn.fav-round::after { display: none; }

    /* ── ANIMAZIONE POP al toggle preferito ── */
    @keyframes fav-pop {
      0%   { transform: scale(1); }
      35%  { transform: scale(1.35); }
      65%  { transform: scale(0.85); }
      100% { transform: scale(1); }
    }
    .fav-pop i { animation: fav-pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }

    /* ── BOTTONI MODAL ── */
    .vod-modal-action-row {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-top: 10px;
      flex-wrap: wrap;
    }

    /* Bottone fav nel modal: solo icona + (o ✓) con testo */
    .vod-modal-fav-btn-new {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      height: 46px;
      padding: 0 1.3rem;
      border-radius: 14px;
      font-size: 0.82rem;
      font-weight: 800;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      cursor: pointer;
      border: 1.5px solid rgba(255,255,255,0.18);
      background: rgba(255,255,255,0.07);
      color: #fff;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
      white-space: nowrap;
      position: relative;
      overflow: hidden;
    }
    .vod-modal-fav-btn-new::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(105deg, transparent 30%, rgba(255,255,255,0.18) 50%, transparent 70%);
      transform: translateX(-100%);
      transition: transform 0.55s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: none;
    }
    .vod-modal-fav-btn-new:hover::after { transform: translateX(100%); }
    .vod-modal-fav-btn-new i {
      font-size: 1.15rem;
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.25s;
    }
    .vod-modal-fav-btn-new:hover {
      background: rgba(255,255,255,0.13);
      border-color: rgba(255,255,255,0.35);
      transform: translateY(-2px);
    }
    .vod-modal-fav-btn-new:active { transform: scale(0.96); }
    .vod-modal-fav-btn-new.is-fav {
      background: rgba(0, 242, 254, 0.1);
      border-color: rgba(255, 255, 255, 0.7);
      color: var(--accent);
      box-shadow: 0 0 20px var(--accent-glow), inset 0 0 8px rgba(0,242,254,0.05);
    }
    .vod-modal-fav-btn-new.is-fav i {
      color: var(--accent);
      transform: scale(1.1);
      filter: drop-shadow(0 0 5px var(--accent-glow));
    }
    .vod-modal-fav-btn-new.is-fav:hover {
      background: rgba(0, 242, 254, 0.18);
      border-color: rgba(0, 242, 254, 0.7);
      box-shadow: 0 0 28px var(--accent-glow), 0 0 0 3px rgba(0,242,254,0.12);
      transform: translateY(-2px);
    }

    /* ── CARD BUTTONS REDESIGN ── */
    .vod-card-btn {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: rgba(8, 10, 20, 0.55);
      border: 1.5px solid rgba(255,255,255,0.2);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      cursor: pointer;
      transition: background 0.25s ease, border-color 0.25s ease,
                  box-shadow 0.25s ease, transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1),
                  color 0.2s ease;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      flex-shrink: 0;
      outline: none;
    }
    .vod-card-btn:active { transform: scale(0.9) !important; }

    /* ─ PLAY: bianco solido → accent al hover ─ */
    .vod-card-btn.play {
      background: rgba(255,255,255,0.92);
      border-color: transparent;
      color: #000;
      font-size: 0.95rem;
      box-shadow: 0 2px 14px rgba(0,0,0,0.45);
    }
    .vod-card-btn.play:hover {
      background: var(--accent);
      color: #000;
      border-color: transparent;
      box-shadow: 0 0 22px var(--accent-glow), 0 4px 14px rgba(0,0,0,0.4);
      transform: scale(1.18) translateY(-2px);
    }

    /* ─ INFO: accento blu/teal con bordo luminoso al hover ─ */
    .vod-card-btn.info {
      font-size: 0.95rem;
    }
    .vod-card-btn.info:hover {
      background: rgba(255,255,255,0.12);
      border-color: rgba(255, 255, 255, 0.7);
      color: #fff;
      box-shadow: 0 0 14px rgba(255,255,255,0.15);
      transform: scale(1.18) translateY(-2px);
    }
    .vod-card-btn.info i { transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
    .vod-card-btn.info:hover i { transform: rotate(15deg) scale(1.1); }

    /* ─ FAV: + → ✓ con glow accent ─ */
    .vod-card-btn.fav {
      font-size: 1.05rem;
      font-weight: 900;
    }
    .vod-card-btn.fav:hover {
      background: rgba(255,255,255,0.12);
      border-color: rgba(255,255,255,0.45);
      color: #fff;
      transform: scale(1.18) translateY(-2px);
    }
    .vod-card-btn.fav.is-fav {
      background: rgba(0, 242, 254, 0.14);
      border-color: rgba(255, 255, 255, 0.7);
      color: var(--accent);
      box-shadow: 0 0 14px var(--accent-glow), inset 0 0 6px rgba(0,242,254,0.06);
    }
    .vod-card-btn.fav.is-fav i {
      filter: drop-shadow(0 0 4px var(--accent-glow));
    }
    .vod-card-btn.fav.is-fav:hover {
      background: rgba(0, 242, 254, 0.24);
      border-color: rgba(0, 242, 254, 0.85);
      box-shadow: 0 0 22px var(--accent-glow), 0 0 0 3px rgba(0,242,254,0.12);
      transform: scale(1.18) translateY(-2px);
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

    /* Skeleton loader (shimmer) */
    @keyframes vod-skeleton-shimmer {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }

    .vod-card-skeleton {
      pointer-events: none;
      cursor: default;
      border-color: rgba(255, 255, 255, 0.04);
      box-shadow: none;
      background: rgba(15, 23, 42, 0.55);
      overflow: hidden;
    }

    .vod-card-skeleton::before {
      display: none;
    }

    .vod-card-skeleton .vod-skeleton-shine {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.02) 0%,
        rgba(255, 255, 255, 0.09) 45%,
        rgba(255, 255, 255, 0.02) 100%
      );
      background-size: 200% 100%;
      animation: vod-skeleton-shimmer 1.5s ease-in-out infinite;
    }

    .vod-card-skeleton:nth-child(2) .vod-skeleton-shine { animation-delay: 0.12s; }
    .vod-card-skeleton:nth-child(3) .vod-skeleton-shine { animation-delay: 0.24s; }
    .vod-card-skeleton:nth-child(4) .vod-skeleton-shine { animation-delay: 0.36s; }
    .vod-card-skeleton:nth-child(5) .vod-skeleton-shine { animation-delay: 0.48s; }

    .vod-card-skeleton:hover {
      transform: none;
      z-index: auto;
      border-color: rgba(255, 255, 255, 0.04);
      box-shadow: none;
    }

    @media (prefers-reduced-motion: reduce) {
      .vod-card-skeleton .vod-skeleton-shine {
        animation: none;
        background: rgba(255, 255, 255, 0.05);
      }
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
      inset: 0;
      z-index: 9999;
      display: block;
      overflow-y: hidden;
      padding: 0;
      box-sizing: border-box;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease;
    }
    .vod-modal::before {
      content: '';
      position: fixed;
      inset: 0;
      background: rgba(2, 6, 23, 0.75);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      z-index: -1;
      pointer-events: none;
    }
    .vod-modal.open {
      opacity: 1;
      pointer-events: auto;
    }
    
    .vod-modal-content {
      background: rgba(2, 6, 23, 0.96);
      backdrop-filter: blur(30px);
      -webkit-backdrop-filter: blur(30px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 20px;
      width: 100%;
      max-width: 900px;
      margin: 5vh auto;
      position: relative;
      transform: scale(0.95) translateY(20px);
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      max-height: 85vh;
      box-shadow: 0 30px 80px rgba(0,0,0,0.7);
      display: flex;
      flex-direction: column;
      overflow: hidden; /* Gestiremo lo scroll internamente */
    }

    .vod-modal-inner {
      display: flex;
      flex-direction: row;
      gap: 2rem;
      padding: 2rem;
      height: 100%;
      overflow: hidden;
    }

    @media (max-width: 768px) {
      .vod-modal-inner {
        flex-direction: column;
        padding: 1.5rem;
        overflow-y: auto;
      }
    }

    .vod-modal-artwork {
      width: 35%;
      flex-shrink: 0;
      position: sticky;
      top: 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      height: 100%;
    }

    .vod-modal-artwork img {
      width: 100%;
      border-radius: 12px;
      object-fit: contain;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    @media (max-width: 768px) {
      .vod-modal-artwork {
        width: 100%;
        position: static;
      }
      .vod-modal-artwork img {
        aspect-ratio: 16/9;
        object-fit: cover;
      }
    }

    .vod-modal-details {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      overflow-y: auto;
      padding-right: 10px; /* Spazio per evitare che scrollbar sia troppo attaccata */
    }

    /* Hide scrollbar for Chrome/Safari/Opera */
    .vod-modal-details::-webkit-scrollbar {
      display: none;
    }
    
    /* Hide scrollbar for IE, Edge and Firefox */
    .vod-modal-details {
      -ms-overflow-style: none;  /* IE and Edge */
      scrollbar-width: none;  /* Firefox */
    }

    /* ── INFO BODY (scrollabile) ── */
    .vod-modal-topbar {
      display: none;
    }
    .vod-modal-info {
      padding: 1.5rem 1.8rem 1.8rem 1.8rem;
      display: flex;
      flex-direction: column;
      gap: 1.1rem;
    }
    .vod-modal-info::-webkit-scrollbar { display: none; }

    .vod-modal-close {
      position: absolute;
      top: 14px;
      right: 14px;
      z-index: 20;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(20, 20, 30, 0.9);
      border: none;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.6);
      transition: background 0.18s ease, transform 0.18s ease;
    }
    .vod-modal-close i {
      transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
      line-height: 1;
    }
    .vod-modal-close:hover {
      background: rgba(60, 60, 80, 0.95);
      transform: scale(1.1);
    }
    .vod-modal-close:hover i {
      transform: rotate(90deg);
    }
    .vod-modal-close:active {
      transform: scale(0.92);
    }

    /* ── POSTER (vecchio, rimosso dal layout ma tenuto per compatibilità JS) ── */
    .vod-modal-poster { display: none; }

    /* ── META ── */
    #vod-modal-title {
      font-family: var(--font-main);
      font-size: 1.85rem;
      margin: 0;
      line-height: 1.1;
      color: #fff;
      font-weight: 900;
      letter-spacing: -0.8px;
    }
    #vod-modal-tagline {
      font-style: italic;
      color: var(--accent);
      font-size: 0.88rem;
      font-weight: 500;
      opacity: 0.9;
    }
    .vod-modal-meta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-top: 6px;
    }
    .vod-meta-badge {
      background: rgba(255,255,255,0.06);
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.76rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      border: 1px solid rgba(255,255,255,0.08);
      color: var(--text-secondary);
      font-family: var(--font-alt);
    }
    .vod-meta-badge.rating {
      color: #fbbf24;
      background: rgba(251,191,36,0.12);
      border-color: rgba(251,191,36,0.25);
    }
    .vod-modal-desc {
      font-size: 0.93rem;
      line-height: 1.65;
      color: var(--text-secondary);
      margin: 0;
    }
    .vod-modal-genres {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
    }
    .vod-genre-tag {
      background: rgba(255,255,255,0.04);
      padding: 4px 11px;
      border-radius: 7px;
      font-size: 0.76rem;
      color: var(--text-secondary);
      border: 1px solid rgba(255,255,255,0.07);
      font-weight: 600;
      transition: background 0.2s, border-color 0.2s;
    }
    .vod-genre-tag:hover {
      background: rgba(255,255,255,0.09);
      border-color: rgba(255,255,255,0.18);
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
    .vod-player-next-ep,
    .vod-player-info-btn {
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
      bottom: 8px;
      right: 25px;
      width: 44px;
      height: 44px
      font-size: 1.3rem;
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
    .vod-player-info-btn {
      top: 20px;
      right: 74px;
    }
    .vod-player-info-btn:hover {
      background: var(--accent);
      border-color: var(--accent);
      box-shadow: 0 4px 20px var(--accent-glow);
      transform: translateY(-2px);
    }
    .vod-player-info-btn:active {
      transform: translateY(0);
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
    .vod-player-overlay.controls-hidden .vod-player-info-btn,
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
      transition: background 0.25s ease, border-color 0.25s ease, opacity 0.25s ease, box-shadow 0.25s ease, transform 0.2s ease;
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
        bottom: 6px;
        right: 35px;
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

    /* Skeleton Loader Cards */
    .vod-card-skeleton {
      background: rgba(255,255,255,0.03);
      border-radius: 10px;
      overflow: hidden;
      position: relative;
      min-height: 200px;
    }
    .vod-card-skeleton.landscape {
      min-height: 140px;
    }
    .vod-skeleton-shine {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.04) 50%, transparent 100%);
      animation: skeletonShine 1.5s infinite;
    }
    @keyframes skeletonShine {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }

    /* Hero Carousel transition */
    .vod-hero-content {
      transition: opacity 0.3s ease, transform 0.3s ease;
    }

    /* Next Episode Countdown Overlay */
    #vod-next-ep-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.85);
      z-index: 100000;
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.3s ease;
    }
    .vod-next-ep-content {
      text-align: center;
      padding: 2.5rem;
      border-radius: 16px;
      background: rgba(20,20,30,0.95);
      border: 1px solid rgba(255,255,255,0.08);
      backdrop-filter: blur(20px);
      max-width: 400px;
      width: 90%;
    }
    .vod-next-ep-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: #fff;
      margin-bottom: 0.5rem;
    }
    .vod-next-ep-title span {
      color: var(--accent);
      font-size: 1.8rem;
      font-weight: 900;
    }
    .vod-next-ep-info {
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-bottom: 1.5rem;
    }
    .vod-next-ep-actions {
      display: flex;
      gap: 0.75rem;
      justify-content: center;
    }
    .vod-next-ep-play {
      background: var(--accent);
      color: #fff;
      border: none;
      padding: 0.65rem 1.5rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.9rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      transition: transform 0.2s, background 0.2s;
    }
    .vod-next-ep-play:hover {
      transform: scale(1.05);
      background: var(--accent-hover);
    }
    .vod-next-ep-cancel {
      background: rgba(255,255,255,0.08);
      color: var(--text-muted);
      border: 1px solid rgba(255,255,255,0.1);
      padding: 0.65rem 1.2rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.3rem;
      transition: background 0.2s;
    }
    .vod-next-ep-cancel:hover {
      background: rgba(255,255,255,0.12);
    }
    /* Player Info Panel */
    #vod-player-info-panel {
      display: none;
      position: absolute;
      top: 70px;
      right: 20px;
      width: 340px;
      max-width: 90vw;
      background: rgba(12, 12, 20, 0.95);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
      padding: 1.4rem;
      z-index: 10002;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      box-shadow: 0 8px 40px rgba(0,0,0,0.6);
      animation: fadeIn 0.2s ease;
    }
    #vod-player-info-panel.open {
      display: block;
    }
    .vod-player-info-title {
      font-size: 1.1rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 0.4rem;
    }
    .vod-player-info-sub {
      font-size: 0.85rem;
      color: var(--accent);
      font-weight: 600;
      margin-bottom: 0.8rem;
    }
    .vod-player-info-meta {
      display: flex;
      gap: 0.6rem;
      flex-wrap: wrap;
      margin-bottom: 0.8rem;
    }
    .vod-player-info-meta span {
      font-size: 0.75rem;
      color: var(--text-muted);
      background: rgba(255,255,255,0.06);
      padding: 3px 8px;
      border-radius: 6px;
    }
    .vod-player-info-desc {
      font-size: 0.8rem;
      color: var(--text-secondary);
      line-height: 1.5;
      max-height: 120px;
      overflow-y: auto;
    }
    .vod-player-info-desc::-webkit-scrollbar {
      width: 4px;
    }
    .vod-player-info-desc::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.15);
      border-radius: 2px;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
  </style>
</head>
<body class="dashboard-body">
  <div class="vod-page-layout">
    
    <!-- Navbar Superiore Stile Netflix -->
    <header class="vod-navbar vod-smooth">
      <div class="vod-brand" onclick="window.location.reload()">
        <div class="vod-brand-icon"><i class="ph-fill ph-play"></i></div>
        <div class="vod-brand-text">PZ<span class="brand-num">8</span><span class="brand-sub">VOD</span></div>
      </div>
      <nav class="vod-nav-links">
        <div class="nav-link active" id="nav-item-home" onclick="changeSection('home')"><i class="ph ph-house"></i> Home</div>
        <div class="nav-link" id="nav-item-movies" onclick="changeSection('movies')"><i class="ph ph-film-strip"></i> Film</div>
        <div class="nav-link" id="nav-item-tv" onclick="changeSection('tv')"><i class="ph ph-television"></i> Serie TV</div>
        <div class="nav-link" id="nav-item-catalog" onclick="changeSection('catalog')"><i class="ph ph-folder-open"></i> Catalogo</div>
        <div class="nav-link" id="nav-item-library" onclick="changeSection('library')"><i class="ph ph-plus-circle"></i> Libreria</div>
      </nav>
      <div class="nav-search">
        <input type="text" id="vod-search-input" placeholder="Cerca film o serie tv..." autocomplete="off">
        <div class="vod-search-icon-wrapper">
          <i class="ph ph-magnifying-glass search-icon"></i>
          <div class="clear-icon" id="vod-search-clear"><i class="ph ph-x"></i></div>
        </div>
        <div class="vod-search-dropdown" id="vod-search-dropdown"></div>
      </div>
      <a href="index.php" class="vod-back-btn" title="Torna alla Home"><i class="ph ph-house"></i> Torna alla Home</a>
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
            <button class="vod-hero-btn fav-round" id="vod-hero-fav-btn" title="Aggiungi ai Preferiti"><i class="ph ph-plus-circle"></i></button>
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
        <h2 class="vod-search-section-title"><i class="ph-fill ph-plus-circle" style="color: var(--accent);"></i> La Mia Libreria</h2>
        <div class="vod-grid" id="vod-library-grid"></div>
        
        <!-- Stato Vuoto Libreria -->
        <div class="dash-empty-state" id="vod-library-empty" style="display: none; padding: 6rem 1rem;">
          <i class="ph ph-book-bookmark" style="color: var(--accent); font-size: 3.5rem; opacity: 0.8;"></i>
          <div class="dash-empty-title" style="font-family: var(--font-main); font-size: 1.3rem; font-weight: 800; color: #fff; margin-top: 10px;">La tua Libreria è vuota</div>
          <div class="dash-empty-hint" style="color: var(--text-muted); font-size: 0.95rem; margin-top: 5px;">Aggiungi film e serie TV ai preferiti per ritrovarli rapidamente qui.</div>
        </div>
      </div>
      
    </main>
  </div>

  <!-- MODAL VOD -->
  <div class="vod-modal" id="vod-modal">
    <div class="vod-modal-content">
      <button class="vod-modal-close" onclick="closeVodModal()" aria-label="Chiudi dettagli"><i class="ph ph-x"></i></button>
      <div class="vod-modal-inner">
        <div class="vod-modal-artwork">
          <img id="vod-modal-img" src="" alt="Poster">
        </div>
        <div class="vod-modal-details">
          <h2 id="vod-modal-title">Titolo</h2>
          <div id="vod-modal-tagline"></div>
          <div class="vod-modal-meta-row">
            <span class="vod-meta-badge rating" id="vod-modal-rating"><i class="ph-fill ph-star"></i> N/A</span>
            <span class="vod-meta-badge" id="vod-modal-date"><i class="ph ph-calendar"></i> N/A</span>
            <span class="vod-meta-badge" id="vod-modal-duration"><i class="ph ph-clock"></i> N/A</span>
            <span class="vod-meta-badge" id="vod-modal-status"><i class="ph ph-info"></i> N/A</span>
          </div>
          <p class="vod-modal-desc" id="vod-modal-overview"></p>
          <div class="vod-modal-genres" id="vod-modal-genres"></div>
          <div class="vod-modal-action-row">
            <button class="vod-hero-btn play" id="vod-modal-play-btn"><i class="ph-fill ph-play"></i> Guarda Ora</button>
            <button class="vod-hero-btn" id="vod-modal-resume-btn" style="display:none;"><i class="ph-fill ph-play"></i> Riprendi</button>
            <button class="vod-modal-fav-btn-new" id="vod-modal-fav-btn"><i class="ph ph-plus-circle"></i> Lista</button>
          </div>
          <div id="vod-modal-tv-section" style="display:none; margin-top:1rem;">
            <select id="vod-season-select"></select>
            <div id="vod-episodes-list" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem;"></div>
          </div>
        </div>
      </div>
    </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BODY SCROLLABILE -->
      <div class="vod-modal-info">
        <div class="vod-modal-genres" id="vod-modal-genres" style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;"></div>
        <div class="vod-modal-action-row">
          <button class="vod-hero-btn play" id="vod-modal-play-btn" style="display: none;"><i class="ph-fill ph-play"></i> Guarda Ora</button>
          <button class="vod-hero-btn play" id="vod-modal-resume-btn" style="display: none;"><i class="ph-fill ph-play"></i> Riprendi</button>
          <button class="vod-modal-fav-btn-new" id="vod-modal-fav-btn"><i class="ph ph-plus-circle"></i> <span>Aggiungi</span></button>
        </div>
        <p class="vod-modal-desc" id="vod-modal-overview" style="margin-bottom: 2rem;"></p>

        <!-- Sezione Serie TV: Stagioni ed Episodi -->
        <div id="vod-modal-tv-section" style="display: none; margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 1.5rem;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h3 style="font-family: var(--font-main); font-size: 1.25rem; font-weight: 800; color: #fff; margin: 0; text-transform: uppercase; letter-spacing: -0.5px;">Episodi</h3>
            <select id="vod-season-select" style="background: var(--bg-card); color: #fff; border: 1px solid rgba(255,255,255,0.1); padding: 0.5rem 1rem; border-radius: 8px;">
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
    <button class="vod-player-close" onclick="closePlayer()" title="Torna ai dettagli" style="z-index: 99999;"><i class="ph ph-arrow-left"></i></button>
    <button class="vod-player-fullscreen" id="vod-player-fullscreen-btn" onclick="togglePlayerFullscreen()"><i class="ph ph-corners-out"></i></button>
    <button class="vod-player-info-btn" id="vod-player-info-btn" onclick="togglePlayerInfoPanel()" title="Info"><i class="ph ph-info"></i></button>
    <button class="vod-player-next-ep" id="vod-player-next-btn" onclick="playNextEpisode()" style="display: none;"><i class="ph ph-skip-forward"></i></button>
    <div class="vod-player-mouse-tracker" id="vod-player-mouse-tracker"></div>
    <div class="vod-player-title-header" id="vod-player-title-header">
      <h2 id="vod-player-title"></h2>
      <div id="vod-player-subtitle"></div>
    </div>
    <div id="vod-player-info-panel">
      <div class="vod-player-info-title" id="vod-player-info-panel-title"></div>
      <div class="vod-player-info-sub" id="vod-player-info-panel-sub"></div>
      <div class="vod-player-info-meta" id="vod-player-info-panel-meta"></div>
      <div class="vod-player-info-desc" id="vod-player-info-panel-desc"></div>
    </div>
    <div class="vod-player-wrapper">
      <iframe id="vod-player-frame" src="about:blank" allow="autoplay; fullscreen" allowfullscreen></iframe>
    </div>
  </div>

  <script src="js/vod.js?v=<?= time() ?>"></script>
</body>
</html>
