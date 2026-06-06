<?php
// Avvia sessione e verifica autenticazione
session_start();

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
  <!-- Iniezione CSRF e Preferiti VOD da PHP -->
  <?php
  if (!isset($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $active_profile = $_SESSION['active_profile'] ?? [];
  $vod_favs = $active_profile['vod_favorites'] ?? [];
  ?>
  <script>
    window.__CSRF_TOKEN__ = "<?= $_SESSION['csrf_token'] ?>";
    window.__ACTIVE_PROFILE_VOD_FAVORITES__ = <?= json_encode($vod_favs) ?>;
  </script>
  <style>
    body {
      margin: 0;
      height: 100vh;
      overflow: hidden;
      background: var(--bg-base);
      background-image: radial-gradient(circle at top right, rgba(15, 23, 42, 0.4) 0%, transparent 40%),
                        radial-gradient(circle at bottom left, rgba(2, 6, 23, 0.7) 0%, transparent 40%);
      color: var(--text-primary);
      font-family: var(--font-main);
    }
    
    .vod-page-layout {
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    /* Top Navbar Fissa e Premium */
    .vod-navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 72px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 40px;
      background: linear-gradient(to bottom, rgba(2, 6, 23, 0.95) 0%, rgba(2, 6, 23, 0.8) 25%, rgba(2, 6, 23, 0.4) 60%, rgba(2, 6, 23, 0) 100%);
      border-bottom: 1px solid transparent;
      z-index: 100;
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .vod-navbar.scrolled {
      background: rgba(2, 6, 23, 0.65);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      height: 66px; /* Leggermente ridotta per un effetto dinamico premium */
    }

    .vod-brand {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      cursor: pointer;
    }
    .vod-brand-icon {
      width: 38px;
      height: 38px;
      background: rgba(15, 23, 42, 0.55);
      border: 1.5px solid var(--accent);
      border-radius: 11px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
      color: var(--accent);
      box-shadow: 0 0 12px var(--accent-glow);
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .vod-brand:hover .vod-brand-icon {
      transform: scale(1.08) rotate(8deg);
      background: var(--accent);
      color: #000;
      box-shadow: 0 0 22px var(--accent-glow);
    }
    .vod-brand-text {
      font-size: 1.35rem;
      font-weight: 900;
      letter-spacing: -1px;
      color: #fff;
      display: flex;
      align-items: center;
      text-transform: uppercase;
      font-family: var(--font-main);
    }
    .vod-brand-text span.brand-num {
      color: var(--accent);
      text-shadow: 0 0 10px var(--accent-glow);
    }
    .vod-brand-text span.brand-sub {
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 2px;
      background: var(--accent);
      color: #000 !important;
      padding: 2px 7px;
      border-radius: 5px;
      margin-left: 7px;
      box-shadow: 0 2px 10px var(--accent-glow);
      text-shadow: none;
    }
    
    .vod-nav-links {
      display: flex;
      gap: 10px;
      margin-left: 20px;
      flex: 1;
    }
    
    .vod-navbar .nav-link {
      padding: 0.5rem 1.2rem;
      border-radius: 99px;
      font-size: 0.85rem;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.75);
      white-space: nowrap;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
    }
    .vod-navbar .nav-link:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.12);
    }
    .vod-navbar .nav-link.active {
      color: #000;
      background: var(--accent);
      font-weight: 700;
      border-color: var(--accent);
      box-shadow: 0 4px 15px var(--accent-glow);
    }
    
    /* Barra di Ricerca Premium */
    .vod-navbar .nav-search {
      position: relative;
      width: 240px;
      flex-shrink: 0;
      margin-right: 15px;
      transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .vod-navbar .nav-search:focus-within {
      width: 320px;
    }
    .vod-navbar .nav-search input {
      width: 100%;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border-subtle);
      border-radius: 99px;
      padding: 0.5rem 2.6rem 0.5rem 2.6rem; /* Padding a destra per fare spazio alla X */
      color: var(--text-primary);
      font-size: 0.85rem;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      font-family: var(--font-main);
    }
    .vod-navbar .nav-search input:focus {
      outline: none;
      background: rgba(0, 0, 0, 0.45);
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-glow), inset 0 2px 4px rgba(0,0,0,0.5);
    }
    .vod-navbar .nav-search .search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1rem;
      pointer-events: none;
      transition: var(--transition);
    }
    .vod-navbar .nav-search input:focus ~ .search-icon {
      color: var(--accent);
    }
    .vod-navbar .nav-search .clear-icon {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      cursor: pointer;
      font-size: 1rem;
      transition: var(--transition);
      display: none; /* Gestito via JS */
    }
    .vod-navbar .nav-search .clear-icon:hover {
      color: var(--danger);
      transform: translateY(-50%) scale(1.15);
    }

    /* Bottone Torna Live TV (Fisso a destra) */
    .vod-back-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1.2rem;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 99px;
      color: var(--text-primary);
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      cursor: pointer;
      transition: var(--transition);
      backdrop-filter: blur(10px);
      flex-shrink: 0;
    }
    .vod-back-btn:hover {
      background: #ef4444;
      color: #fff;
      border-color: #ef4444;
      box-shadow: 0 4px 20px rgba(239, 68, 68, 0.45);
      transform: translateY(-2px);
    }
    .vod-back-btn:active {
      transform: translateY(0);
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
      background: linear-gradient(135deg, var(--accent) 0%, #00b0ff 100%);
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
      margin-bottom: 2.5rem;
      padding-left: 40px;
    }
    
    .vod-row-title { 
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text-primary);
      margin-bottom: 15px; 
      display: flex;
      align-items: center;
      gap: 10px;
      letter-spacing: -0.5px;
      text-transform: uppercase;
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
      overflow-x: auto;
      padding: 10px 40px 20px 0;
      scroll-behavior: smooth;
      scrollbar-width: none; /* Firefox */
    }
    .vod-row::-webkit-scrollbar {
      display: none; /* Chrome, Safari, Edge */
    }

    /* Poster Card */
    .vod-card {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      background: var(--bg-surface);
      flex-shrink: 0;
      border: 1px solid rgba(255, 255, 255, 0.05);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
    }
    .vod-card.landscape { width: 280px; aspect-ratio: 16 / 9; }
    .vod-card.portrait { width: 160px; aspect-ratio: 2 / 3; }
    
    .vod-card:hover { 
      transform: scale(1.02) translateY(-2px);
      z-index: 10;
      border-color: rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.55), 0 0 10px var(--accent-glow);
    }
    .vod-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .vod-card:hover img {
      transform: scale(1.015);
    }
    
    .vod-card-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(10, 10, 15, 0.75);
      backdrop-filter: blur(8px);
      padding: 4px 8px;
      border-radius: 6px;
      border: 1px solid rgba(255,255,255,0.1);
      color: #fbbf24;
      font-weight: 800;
      font-size: 0.75rem;
      display: flex;
      align-items: center;
      gap: 4px;
      z-index: 2;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    .vod-card-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(2, 6, 23, 0.95) 0%, rgba(2, 6, 23, 0.3) 50%, transparent 100%);
      padding: 12px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      opacity: 0;
      transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      z-index: 2;
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
    }
    .vod-card:hover .vod-card-overlay {
      opacity: 1;
    }
    .vod-card-title {
      font-weight: 700;
      font-size: 0.85rem;
      color: #fff;
      line-height: 1.25;
      text-align: left;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
      font-family: var(--font-main);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .vod-card-actions {
      display: flex;
      gap: 6px;
      justify-content: flex-start;
      margin-top: 8px;
    }
    
    .vod-card-btn {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: rgba(15, 23, 42, 0.75);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      padding: 0;
    }
    
    .vod-card-btn:hover {
      background: #fff;
      color: #000;
      border-color: #fff;
      transform: scale(1.1);
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
    }
    
    .vod-card-btn.play {
      background: var(--accent);
      color: #000;
      border-color: var(--accent);
    }
    .vod-card-btn.play:hover {
      background: #fff;
      color: #000;
      border-color: #fff;
      box-shadow: 0 0 10px var(--accent-glow);
    }
    
    .vod-card-btn.fav i {
      color: var(--danger);
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
      background: rgba(15, 23, 42, 0.75);
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
    
    .vod-modal-close {
      position: absolute;
      top: 20px;
      right: 20px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: var(--text-secondary);
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.3rem;
      z-index: 10;
      transition: var(--transition);
      backdrop-filter: blur(10px);
    }
    .vod-modal-close:hover {
      background: var(--accent);
      color: #000;
      border-color: var(--accent);
      transform: rotate(90deg) scale(1.05);
      box-shadow: 0 0 15px var(--accent-glow);
    }
    
    .vod-modal-poster {
      width: 300px;
      flex-shrink: 0;
      padding: 2.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.15);
      border-right: 1px solid rgba(255,255,255,0.03);
    }
    .vod-modal-poster img {
      width: 100%;
      border-radius: 12px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.5);
      border: 1px solid rgba(255,255,255,0.05);
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
    .vod-player-close {
      position: absolute;
      top: 20px;
      left: 20px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
      padding: 0.6rem 1.4rem;
      border-radius: 99px;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      cursor: pointer;
      z-index: 10001;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
      backdrop-filter: blur(10px);
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
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border-subtle);
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
      background-color: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
    }
    #vod-season-select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 15px var(--accent-glow);
    }
    #vod-season-select option {
      background: #0f172a;
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

    .vod-episode-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 15px;
      padding: 0.8rem 1.2rem;
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.04);
      border-radius: 12px;
      transition: var(--transition);
      cursor: pointer;
    }
    .vod-episode-row:hover {
      background: rgba(255, 255, 255, 0.06);
      border-color: rgba(255, 255, 255, 0.1);
      transform: translateX(4px);
    }
    .vod-episode-info {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 3px;
      min-width: 0;
    }
    .vod-episode-title {
      font-weight: 700;
      font-size: 0.92rem;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .vod-episode-overview {
      font-size: 0.8rem;
      color: var(--text-muted);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.4;
    }
    .vod-episode-play-btn {
      width: 36px;
      height: 36px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      cursor: pointer;
      transition: var(--transition);
      flex-shrink: 0;
    }
    .vod-episode-row:hover .vod-episode-play-btn {
      background: var(--accent);
      color: #000;
      border-color: var(--accent);
      box-shadow: 0 0 10px var(--accent-glow);
    }

    /* --- RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
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
        padding-left: 15px;
        margin-bottom: 1.5rem;
      }
      .vod-row-title {
        font-size: 1.1rem;
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
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 1.5rem;
      }
      .vod-modal-poster img {
        max-width: 130px;
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
<body>
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
        <i class="ph ph-magnifying-glass search-icon"></i>
        <input type="text" id="vod-search-input" placeholder="Cerca film o serie tv...">
        <i class="ph ph-x clear-icon" id="vod-search-clear"></i>
      </div>
      <a href="index.php" class="vod-back-btn"><i class="ph-bold ph-monitor-play"></i> Live TV</a>
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
              <select id="filter-type" onchange="resetCatalogAndLoad()">
                <option value="all">Tutti</option>
                <option value="movie">Film</option>
                <option value="tv">Serie TV</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="filter-genre">Genere</label>
              <select id="filter-genre" onchange="resetCatalogAndLoad()">
                <option value="">Tutti i generi</option>
                <option value="action">Azione & Avventura</option>
                <option value="comedy">Commedia</option>
                <option value="drama">Dramma</option>
                <option value="scifi">Fantascienza</option>
                <option value="horror">Horror</option>
                <option value="thriller">Thriller & Mistero</option>
                <option value="romance">Romantico</option>
                <option value="animation">Animazione</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="filter-year">Anno</label>
              <select id="filter-year" onchange="resetCatalogAndLoad()">
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
              <label for="filter-sort">Ordina Per</label>
              <select id="filter-sort" onchange="resetCatalogAndLoad()">
                <option value="popularity.desc">Popolarità</option>
                <option value="vote_average.desc">Più Votati</option>
                <option value="primary_release_date.desc">Più Recenti</option>
              </select>
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
      <button class="vod-modal-close" onclick="closeVodModal()"><i class="ph ph-x"></i></button>
      <div class="vod-modal-poster">
        <img id="vod-modal-img" src="" alt="Poster">
      </div>
      <div class="vod-modal-info">
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
    <button class="vod-player-close" onclick="closePlayer()"><i class="ph ph-arrow-left"></i> Torna ai Dettagli</button>
    <div class="vod-player-wrapper">
      <iframe id="vod-player-frame" src="about:blank" allow="autoplay; fullscreen" allowfullscreen></iframe>
    </div>
  </div>

  <script src="js/vod.js?v=<?= time() ?>"></script>
</body>
</html>
