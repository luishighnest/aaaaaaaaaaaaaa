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
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 40px;
      background: rgba(10, 10, 15, 0.85);
      backdrop-filter: blur(24px) saturate(1.4);
      -webkit-backdrop-filter: blur(24px) saturate(1.4);
      border-bottom: 1px solid var(--border-subtle);
      z-index: 100;
      transition: background 0.3s ease;
    }
    
    .vod-brand {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      cursor: pointer;
    }
    .vod-brand-icon {
      width: 36px;
      height: 36px;
      background: linear-gradient(135deg, #ef4444, #b91c1c);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      color: #fff;
      box-shadow: 0 0 12px rgba(239, 68, 68, 0.35);
    }
    .vod-brand-text {
      font-size: 1.25rem;
      font-weight: 800;
      letter-spacing: -0.5px;
      color: #fff;
      text-transform: uppercase;
    }
    .vod-brand-text span {
      color: #ef4444;
    }
    .vod-brand-sub {
      font-size: 0.8rem;
      font-weight: 700;
      opacity: 0.9;
      letter-spacing: 1px;
      color: var(--accent) !important;
      margin-left: 4px;
      text-shadow: 0 0 10px var(--accent-glow);
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
      color: var(--text-secondary);
      white-space: nowrap;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 0.4rem;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
    }
    .vod-navbar .nav-link:hover {
      color: var(--text-primary);
      background: rgba(255, 255, 255, 0.05);
      border-color: rgba(255, 255, 255, 0.1);
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
    }
    .vod-navbar .nav-search input {
      width: 100%;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border-subtle);
      border-radius: 99px;
      padding: 0.5rem 1.2rem 0.5rem 2.6rem;
      color: var(--text-primary);
      font-size: 0.85rem;
      transition: var(--transition);
      backdrop-filter: blur(10px);
      font-family: var(--font-main);
    }
    .vod-navbar .nav-search input:focus {
      outline: none;
      background: rgba(255, 255, 255, 0.08);
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-glow);
      width: 300px;
    }
    .vod-navbar .nav-search i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1rem;
      pointer-events: none;
    }

    /* Bottone Torna Live TV (Fisso a destra) */
    .vod-back-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1.2rem;
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      border-radius: 99px;
      color: #ef4444;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      cursor: pointer;
      transition: var(--transition);
    }
    .vod-back-btn:hover {
      background: #ef4444;
      color: #fff;
      border-color: #ef4444;
      box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
      transform: translateY(-1px);
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
    }
    .vod-row::-webkit-scrollbar {
      height: 6px;
    }
    .vod-row::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.01);
    }
    .vod-row::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.08);
      border-radius: 10px;
    }
    .vod-row::-webkit-scrollbar-thumb:hover {
      background: var(--accent);
    }

    /* Poster Card */
    .vod-card {
      position: relative;
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      background: var(--bg-surface);
      flex-shrink: 0;
      border: 1px solid rgba(255,255,255,0.03);
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .vod-card.landscape { width: 280px; aspect-ratio: 16 / 9; }
    .vod-card.portrait { width: 160px; aspect-ratio: 2 / 3; }
    
    .vod-card:hover { 
      transform: scale(1.06) translateY(-4px);
      z-index: 10;
      border-color: var(--accent);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6), 0 0 15px var(--accent-glow);
    }
    .vod-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 0.5s ease;
    }
    .vod-card:hover img {
      transform: scale(1.03);
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
      background: linear-gradient(to top, rgba(2, 6, 23, 0.95) 0%, rgba(2, 6, 23, 0.4) 60%, transparent 100%);
      padding: 15px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: 2;
    }
    .vod-card:hover .vod-card-overlay {
      opacity: 1;
    }
    .vod-card-title {
      font-weight: 800;
      font-size: 0.9rem;
      color: #fff;
      line-height: 1.3;
      text-align: center;
      text-shadow: 0 2px 4px rgba(0,0,0,0.8);
      font-family: var(--font-main);
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
  </style>
</head>
<body>
  <div class="vod-page-layout">
    
    <!-- Navbar Superiore Stile Netflix -->
    <header class="vod-navbar">
      <div class="vod-brand" onclick="window.location.reload()">
        <div class="vod-brand-icon"><i class="ph-fill ph-play-circle"></i></div>
        <div class="vod-brand-text">PZ<span>8</span><span class="vod-brand-sub">VOD</span></div>
      </div>
      <nav class="vod-nav-links">
        <div class="nav-link active" id="nav-item-home" onclick="resetSearch()">Home</div>
        <div class="nav-link" id="nav-item-search" onclick="document.getElementById('vod-search-input').focus()">Cerca</div>
      </nav>
      <div class="nav-search">
        <i class="ph ph-magnifying-glass"></i>
        <input type="text" id="vod-search-input" placeholder="Cerca film o serie tv...">
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
          </div>
        </div>
      </div>

      <!-- Container Home (Righe Netflix) -->
      <div id="vod-home-container">
        <!-- Righe generate via JS -->
      </div>

      <!-- Container Ricerca -->
      <div id="vod-search-container" style="display: none;">
        <h2 class="vod-search-section-title" id="vod-search-title">Risultati della Ricerca</h2>
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

  <script src="js/vod.js?v=<?= time() ?>"></script>
</body>
</html>
