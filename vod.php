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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
    body { margin: 0; height: 100vh; overflow: hidden; background: #050505; font-family: 'Montserrat', sans-serif; }
    
    .vod-page-layout {
      display: flex;
      flex-direction: column;
      height: calc(100vh - 60px); /* header height approx */
    }

    /* Top Navbar */
    .vod-navbar {
      display: flex;
      align-items: center;
      padding: 15px 30px;
      background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0) 100%);
      gap: 30px;
      z-index: 50;
    }
    .vod-nav-brand {
      color: var(--accent); font-weight: 900; font-size: 1.5rem; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px;
    }
    .vod-nav-links {
      display: flex; gap: 20px; flex: 1;
    }
    .vod-nav-item {
      color: #cbd5e1; font-weight: 500; font-size: 1rem; cursor: pointer; transition: color 0.2s;
    }
    .vod-nav-item:hover, .vod-nav-item.active {
      color: #fff; font-weight: 700;
    }
    
    /* Barra di Ricerca Premium nell'header */
    .vod-search-bar {
      display: flex; align-items: center;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 50px; 
      padding: 0.6rem 1.2rem; 
      gap: 10px;
      transition: all 0.3s ease;
      width: 250px;
    }
    .vod-search-bar:focus-within {
      background: rgba(0,0,0,0.6);
      border-color: var(--accent);
      box-shadow: 0 0 15px var(--accent-glow);
      width: 350px;
    }
    .vod-search-bar i { color: #fff; font-size: 1.2rem; }
    .vod-search-bar input {
      flex: 1; background: transparent; border: none; color: #fff; outline: none; font-size: 1rem; font-family: 'Montserrat', sans-serif;
    }
    .vod-search-bar input::placeholder { color: rgba(255,255,255,0.6); }

    /* VOD Main Area (Scrollable) */
    .dash-main {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding-bottom: 40px;
      scroll-behavior: smooth;
    }
    .dash-main::-webkit-scrollbar { width: 8px; }
    .dash-main::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

    /* Stile Righe Netflix */
    .vod-row-container { margin-bottom: 3rem; margin-top: 1rem; padding-left: 30px; }
    .vod-row-title { 
      font-size: 1.4rem; font-weight: 800; color: #e2e8f0; margin-bottom: 12px; 
      display: flex; align-items: center; gap: 8px;
    }
    .vod-row-title i { color: var(--accent); font-size: 1.6rem; }
    .vod-row {
      display: flex; gap: 15px; overflow-x: auto; padding-bottom: 15px; padding-right: 30px;
      scroll-behavior: smooth; scroll-snap-type: x mandatory;
    }
    .vod-row::-webkit-scrollbar { height: 6px; }
    .vod-row::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .vod-row::-webkit-scrollbar-thumb:hover { background: var(--accent); }

    /* Griglia Ricerca */
    .vod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1.5rem; padding: 20px 30px; }
    
    /* VOD Main Area */
    .dash-main {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      background: linear-gradient(145deg, #111827 0%, #030712 100%);
      border: 1px solid rgba(255,255,255,0.04);
      border-radius: 24px;
      overflow: hidden;
      position: relative;
      box-shadow: 0 15px 40px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.05);
    }

    /* Stili Sidebar Items Premium */
    .dash-cat-list { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
    .dash-cat-item {
      padding: 14px 18px; border-radius: 14px; color: var(--text-secondary);
      display: flex; align-items: center; gap: 12px; cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      font-weight: 500; font-size: 1.05rem; border: 1px solid transparent;
    }
    .dash-cat-item i { font-size: 1.4rem; transition: transform 0.3s ease; }
    .dash-cat-item:hover {
      background: rgba(255,255,255,0.04); color: #fff;
    }
    .dash-cat-item:hover i { transform: scale(1.1); }
    .dash-cat-item.active {
      background: linear-gradient(90deg, rgba(255,255,255,0.08) 0%, transparent 100%);
      border-left: 4px solid var(--accent);
      color: #fff; font-weight: 700;
      box-shadow: inset 1px 0 10px rgba(255,255,255,0.02);
    }
    .dash-cat-item.active i { color: var(--accent); }

    /* Barra di Ricerca Premium */
    .vod-search-bar {
      display: flex; align-items: center;
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 50px; 
      padding: 1.2rem 2rem; 
      margin: 2.5rem 2.5rem 1.5rem 2.5rem; 
      gap: 1rem;
      transition: all 0.3s ease;
    }
    .vod-search-bar:focus-within {
      border-color: var(--accent);
      box-shadow: 0 0 25px rgba(0,0,0,0.5), inset 0 2px 4px rgba(0,0,0,0.5);
      background: rgba(0, 0, 0, 0.4);
    }
    .vod-search-bar i { color: var(--text-muted); font-size: 1.5rem; transition: color 0.3s ease; }
    .vod-search-bar:focus-within i { color: var(--accent); }
    .vod-search-bar input {
      flex: 1; background: transparent; border: none; color: #fff; outline: none; font-size: 1.15rem;
      font-family: var(--font-main);
    }
    .vod-search-bar input::placeholder { color: var(--text-muted); }

    /* Area Scorrimento e Griglia */
    .vod-scroll-area { flex: 1; overflow-y: auto; padding: 0 2.5rem 2.5rem 2.5rem; scroll-behavior: smooth; }
    .vod-scroll-area::-webkit-scrollbar { width: 8px; }
    .vod-scroll-area::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .vod-scroll-area::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    .vod-section-title { 
      font-family: var(--font-alt); font-size: 2.2rem; margin: 0 0 2rem 0; 
      font-weight: 800; letter-spacing: -0.5px;
      display: flex; align-items: center; gap: 12px;
      background: linear-gradient(to right, #ffffff, #94a3b8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .vod-section-title i { -webkit-text-fill-color: var(--accent); }
    
    .vod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1.8rem; }
    
    /* Poster Card */
    .vod-card {
      position: relative; border-radius: 8px; overflow: hidden; cursor: pointer;
      transition: all 0.3s ease; background: #111; flex-shrink: 0;
      scroll-snap-align: start;
    }
    /* Dimensione orizzontale (Landscape) per la prima riga */
    .vod-card.landscape { width: 300px; aspect-ratio: 16 / 9; }
    /* Dimensione verticale (Portrait) per le altre */
    .vod-card.portrait { width: 160px; aspect-ratio: 2 / 3; }

    .vod-card:hover { 
      transform: scale(1.05); z-index: 10;
      box-shadow: 0 10px 20px rgba(0,0,0,0.8); 
      border-radius: 12px;
    }
    .vod-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
    
    .vod-card-badge {
      position: absolute; top: 8px; right: 8px;
      background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px);
      padding: 3px 6px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);
      color: #fbbf24; font-weight: 800; font-size: 0.8rem;
      display: flex; align-items: center; gap: 4px; z-index: 2;
    }

    .vod-card-overlay {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0) 100%);
      padding: 30px 10px 10px 10px; display: flex; flex-direction: column; 
      opacity: 0; transition: opacity 0.3s ease; z-index: 2;
    }
    .vod-card:hover .vod-card-overlay { opacity: 1; }
    .vod-card-title { font-weight: 800; font-size: 0.95rem; color: #fff; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.8); text-align: center; }
    
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
    
    .vod-modal-poster { width: 300px; flex-shrink: 0; padding: 2rem; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.2); }
    .vod-modal-poster img { width: 100%; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.05); }
    
    .vod-modal-info { padding: 3rem 3rem 3rem 1rem; flex: 1; display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; }
    .vod-modal-info::-webkit-scrollbar { width: 6px; }
    .vod-modal-info::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    
    #vod-modal-title { font-family: var(--font-alt); font-size: 2.2rem; margin: 0; line-height: 1.1; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); font-weight: 800; letter-spacing: -1px; }
    
    #vod-modal-tagline { font-style: italic; color: var(--accent); font-size: 1.1rem; margin-top: -5px; }

    .vod-modal-meta-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }

    .vod-meta-badge { 
      background: rgba(255,255,255,0.08); padding: 4px 12px; border-radius: 30px; 
      font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; 
      border: 1px solid rgba(255,255,255,0.05); color: #e2e8f0;
    }
    .vod-meta-badge.rating { color: #fbbf24; background: rgba(251, 191, 36, 0.15); border-color: rgba(251, 191, 36, 0.3); }
    
    .vod-modal-desc { font-size: 1rem; line-height: 1.6; color: #cbd5e1; margin-top: 10px; }
    
    .vod-modal-genres { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .vod-genre-tag { background: rgba(255,255,255,0.1); padding: 3px 10px; border-radius: 6px; font-size: 0.8rem; color: #fff; border: 1px solid rgba(255,255,255,0.1); }
  </style>
</head>
<body>
  <!-- Header Mobile -->
  <header class="dash-header">
    <div class="dash-logo" style="display:flex;align-items:center;gap:0.5rem;">

  <div class="vod-page-layout">
    
    <!-- Navbar Superiore Stile Netflix -->
    <header class="vod-navbar">
      <div class="vod-nav-brand"><i class="ph-fill ph-play-circle"></i> VOD</div>
      <nav class="vod-nav-links">
        <div class="vod-nav-item active" onclick="window.location.reload()">Home</div>
        <div class="vod-nav-item" onclick="document.getElementById('vod-search-input').focus()">Cerca</div>
        <a class="vod-nav-item" href="index.php" style="text-decoration:none; margin-left: auto;">Torna a Live TV</a>
      </nav>
      <div class="vod-search-bar">
        <i class="ph ph-magnifying-glass"></i>
        <input type="text" id="vod-search-input" placeholder="Titoli, persone, generi...">
      </div>
    </header>

    <!-- Main Content -->
    <main class="dash-main" id="dash-main">
      
      <!-- Container Home (Righe Netflix) -->
      <div id="vod-home-container">
        <!-- Righe generate via JS -->
      </div>

      <!-- Container Ricerca -->
      <div id="vod-search-container" style="display: none;">
        <h2 style="color: #fff; padding: 0 30px;" id="vod-search-title">Risultati della Ricerca</h2>
        <div class="vod-grid" id="vod-search-grid"></div>
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

        <p class="vod-modal-desc" id="vod-modal-overview">Caricamento dettagli...</p>
      </div>
    </div>
  </div>

  <script src="js/vod.js?v=1.24"></script>
</body>
</html>
