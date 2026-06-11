  <?php
  // Avvia sessione e verifica autenticazione
  session_start();
  
  $is_loading_route = (isset($_GET['type']) && isset($_GET['id'])) || (isset($_GET['play']) && isset($_GET['id']));

  // Sincronizza il profilo attivo in sessione con user_profiles.json se esiste
  if (isset($_SESSION['username']) && isset($_SESSION['active_profile']['id'])) {
      $username = $_SESSION['username'];
      $profile_id = $_SESSION['active_profile']['id'];
      $profiles_file = file_exists(__DIR__ . '/user_profiles.json') 
          ? __DIR__ . '/user_profiles.json' 
          : (file_exists(dirname(__DIR__) . '/user_profiles.json') ? dirname(__DIR__) . '/user_profiles.json' : '');
      if ($profiles_file && file_exists($profiles_file)) {
          $file_mtime = filemtime($profiles_file);
          // Risincronizza solo se il file è stato modificato dopo l'ultima sincronizzazione
          if (!isset($_SESSION['profiles_synced_at']) || $_SESSION['profiles_synced_at'] < $file_mtime) {
              $profiles_data = json_decode(file_get_contents($profiles_file), true);
              if (isset($profiles_data[$username]) && is_array($profiles_data[$username])) {
                  foreach ($profiles_data[$username] as $p) {
                      if ($p['id'] === $profile_id) {
                          $_SESSION['active_profile'] = $p;
                          $_SESSION['profiles_synced_at'] = $file_mtime;
                          break;
                      }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<?php
    $page_title = "Film & Serie TV - VOD";
    if (isset($_GET['section'])) {
        switch ($_GET['section']) {
            case 'movies':  $page_title = "Film - VOD"; break;
            case 'tv':      $page_title = "Serie TV - VOD"; break;
            case 'catalog': $page_title = "Catalogo - VOD"; break;
            case 'library': $page_title = "Libreria - VOD"; break;
        }
    } elseif (isset($_GET['play'])) {
        $page_title = "Guarda";
    } elseif (isset($_GET['type'])) {
        $page_title = "Dettagli";
    }
    ?>
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <link rel="stylesheet" href="/css/vod.css?v=<?= filemtime(__DIR__ . '/css/vod.css') ?>">
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
        const accent = localStorage.getItem('accent_color') || '#00f2fe';
        const glow = localStorage.getItem('accent_glow') || 'rgba(0, 242, 254, 0.3)';
        document.documentElement.style.setProperty('--accent', accent);
        document.documentElement.style.setProperty('--accent-glow', glow);

        window.updateFaviconColor = function(color) {
          const hex = color || '#00f2fe';
          const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><defs><linearGradient id='g' x1='0%' y1='0%' x2='100%' y2='100%'><stop offset='0%' stop-color='${hex}'/><stop offset='100%' stop-color='#020617'/></linearGradient></defs><rect width='24' height='24' rx='6' fill='url(#g)'/><path d='M9.5 7.5v9l7-4.5-7-4.5z' fill='#ffffff'/></svg>`;
          let link = document.querySelector('link[rel="icon"]');
          if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            link.type = 'image/svg+xml';
            document.head.appendChild(link);
          }
          link.href = 'data:image/svg+xml,' + encodeURIComponent(svg);
        };

        window.updateFaviconColor(accent);
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
      window.__CSRF_TOKEN__ = <?= json_encode($_SESSION['csrf_token']) ?>;
      window.__ACTIVE_PROFILE_VOD_FAVORITES__ = <?= json_encode($vod_favs) ?>;
      window.__ACTIVE_PROFILE_VOD_HISTORY__ = <?= json_encode($vod_history) ?>;
    </script>
    <?php if ($is_loading_route): ?>
    <style>
      /* Evita flash della homepage caricando un'interfaccia nera di attesa */
      body.route-loading .vod-navbar,
      body.route-loading .dash-main,
      body.route-loading #vod-modal {
        opacity: 0 !important;
        pointer-events: none !important;
      }
      body.route-loading {
        background: #020617 !important;
      }
      /* Spinner centrale per la route */
      body.route-loading::before {
        content: "";
        position: fixed;
        top: 50%;
        left: 50%;
        width: 36px;
        height: 36px;
        margin-top: -18px;
        margin-left: -18px;
        border: 2px solid rgba(255, 255, 255, 0.08);
        border-top-color: var(--accent, #ffffff);
        border-radius: 50%;
        animation: route-loader-spin 0.8s linear infinite;
        z-index: 1000000;
        pointer-events: none;
      }
      @keyframes route-loader-spin {
        to { transform: rotate(360deg); }
      }
      <?php if (isset($_GET['play'])): ?>
      body.route-loading #vod-player-overlay {
        display: flex !important;
        opacity: 1 !important;
        background: #000000 !important;
      }
      <?php endif; ?>
    </style>
    <?php endif; ?>
  </head>
  <body class="dashboard-body <?= $is_loading_route ? 'route-loading' : '' ?>">

    <!-- ═══════════════════════════════════════════
         MOBILE HEADER + DRAWER (visibile solo su mobile)
         ═══════════════════════════════════════════ -->
    <header class="vod-mobile-header" id="vod-mobile-header">
      <div class="vod-mh-left">
        <button class="vod-mh-menu-btn" id="vod-mh-menu-btn" aria-label="Apri menu">
          <i class="ph ph-list"></i>
        </button>
        <div class="vod-mh-brand">PZ<span>8</span> <span class="vod-mh-brand-sub">VOD</span></div>
      </div>
      <div class="vod-mh-right">
        <div class="vod-mh-search-btn" id="vod-mh-search-btn">
          <i class="ph ph-magnifying-glass"></i>
        </div>
        <a href="/mobile.php" id="vod-back-home-btn" class="vod-mh-back-btn" aria-label="Torna alla home">
          <i class="ph ph-house"></i>
        </a>
      </div>
    </header>

    <!-- Search bar mobile (espandibile) -->
    <div class="vod-mobile-search-bar" id="vod-mobile-search-bar">
      <div class="vod-mh-search-inner">
        <i class="ph ph-magnifying-glass"></i>
        <input type="text" id="vod-search-input" placeholder="Cerca film o serie tv..." autocomplete="off">
        <div class="clear-icon" id="vod-search-clear"><i class="ph ph-x"></i></div>
      </div>
      <div class="vod-search-dropdown" id="vod-search-dropdown"></div>
    </div>

    <!-- Drawer overlay -->
    <div class="vod-drawer-overlay" id="vod-drawer-overlay"></div>

    <!-- Drawer menu -->
    <aside class="vod-drawer" id="vod-drawer">
      <div class="vod-drawer-header">
        <div class="vod-drawer-logo">PZ<span>8</span> VOD</div>
        <button class="vod-drawer-close" id="vod-drawer-close"><i class="ph ph-x"></i></button>
      </div>
      <div class="vod-drawer-body">

        <div class="vod-drawer-section-title">Naviga</div>
        <nav class="vod-drawer-list">
          <div class="vod-drawer-item active" id="drawer-item-home" onclick="vodDrawerNav('home')">
            <i class="ph ph-house"></i> Home
          </div>
          <div class="vod-drawer-item" id="drawer-item-movies" onclick="vodDrawerNav('movies')">
            <i class="ph ph-film-strip"></i> Film
          </div>
          <div class="vod-drawer-item" id="drawer-item-tv" onclick="vodDrawerNav('tv')">
            <i class="ph ph-television"></i> Serie TV
          </div>
          <div class="vod-drawer-item" id="drawer-item-catalog" onclick="vodDrawerNav('catalog')">
            <i class="ph ph-folder-open"></i> Catalogo
          </div>
          <div class="vod-drawer-item" id="drawer-item-library" onclick="vodDrawerNav('library')">
            <i class="ph ph-plus-circle"></i> Libreria
          </div>
        </nav>

        <div class="vod-drawer-section-title" style="margin-top:1.2rem;">Impostazioni</div>
        <nav class="vod-drawer-list">
          <div class="vod-drawer-item" id="vod-drawer-color-btn">
            <i class="ph ph-palette"></i> Colore Principale
          </div>
          <a href="/mobile.php" class="vod-drawer-item">
            <i class="ph ph-arrow-left"></i> Torna ai Canali
          </a>
        </nav>

        <!-- Color swatches inline nel drawer -->
        <div class="vod-drawer-color-panel" id="vod-drawer-color-panel" style="display:none;">
          <div class="vod-drawer-color-swatches" id="vod-drawer-color-swatches"></div>
        </div>

      </div>
      <div class="vod-drawer-footer">
        <div>PZ8 VOD</div>
      </div>
    </aside>
    <!-- ═══════════════════════════════════════════ -->

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
        <div class="vod-navbar-right">
          <div class="nav-search" id="vod-desktop-search-box">
            <input type="text" id="vod-search-input-desktop" placeholder="Cerca film o serie tv..." autocomplete="off">
            <div class="vod-search-icon-wrapper">
              <i class="ph ph-magnifying-glass search-icon"></i>
              <div class="clear-icon" id="vod-search-clear-desktop"><i class="ph ph-x"></i></div>
            </div>
            <div class="vod-search-dropdown" id="vod-search-dropdown-desktop"></div>
          </div>

          <!-- Tasto cambio colore -->
          <div class="vod-navbar-icon-btn" id="vod-color-btn">
            <i class="ph ph-palette"></i>
            <div class="vod-color-popup" id="vod-color-popup">
            <div class="vod-color-popup-title">Colore principale</div>
            <div class="vod-color-swatches" id="vod-color-swatches"></div>
          </div>
        </div>

          <!-- Torna alla Home (desktop) -->
          <a href="/index.php" class="vod-navbar-icon-btn vod-back-btn" id="vod-back-home-btn-desktop">
            <i class="ph ph-house"></i>
          </a>
        </div><!-- /vod-navbar-right -->
      </header>

      <!-- Main Content (Vertical Scroll) -->
      <main class="dash-main" id="dash-main">
        
        <!-- Hero Banner -->
        <div class="vod-hero-banner" id="vod-hero-banner" style="display: none;">
          <div class="vod-hero-bg">
            <img id="vod-hero-backdrop" class="vod-hero-backdrop" src="" alt="Backdrop">
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
              <button class="vod-hero-btn fav-round" id="vod-hero-fav-btn"><i class="ph ph-plus-circle"></i></button>
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
          <!-- Colonna destra: TUTTA scorrevole -->
          <div class="vod-modal-details">
            <h2 id="vod-modal-title">Titolo</h2>
            <div id="vod-modal-tagline"></div>
            <div class="vod-modal-meta-row">
              <span class="vod-meta-info-item rating" id="vod-modal-rating"><i class="ph-fill ph-star"></i> N/A</span>
              <span class="vod-meta-sep">•</span>
              <span class="vod-meta-info-item" id="vod-modal-date">N/A</span>
              <span class="vod-meta-sep">•</span>
              <span class="vod-meta-info-item" id="vod-modal-duration">N/A</span>
              <span class="vod-meta-sep">•</span>
              <span class="vod-meta-info-item status" id="vod-modal-status">N/A</span>
            </div>
            <div class="vod-modal-info-grid">
              <div class="vod-modal-info-left">
                <p class="vod-modal-desc" id="vod-modal-overview"></p>
              </div>
              <div class="vod-modal-info-right" id="vod-modal-metadata">
                <!-- Popolato via JS: Cast, Regia, Creatori, Generi ecc. -->
              </div>
            </div>
            <div class="vod-modal-genres" id="vod-modal-genres" style="display:none;"></div>
            <!-- Pulsanti azione + linea separatrice -->
            <div class="vod-modal-action-bar">
              <div class="vod-modal-action-row">
                <button class="vod-hero-btn play" id="vod-modal-play-btn"><svg class="btn-play-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v14l11-7-11-7z"/></svg> Guarda Ora</button>
                <button class="vod-hero-btn" id="vod-modal-resume-btn" style="display:none;"><svg class="btn-play-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v14l11-7-11-7z"/></svg> Riprendi</button>
                <button class="vod-modal-fav-btn-new" id="vod-modal-fav-btn"><i class="ph ph-plus-circle"></i> La mia Lista</button>
              </div>
            </div>
            <!-- Episodi TV -->
            <div id="vod-modal-tv-section" style="display:none; margin-top:1rem;">
              <div class="vod-modal-tv-header">
                <select id="vod-season-select"></select>
              </div>
              <div style="overflow: hidden; padding-right: 2px; padding-top: 1.5rem; background: #020617;">
                <div id="vod-episodes-list" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MINI MODAL PER TRAMA COMPLETA -->
    <div class="vod-mini-modal" id="vod-mini-modal">
      <div class="vod-mini-modal-content">
        <button class="vod-mini-modal-close" onclick="closeVodMiniModal()" aria-label="Chiudi trama"><i class="ph ph-x"></i></button>
        <h3>Trama Completa</h3>
        <p id="vod-mini-modal-text"></p>
      </div>
    </div>

    <!-- PLAYER OVERLAY -->
    <div class="vod-player-overlay" id="vod-player-overlay">
      <!-- Tasto Indietro (pill top-left) -->
      <button class="vod-player-close" onclick="closePlayer()" style="z-index: 99999;">
        <i class="ph ph-arrow-left"></i>
      </button>

      <!-- Pill controlli verticale (top-right) -->
      <div class="vod-player-controls-pill" id="vod-player-controls-pill">
        <!-- Info -->
        <button class="vod-player-pill-btn pill-info" id="vod-player-info-btn" onclick="togglePlayerInfoPanel()">
          <i class="ph ph-info"></i>
          <span class="pill-label">Info</span>
        </button>
        <!-- Separatore -->
        <div class="vod-player-pill-sep"></div>
        <!-- Episodi/Playlist -->
        <button class="vod-player-pill-btn pill-playlist" id="vod-player-playlist-btn" onclick="togglePlayerPlaylistPanel()" style="display: none;">
          <i class="ph ph-playlist"></i>
          <span class="pill-label">Episodi</span>
        </button>
        <!-- Separatore next (visibile solo con playlist) -->
        <div class="vod-player-pill-sep pill-sep-next" style="display: none;"></div>
        <!-- Episodio successivo -->
        <button class="vod-player-pill-btn pill-next" id="vod-player-next-btn" onclick="playNextEpisode()" style="display: none;">
          <i class="ph ph-skip-forward"></i>
          <span class="pill-label">Avanti</span>
        </button>
      </div>

      <!-- Fullscreen (bottom-right, invariato) -->
      <button class="vod-player-fullscreen" id="vod-player-fullscreen-btn" onclick="togglePlayerFullscreen()"><i class="ph ph-corners-out"></i></button>

      <div class="vod-player-mouse-tracker" id="vod-player-mouse-tracker"></div>
      <div class="vod-player-title-header" id="vod-player-title-header">
        <h2 id="vod-player-title"></h2>
        <div id="vod-player-subtitle"></div>
      </div>

      <!-- Pannello Playlist (Stagioni & Episodi) -->
      <div id="vod-player-playlist-panel">
        <div class="vod-playlist-panel-header">
          <div class="vod-playlist-panel-top">
            <span class="vod-playlist-panel-show-name" id="vod-playlist-show-title">—</span>
            <button class="vod-playlist-close-btn" onclick="togglePlayerPlaylistPanel()"><i class="ph ph-x"></i></button>
          </div>
          <!-- Selettore stagione custom dropdown -->
          <div class="vod-playlist-season-dropdown-container">
            <button class="vod-playlist-season-dropdown-btn" id="vod-playlist-season-dropdown-btn">
              <span id="vod-playlist-season-dropdown-label">Caricamento...</span>
              <i class="ph ph-caret-down"></i>
            </button>
            <div class="vod-playlist-season-dropdown-menu" id="vod-playlist-season-dropdown-menu"></div>
          </div>
        </div>
        <div id="vod-playlist-episodes-list">
          <!-- Popolato via JS -->
        </div>
      </div>

      <div id="vod-player-info-panel"></div>
      <div class="vod-player-wrapper">
        <iframe id="vod-player-frame" src="about:blank" allow="autoplay; fullscreen" allowfullscreen></iframe>
      </div>
    </div>

    <script src="/js/vod.js?v=<?= filemtime(__DIR__ . '/js/vod.js') ?>"></script>

  <script>
    // ── Back-home button: redirect to mobile.php if on mobile view ──
    (function() {
      const btn = document.getElementById('vod-back-home-btn');
      if (!btn) return;
      const cookies = document.cookie.split(';').map(c => c.trim());
      const viewMode = cookies.find(c => c.startsWith('pz8_view_mode='));
      const mode = viewMode ? viewMode.split('=')[1] : null;
      if (mode === 'pc') {
        btn.href = '/index.php';
      } else if (mode === 'tv') {
        btn.href = '/tv.php';
      } else {
        btn.href = '/mobile.php'; // mobile or unknown
      }
    })();
    // ── Vod Color Picker ──────────────────────────────
    (function() {
      const accentPresets = [
        { name: 'Ciano',        hex: '#00f2fe', glow: 'rgba(0, 242, 254, 0.3)' },
        { name: 'Rosso Netflix',hex: '#e50914', glow: 'rgba(229, 9, 20, 0.3)' },
        { name: 'Oro Premium',  hex: '#eab308', glow: 'rgba(234, 179, 8, 0.3)' },
        { name: 'Smeraldo',     hex: '#10b981', glow: 'rgba(16, 185, 129, 0.3)' },
        { name: 'Viola',        hex: '#a855f7', glow: 'rgba(168, 85, 247, 0.3)' },
        { name: 'Rosa',         hex: '#ec4899', glow: 'rgba(236, 72, 153, 0.3)' },
        { name: 'Bianco',       hex: '#ffffff', glow: 'rgba(255, 255, 255, 0.3)' },
        { name: 'Nero',         hex: '#000000', glow: 'rgba(0, 0, 0, 0.3)' }
      ];

      const btn     = document.getElementById('vod-color-btn');
      const popup   = document.getElementById('vod-color-popup');
      const swatches= document.getElementById('vod-color-swatches');

      let current = localStorage.getItem('accent_color') || '#00f2fe';

      function renderSwatches() {
        swatches.innerHTML = '';
        accentPresets.forEach(p => {
          const sel = current.toLowerCase() === p.hex.toLowerCase();
          const s = document.createElement('div');
          s.title = p.name;
          s.style.cssText = `
            width:30px; height:30px; border-radius:50%; background:${p.hex}; cursor:pointer;
            border: ${sel ? '3px solid #fff' : '1px solid rgba(255, 255, 255, 0.2)'};
            box-shadow: ${sel ? `0 0 0 2px ${p.hex}` : 'none'};
            transform: ${sel ? 'scale(1.15)' : 'scale(1)'};
            transition: all 0.18s;
          `;
          s.onmouseenter = () => { if (!sel) s.style.transform = 'scale(1.1)'; };
          s.onmouseleave = () => { if (!sel) s.style.transform = 'scale(1)'; };
          s.onclick = (e) => {
            e.stopPropagation();
            current = p.hex;
            localStorage.setItem('accent_color', p.hex);
            localStorage.setItem('accent_glow',  p.glow);
            document.documentElement.style.setProperty('--accent', p.hex);
            document.documentElement.style.setProperty('--accent-glow', p.glow);
            if (window.updateFaviconColor) window.updateFaviconColor(p.hex);
            renderSwatches();
          };
          swatches.appendChild(s);
        });
      }

      renderSwatches();

      // Toggle popup
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        popup.classList.toggle('open');
      });

      // Chiudi cliccando fuori
      document.addEventListener('click', () => popup.classList.remove('open'));
      popup.addEventListener('click', (e) => e.stopPropagation());
    })();
  </script>
  </body>
  </html>
