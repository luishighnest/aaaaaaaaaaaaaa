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
    body { margin: 0; height: 100vh; overflow: hidden; }
    .vod-page-layout {
      display: flex;
      height: calc(100vh - 60px); /* header height approx */
      gap: 15px;
      padding: 15px;
      padding-top: 0;
    }
    .dash-sidebar {
      width: 260px;
      flex-shrink: 0;
      height: 100%;
      border-radius: var(--radius-lg);
    }
    .dash-main {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }

    /* VOD STYLES */
    .vod-search-bar {
      display: flex; align-items: center;
      background: var(--bg-input); border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md); padding: 0.8rem 1rem; margin: 1.5rem; gap: 0.8rem;
    }
    .vod-search-bar input {
      flex: 1; background: transparent; border: none; color: var(--text-primary); outline: none; font-size: 1rem;
    }
    .vod-scroll-area { flex: 1; overflow-y: auto; padding: 0 1.5rem 1.5rem 1.5rem; }
    .vod-section-title { font-family: var(--font-alt); font-size: 1.5rem; margin: 0 0 1.5rem 0; }
    .vod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; }
    .vod-card {
      position: relative; border-radius: var(--radius-md); overflow: hidden; cursor: pointer;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: transform 0.3s ease, box-shadow 0.3s ease;
      aspect-ratio: 2 / 3; background: var(--bg-input);
    }
    .vod-card:hover { transform: translateY(-5px) scale(1.03); box-shadow: 0 10px 25px rgba(0,0,0,0.6); }
    .vod-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .vod-card-overlay {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0) 100%);
      padding: 30px 15px 15px 15px; display: flex; flex-direction: column; gap: 5px; opacity: 0; transition: opacity 0.3s ease;
    }
    .vod-card:hover .vod-card-overlay { opacity: 1; }
    .vod-card-title { font-family: var(--font-alt); font-weight: 700; font-size: 1rem; color: #fff; line-height: 1.2; }
    .vod-card-rating { font-size: 0.85rem; color: #fbbf24; font-weight: 700; display: flex; align-items: center; gap: 3px; }
    .vod-loading, .vod-empty { grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted); font-size: 1.2rem; }

    /* VOD MODAL */
    .vod-modal {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 1000;
      display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
    }
    .vod-modal.open { opacity: 1; pointer-events: auto; }
    .vod-modal-content {
      background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);
      width: 90%; max-width: 800px; position: relative; transform: scale(0.9); transition: transform 0.3s ease;
      overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5); display: flex; max-height: 85vh;
    }
    .vod-modal.open .vod-modal-content { transform: scale(1); }
    .vod-modal-close {
      position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.1); border: none; color: #fff;
      width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
      cursor: pointer; font-size: 1.5rem; z-index: 10;
    }
    .vod-modal-close:hover { background: rgba(255,255,255,0.2); }
    .vod-modal-poster { width: 300px; flex-shrink: 0; }
    .vod-modal-poster img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .vod-modal-info { padding: 2.5rem; flex: 1; display: flex; flex-direction: column; gap: 1.5rem; overflow-y: auto; }
    #vod-modal-title { font-family: var(--font-alt); font-size: 2.2rem; margin: 0; line-height: 1.1; }
    .vod-meta-badge { background: rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .vod-meta-badge.rating { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
    .vod-modal-desc { font-size: 1rem; line-height: 1.6; color: var(--text-secondary); margin: 0; }
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

  <script src="js/vod.js?v=1.18"></script>
</body>
</html>
