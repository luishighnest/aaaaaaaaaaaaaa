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
  <link rel="stylesheet" href="css/style.css?v=1.18">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
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
  <style>
    body { margin: 0; height: 100vh; overflow: hidden; background: #050505; }
    
    .vod-page-layout {
      display: flex;
      height: calc(100vh - 70px);
      gap: 20px;
      padding: 20px;
      padding-top: 0;
    }
    .dash-sidebar {
      width: 280px;
      flex-shrink: 0;
      height: 100%;
      background: rgba(15, 23, 42, 0.4);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 24px;
      padding: 20px;
      display: flex;
      flex-direction: column;
    }
    
    /* VOD Main Area */
    .dash-main {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      background: rgba(15, 23, 42, 0.4);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 24px;
      overflow: hidden;
      position: relative;
    }
    .dash-main::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 150px;
      background: linear-gradient(to bottom, rgba(255,255,255,0.03), transparent); pointer-events: none;
    }

    /* Barra di Ricerca Premium */
    .vod-search-bar {
      display: flex; align-items: center;
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 50px; 
      padding: 1rem 1.5rem; 
      margin: 2rem 2rem 1rem 2rem; 
      gap: 1rem;
      transition: all 0.3s ease;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
    }
    .vod-search-bar:focus-within {
      border-color: var(--accent);
      box-shadow: 0 0 20px var(--accent-glow), inset 0 2px 4px rgba(0,0,0,0.5);
      background: rgba(0, 0, 0, 0.5);
    }
    .vod-search-bar i { color: var(--text-muted); font-size: 1.4rem; transition: color 0.3s ease; }
    .vod-search-bar:focus-within i { color: var(--accent); }
    .vod-search-bar input {
      flex: 1; background: transparent; border: none; color: #fff; outline: none; font-size: 1.1rem;
      font-family: var(--font-main);
    }
    .vod-search-bar input::placeholder { color: var(--text-muted); }

    /* Area Scorrimento e Griglia */
    .vod-scroll-area { flex: 1; overflow-y: auto; padding: 0 2rem 2rem 2rem; scroll-behavior: smooth; }
    .vod-scroll-area::-webkit-scrollbar { width: 8px; }
    .vod-scroll-area::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .vod-scroll-area::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    .vod-section-title { 
      font-family: var(--font-alt); font-size: 1.8rem; margin: 0 0 1.5rem 0; 
      color: #fff; font-weight: 800; letter-spacing: -0.5px;
      text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }
    
    .vod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 2rem; }
    
    /* Poster Card: Effetto 3D e Vetro */
    .vod-card {
      position: relative; border-radius: 16px; overflow: hidden; cursor: pointer;
      box-shadow: 0 10px 20px rgba(0,0,0,0.5); 
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      aspect-ratio: 2 / 3; background: #111;
      border: 1px solid rgba(255,255,255,0.05);
    }
    .vod-card:hover { 
      transform: translateY(-10px) scale(1.05); 
      box-shadow: 0 20px 40px rgba(0,0,0,0.8), 0 0 20px rgba(255,255,255,0.1); 
      border-color: rgba(255,255,255,0.2);
      z-index: 10;
    }
    .vod-card img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s ease; }
    .vod-card:hover img { transform: scale(1.08); }
    
    /* Badge Voto Fisso sulla Card */
    .vod-card-badge {
      position: absolute; top: 10px; right: 10px;
      background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px);
      padding: 4px 8px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);
      color: #fbbf24; font-weight: 800; font-size: 0.85rem;
      display: flex; align-items: center; gap: 4px; z-index: 2;
    }

    /* Overlay Titolo Hover */
    .vod-card-overlay {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: linear-gradient(to top, rgba(0,0,0,1) 0%, rgba(0,0,0,0.6) 50%, rgba(0,0,0,0) 100%);
      padding: 40px 15px 15px 15px; display: flex; flex-direction: column; gap: 5px; 
      opacity: 0; transition: opacity 0.3s ease; z-index: 2;
    }
    .vod-card:hover .vod-card-overlay { opacity: 1; }
    .vod-card-title { font-family: var(--font-alt); font-weight: 800; font-size: 1.1rem; color: #fff; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }
    
    .vod-loading, .vod-empty { grid-column: 1 / -1; text-align: center; padding: 4rem; color: var(--text-muted); font-size: 1.3rem; font-weight: 600; }

    /* VOD MODAL PREMIUM */
    .vod-modal {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.7); backdrop-filter: blur(15px); z-index: 9999;
      display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: all 0.4s ease;
    }
    .vod-modal.open { opacity: 1; pointer-events: auto; }
    
    .vod-modal-content {
      background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
      border: 1px solid rgba(255,255,255,0.1); border-radius: 24px;
      width: 90%; max-width: 900px; position: relative; 
      transform: scale(0.95) translateY(20px); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      overflow: hidden; box-shadow: 0 30px 60px rgba(0,0,0,0.8), inset 0 1px 0 rgba(255,255,255,0.1); 
      display: flex; max-height: 85vh;
    }
    .vod-modal.open .vod-modal-content { transform: scale(1) translateY(0); }
    
    .vod-modal-close {
      position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color: #fff;
      width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
      cursor: pointer; font-size: 1.5rem; z-index: 10; transition: all 0.2s; backdrop-filter: blur(4px);
    }
    .vod-modal-close:hover { background: rgba(255,255,255,0.2); transform: scale(1.1); }
    
    .vod-modal-poster { width: 320px; flex-shrink: 0; padding: 2rem; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.2); }
    .vod-modal-poster img { width: 100%; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.05); }
    
    .vod-modal-info { padding: 3rem 3rem 3rem 1rem; flex: 1; display: flex; flex-direction: column; gap: 1.5rem; overflow-y: auto; }
    .vod-modal-info::-webkit-scrollbar { width: 6px; }
    .vod-modal-info::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    
    #vod-modal-title { font-family: var(--font-alt); font-size: 2.8rem; margin: 0; line-height: 1.1; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); font-weight: 800; letter-spacing: -1px; }
    
    .vod-meta-badge { 
      background: rgba(255,255,255,0.08); padding: 6px 16px; border-radius: 30px; 
      font-size: 0.95rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; 
      border: 1px solid rgba(255,255,255,0.05); color: #e2e8f0;
    }
    .vod-meta-badge.rating { color: #fbbf24; background: rgba(251, 191, 36, 0.15); border-color: rgba(251, 191, 36, 0.3); }
    
    .vod-modal-desc { font-size: 1.1rem; line-height: 1.7; color: #cbd5e1; margin: 0; }
  </style>
</head>
<body>
  <!-- Header Mobile -->
  <header class="dash-header">
    <div class="dash-logo" style="display:flex;align-items:center;gap:0.5rem;">
        <i class="ph-fill ph-film-strip"></i> FILM & SERIE
    </div>
  </header>

  <div class="vod-page-layout">
    <!-- Sidebar / Navigazione -->
    <aside class="dash-sidebar" id="dash-sidebar">
      <div class="dash-cat-title">
        Menu VOD
        <button id="sidebar-toggle" class="sidebar-toggle" title="Nascondi sidebar">
          <i class="ph ph-sidebar-simple"></i>
        </button>
      </div>
      <div class="dash-cat-list" id="vod-menu-list">
        <div class="dash-cat-item active" data-category="trending" onclick="loadCategory('trending', this)">
          <i class="ph ph-trend-up"></i> In Tendenza
        </div>
        <div class="dash-cat-item" data-category="movie" onclick="loadCategory('movie', this)">
          <i class="ph ph-film-strip"></i> Film
        </div>
        <div class="dash-cat-item" data-category="tv" onclick="loadCategory('tv', this)">
          <i class="ph ph-television"></i> Serie TV
        </div>
      </div>
      
      <!-- Back to Live TV -->
      <a href="index.php" class="dash-exit" style="margin-top: auto;"><i class="ph ph-monitor-play"></i> LIVE TV</a>
    </aside>

    <!-- Main Content -->
    <main class="dash-main" style="display: flex; flex-direction: column;">
      
      <!-- Barra di ricerca -->
      <div class="vod-search-bar">
        <i class="ph ph-magnifying-glass"></i>
        <input type="text" id="vod-search-input" placeholder="Cerca film o serie tv...">
      </div>

      <!-- Griglia Contenuti -->
      <div class="vod-scroll-area">
        <h2 id="vod-section-title" class="vod-section-title">In Tendenza</h2>
        <div id="vod-grid" class="vod-grid">
           <!-- Popolato via JS -->
        </div>
      </div>

    </main>
  </div>

  <!-- Modal Dettaglio Film/Serie -->
  <div id="vod-modal" class="vod-modal">
    <div class="vod-modal-content">
      <button class="vod-modal-close" onclick="closeVodModal()"><i class="ph ph-x"></i></button>
      <div class="vod-modal-body">
        <div class="vod-modal-poster">
           <img id="vod-modal-img" src="" alt="Poster">
        </div>
        <div class="vod-modal-info">
           <h2 id="vod-modal-title">Titolo</h2>
           <div class="vod-modal-meta">
              <span id="vod-modal-date" class="vod-meta-badge"><i class="ph ph-calendar"></i> 2023</span>
              <span id="vod-modal-rating" class="vod-meta-badge rating"><i class="ph-fill ph-star"></i> 8.5</span>
           </div>
           <p id="vod-modal-overview" class="vod-modal-desc">Descrizione in arrivo...</p>
        </div>
      </div>
    </div>
  </div>

  <script src="js/vod.js?v=1.19"></script>
</body>
</html>
