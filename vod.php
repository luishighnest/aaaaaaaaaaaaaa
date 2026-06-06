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
  <link rel="stylesheet" href="css/style.css?v=1.17">
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
</head>
<body>
  <!-- Header Mobile -->
  <header class="dash-header">
    <div class="dash-logo" style="display:flex;align-items:center;gap:0.5rem;">
        <i class="ph-fill ph-film-strip"></i> FILM & SERIE
    </div>
  </header>

  <div class="dash-layout">
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

  <script src="js/vod.js?v=1.17"></script>
</body>
</html>
