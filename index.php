<?php
// Avvia sessione e verifica autenticazione
session_start();

// Gestione della modalità di visualizzazione (PC vs Mobile vs TV)
function isSmartTV() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/smarttv|smart-tv|hbbtv|appletv|googletv|tizen|webos|chromecast|roku|philips|sony|panasonic|lg-tv/i', $ua);
}

function isMobile() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $ua);
}

$view_mode = $_GET['view_mode'] ?? $_COOKIE['pz8_view_mode'] ?? null;

if (isset($_GET['view_mode'])) {
    $secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('pz8_view_mode', $_GET['view_mode'], [
        'expires' => time() + (365 * 24 * 3600),
        'path' => '/',
        'secure' => $secure_cookie,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    $view_mode = $_GET['view_mode'];
}

if ($view_mode === null) {
    if (isSmartTV()) {
        $view_mode = 'tv';
    } elseif (isMobile()) {
        $view_mode = 'mobile';
    } else {
        $view_mode = 'pc';
    }
    
    if ($view_mode !== 'pc') {
        $secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('pz8_view_mode', $view_mode, [
            'expires' => time() + (365 * 24 * 3600),
            'path' => '/',
            'secure' => $secure_cookie,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}

if ($view_mode === 'mobile') {
    $redirect_url = 'mobile.php';
    if (isset($_GET['id'])) {
        $redirect_url .= '?id=' . urlencode($_GET['id']);
    }
    header('Location: ' . $redirect_url);
    exit;
} elseif ($view_mode === 'tv') {
    $redirect_url = 'tv.php';
    if (isset($_GET['id'])) {
        $redirect_url .= '?id=' . urlencode($_GET['id']);
    }
    header('Location: ' . $redirect_url);
    exit;
}


// Genera il token CSRF se non presente in sessione
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Previeni il caching della pagina da parte del browser
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

$config_file = __DIR__ . '/users_config.php';
$config = file_exists($config_file) ? require $config_file : [];
$subscription_expiry = $config['subscription_expiry'] ?? '2027-12-31';
if (time() > strtotime($subscription_expiry . ' 23:59:59')) {
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
$active_profile = $_SESSION['active_profile'];
$username = $_SESSION['username'] ?? '';

// Carica tutti i profili dell'utente
$all_profiles = [];
$custom_profiles_file = __DIR__ . '/user_profiles.json';
if (file_exists($custom_profiles_file)) {
    $custom_data = json_decode(file_get_contents($custom_profiles_file), true);
    if (isset($custom_data[$username]) && is_array($custom_data[$username])) {
        $all_profiles = $custom_data[$username];
    }
}
if (empty($all_profiles)) {
    $all_profiles = $config['users'][$username]['profiles'] ?? [];
}
$all_profiles_json = json_encode($all_profiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$pname_check = isset($active_profile['name']) ? strtolower($active_profile['name']) : '';
$pid_check = isset($active_profile['id']) ? strtolower($active_profile['id']) : '';
$is_kids_profile = (
    strpos($pname_check, 'bambini') !== false || 
    strpos($pname_check, 'kids') !== false || 
    strpos($pid_check, 'bambini') !== false || 
    strpos($pid_check, 'kids') !== false
);

// Legge il JSON EPG dal file aggiornato dallo scraper Python
$epg_file = __DIR__ . '/guida_tv_sky.json';
$epg_data = [];
if (file_exists($epg_file)) {
    $json_raw = file_get_contents($epg_file);
    $epg_data = json_decode($json_raw, true) ?? [];
}
$epg_json = json_encode($epg_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$last_update = file_exists($epg_file) ? date('H:i', filemtime($epg_file)) : '--:--';

// Legge il JSON Agenda
$agenda_file = __DIR__ . '/agenda.json';
$agenda_data = [];
if (file_exists($agenda_file)) {
    $agenda_raw = file_get_contents($agenda_file);
    $agenda_data = json_decode($agenda_raw, true) ?? [];
}
$agenda_json = json_encode($agenda_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>PZ8</title>
    <meta name="description" content="Dashboard StreamHub Premium">
    <link rel="stylesheet" href="css/style.css?v=1.10">
    <script>
      (function() {
        const accent = localStorage.getItem('accent_color');
        const glow = localStorage.getItem('accent_glow');
        if (accent && glow) {
          document.documentElement.style.setProperty('--accent', accent);
          document.documentElement.style.setProperty('--accent-glow', glow);
        }
        
        // Rilevamento client-side per schermi piccoli (mobile)
        const cookieMode = document.cookie.split('; ').find(row => row.startsWith('pz8_view_mode='));
        const viewMode = cookieMode ? cookieMode.split('=')[1] : null;
        if (viewMode !== 'pc' && window.innerWidth <= 900) {
          const urlParams = new URLSearchParams(window.location.search);
          const chId = urlParams.get('id');
          window.location.href = 'mobile.php' + (chId ? '?id=' + chId : '');
        }
      })();
    </script>
    <style>
    /* OVERRIDE CACHED STYLES PER EVITARE CHE IL RIQUADRO SI ALLARGHI */
    .dashboard-layout .dash-info-area {
      position: relative !important;
      height: 100% !important;
      min-height: 0 !important;
      display: block !important;
    }

    .dashboard-layout .dash-info-card {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      overflow-y: auto !important;
      margin: 0 !important;
      height: auto !important;
      flex: none !important;
    }

    .dashboard-layout .dash-info-card::-webkit-scrollbar {
      width: 6px;
    }
    .dashboard-layout .dash-info-card::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 3px;
    }

    @media (max-width: 900px) {
      .dash-agenda-panel {
        grid-column: unset !important;
        width: 100% !important;
        border-radius: var(--radius-sm) !important;
      }
    }
  </style>
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
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

<body class="dashboard-body">

  <!-- EPG e Agenda iniettati lato server da PHP: dati disponibili immediatamente -->
  <script>
    window.__EPG_DATA__    = <?= $epg_json ?>;
    window.__EPG_UPDATED__ = "<?= $last_update ?>";
    window.__AGENDA_DATA__ = <?= $agenda_json ?>;
    window.__ACTIVE_PROFILE_NAME__ = "<?= isset($active_profile['name']) ? addslashes($active_profile['name']) : '' ?>";
    window.__ACTIVE_PROFILE_ID__   = "<?= isset($active_profile['id']) ? addslashes($active_profile['id']) : '' ?>";
    window.__ACTIVE_PROFILE_FAVORITES__ = <?= json_encode($active_profile['favorites'] ?? []) ?>;
    window.__ALL_PROFILES__        = <?= $all_profiles_json ?>;
    window.__CSRF_TOKEN__          = "<?= $_SESSION['csrf_token'] ?>";
  </script>

  <div class="dashboard-layout">
    <script>
      (function() {
        const sidebarHidden = localStorage.getItem('sidebar_hidden') === 'true';
        const guideExpanded = localStorage.getItem('guide_expanded') === 'true';
        const layout = document.querySelector('.dashboard-layout');
        if (sidebarHidden) layout.classList.add('sidebar-hidden');
        if (guideExpanded) layout.classList.add('guide-expanded');
      })();
    </script>

    <!-- Sidebar -->
    <aside class="dash-sidebar">
      <div class="dash-brand">
        <div class="dash-brand-icon"><i class="ph ph-play-circle"></i></div>
        <div class="dash-brand-text">PZ<span>8</span></div>
      </div>
      <div class="dash-clock" id="dash-clock">--:--:--</div>
      <div class="dash-clock-date" id="dash-clock-date"></div>
      <div class="dash-clock-divider"></div>

      <!-- Profilo Attivo -->
      <div class="dash-profile-box" style="background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 0.75rem; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.2rem; position: relative;">
        <div class="dash-profile-avatar" style="width: 34px; height: 34px; border-radius: 8px; background: <?= $active_profile['color'] ?>15; display: flex; align-items: center; justify-content: center; border: 1px solid <?= $active_profile['color'] ?>40; flex-shrink: 0;">
          <i class="ph <?= htmlspecialchars($active_profile['avatar']) ?>" style="color: <?= $active_profile['color'] ?>; font-size: 1.1rem;"></i>
        </div>
        <div class="dash-profile-info" style="display: flex; flex-direction: column; min-width: 0; flex: 1;">
          <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($active_profile['name']) ?></span>
          <span style="font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Profilo attivo</span>
        </div>

      </div>

      <div class="dash-cat-title">
        Categorie
        <button id="sidebar-toggle" class="sidebar-toggle" title="Nascondi sidebar">
          <i class="ph ph-sidebar-simple"></i>
        </button>
      </div>
      <div class="dash-cat-list" id="dash-cat-list">
        <!-- Categories injected here -->
      </div>
      <a id="btn-guida-tv" href="guida.php" class="dash-exit"><i class="ph ph-calendar"></i> GUIDA TV</a>
      <!-- Settings Trigger -->
      <button id="open-settings-btn" onclick="document.getElementById('settings-modal').classList.add('open')" class="dash-exit" style="margin-top: 0.8rem; margin-bottom: 0; padding: 0.6rem; background: var(--bg-input); border-color: var(--border-subtle); color: var(--text-primary); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%;"><i class="ph ph-gear"></i> Impostazioni</button>
    </aside>

    <!-- Tasto per riaprire la sidebar (visibile solo quando ÃƒÂ¨ nascosta) -->
    <button id="sidebar-show" class="sidebar-show-btn" title="Mostra sidebar">
      <i class="ph ph-sidebar-simple"></i>
    </button>

    <!-- Player Area -->
    <div class="dash-player-area" id="dash-player-area">
      <!-- Tasto Fullscreen Custom (visibile al passaggio del mouse) -->
      <button id="btn-custom-fullscreen" class="custom-fullscreen-btn" title="Schermo intero (doppio clic qui)">
        <i class="ph ph-corners-out"></i>
      </button>

      <!-- Scudo trasparente per catturare il mousemove senza bloccare i controlli video in basso -->
      <div id="player-top-shield" class="player-top-shield"></div>

      <!-- OVERLAY IN STILE TV PREMIUM (visibile solo a schermo intero) -->
      <div id="pc-fullscreen-overlay" class="pc-fullscreen-overlay">
        <div class="pc-glass-pill">
            <div class="pc-overlay-icon" id="pc-overlay-icon">
               <i class="ph-fill ph-television"></i>
            </div>
            <div class="pc-overlay-info">
                <div class="pc-overlay-name" id="pc-overlay-name">Nessun Canale</div>
                <div class="pc-overlay-epg" id="pc-overlay-epg"><span class="epg-label">In onda:</span> Programmazione in corso</div>
            </div>
        </div>
      </div>

      <iframe id="player-frame" src="about:blank" allow="autoplay; encrypted-media"></iframe>
    </div>

    <!-- Info Area -->
    <div class="dash-info-area">
      <div class="dash-info-card">
        <h1 class="dash-info-title" style="display:flex;align-items:center;justify-content:space-between;width:100%;gap:1rem;">
          <span id="player-ch-name">Caricamento...</span>
          <button id="btn-toggle-favorite" class="btn-favorite-toggle" style="display:none;" title="Aggiungi ai preferiti">
            <i class="ph ph-star"></i>
          </button>
        </h1>
        <div class="dash-info-meta" id="player-ch-meta"></div>

        <div id="player-ch-now-container">
          <div class="dash-info-now-box">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <span style="color: #00e676; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-size: 0.7rem; background: rgba(0, 230, 118, 0.1); padding: 2px 8px; border-radius: 4px;">IN ONDA LIVE</span>
              <span style="font-family: var(--font-alt); font-size: 0.8rem; color: var(--text-secondary); font-weight: 700;">--:--</span>
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-primary); line-height: 1.3;">Programmazione in corso</div>
          </div>
        </div>

        <!-- A SEGUIRE container -->
        <div id="player-ch-next-container">
          <div class="dash-info-next-box">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <span style="color: #60a5fa; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-size: 0.7rem; background: rgba(96, 165, 250, 0.1); padding: 2px 8px; border-radius: 4px;">A SEGUIRE</span>
              <span style="font-family: var(--font-alt); font-size: 0.8rem; color: var(--text-secondary); font-weight: 700;">--:--</span>
            </div>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); line-height: 1.3;">Programma successivo in arrivo</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Panels -->
    <div class="dash-panel dash-guide-panel" style="grid-column: <?= $is_kids_profile ? '2 / -1' : '2 / span 2' ?>; grid-row: 2;">
      <div class="dash-panel-header guide"
        style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1.5rem; border-bottom: 1px solid var(--border-subtle);">
        <div style="display: flex; align-items: center; gap: 0.8rem;">
          <span class="dot" style="background: #ef4444; width: 10px; height: 10px; border-radius: 50%; display: inline-block;"></span>
          <span id="panel-cat-title" style="color: #60a5fa; font-weight: 900; font-size: 0.85rem; letter-spacing: 0.5px; text-transform: uppercase;">CANALI LIVE</span>
          <span style="background: rgba(255,255,255,0.08); padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; color: var(--text-secondary);" id="ch-count">0</span>
        </div>

        <div style="display: flex; align-items: center; gap: 1rem;">
          <div class="dash-search-container" style="position: relative; width: 300px;">
            <i class="ph ph-magnifying-glass"
              style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1rem; pointer-events: none;"></i>
            <input type="text" id="dash-search" placeholder="Cerca canale, categoria o evento..."
              style="width: 100%; background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 6px 12px 6px 36px; color: var(--text-primary); font-size: 0.85rem;" />
          </div>


        </div>
      </div>

      <div class="dash-panel-content" id="dash-ch-list" style="padding: 1rem; gap: 0.5rem;">
        <!-- Channels & EPG items injected dynamically -->
      </div>
    </div>

    <!-- Agenda Eventi Panel -->
    <?php if (!$is_kids_profile): ?>
    <div class="dash-panel dash-agenda-panel" style="grid-column: 4; grid-row: 2;">
      <div class="dash-panel-header agenda" style="display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1.5rem; border-bottom: 1px solid var(--border-subtle);">
        <div style="display: flex; align-items: center; gap: 0.8rem;">
          <span class="dot" style="background: #eab308; width: 10px; height: 10px; border-radius: 50%; display: inline-block;"></span>
          <span style="color: #eab308; font-weight: 900; font-size: 0.85rem; letter-spacing: 0.5px; text-transform: uppercase;">AGENDA EVENTI</span>
        </div>
      </div>
      <div class="dash-panel-content" id="dash-agenda-list" style="padding: 1rem; gap: 0.5rem; overflow-y: auto;">
        <!-- Events populated dynamically by JavaScript from agenda.json -->
      </div>
    </div>
    <?php endif; ?>
  </div>

  <script src="js/channels.js?v=<?= time() ?>"></script>
  <?php 
    $pname_check = isset($active_profile['name']) ? strtolower($active_profile['name']) : '';
    $pid_check = isset($active_profile['id']) ? strtolower($active_profile['id']) : '';
    $is_kids_profile = (
        strpos($pname_check, 'bambini') !== false || 
        strpos($pname_check, 'kids') !== false || 
        strpos($pid_check, 'bambini') !== false || 
        strpos($pid_check, 'kids') !== false
    );
  ?>
  <script>
    if (typeof CHANNELS !== 'undefined') {
        const _activeProf = <?= json_encode($active_profile) ?>;
        let allowedCats = _activeProf.allowed_categories || [];
        let allowedChs = _activeProf.allowed_channels || [];

        if (allowedCats.length === 0 && allowedChs.length === 0) {
            if (<?= $is_kids_profile ? 'true' : 'false' ?>) {
                allowedCats = ['kids']; // RetrocompatibilitÃƒÂ  profili bambini vecchi
            } else {
                allowedCats = ['*'];
            }
        }

        // Se un vecchio profilo ha salvato 'bambini', mappiamo a 'kids'
        if (allowedCats.includes('bambini')) {
            allowedCats = allowedCats.map(c => c === 'bambini' ? 'kids' : c);
        }

        if (!allowedCats.includes('*')) {
            CHANNELS = CHANNELS.filter(c => allowedCats.includes(c.cat) || allowedChs.includes(c.id));

            for (let k in CATEGORIES) {
                if (k === 'all') continue;
                if (!CHANNELS.some(c => c.cat === k)) {
                    delete CATEGORIES[k];
                }
            }
        }
    }
  </script>
  <script>
    // Ã¢â€â‚¬Ã¢â€â‚¬ Clock + Date Ã¢â€â‚¬Ã¢â€â‚¬
    function updateClock() {
      const now = new Date();
      const use12Hour = localStorage.getItem('clock_format') === '12';
      document.getElementById('dash-clock').textContent = now.toLocaleTimeString('it-IT', { hour12: use12Hour, hour: '2-digit', minute: '2-digit', second: '2-digit' }).toUpperCase();
      const dateEl = document.getElementById('dash-clock-date');
      if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric', month: 'short' });
      }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Inizializza impostazioni orologio
    document.addEventListener('DOMContentLoaded', () => {
      const clockFormatSelect = document.getElementById('clock-format-select');
      if (clockFormatSelect) {
        const savedFormat = localStorage.getItem('clock_format') || '24';
        clockFormatSelect.value = savedFormat;
        clockFormatSelect.addEventListener('change', (e) => {
          localStorage.setItem('clock_format', e.target.value);
          updateClock();
        });
      }
    });

    // Ripristina stati salvati
    const layout = document.querySelector('.dashboard-layout');
    const sidebarHidden = localStorage.getItem('sidebar_hidden') === 'true';
    const guideExpanded = localStorage.getItem('guide_expanded') === 'true';
    if (sidebarHidden) layout.classList.add('sidebar-hidden');
    if (guideExpanded) {
      layout.classList.add('guide-expanded');
      const guideToggle = document.getElementById('guide-expand-toggle');
      if (guideToggle) {
        const icon = guideToggle.querySelector('i');
        const text = guideToggle.querySelector('span');
        if (icon && text) { icon.className = 'ph ph-arrows-in-simple'; text.textContent = 'Compatta'; }
      }
    }

    // Sidebar Toggle (dentro la sidebar Ã¢â‚¬â€ nasconde)
    const sidebarToggleBtn = document.getElementById('sidebar-toggle');
    // Tasto esterno Ã¢â‚¬â€ riapre la sidebar quando ÃƒÂ¨ nascosta
    const sidebarShowBtn = document.getElementById('sidebar-show');

    function setSidebarState(hidden) {
      if (hidden) {
        layout.classList.add('sidebar-hidden');
      } else {
        layout.classList.remove('sidebar-hidden');
      }
      localStorage.setItem('sidebar_hidden', hidden ? 'true' : 'false');
    }

    if (sidebarToggleBtn) {
      sidebarToggleBtn.addEventListener('click', function () {
        setSidebarState(true);
      });
    }

    if (sidebarShowBtn) {
      // Pulse sul tasto "riapri" se la sidebar era giÃƒÂ  nascosta al caricamento
      if (localStorage.getItem('sidebar_hidden') === 'true') {
        sidebarShowBtn.style.animation = 'sidebar-pulse 2.4s ease-in-out infinite';
      }
      sidebarShowBtn.addEventListener('click', function () {
        setSidebarState(false);
        sidebarShowBtn.style.animation = 'none';
      });
    }

    // Guide Expand Toggle
    const guideToggle = document.getElementById('guide-expand-toggle');
    if (guideToggle) {
      guideToggle.addEventListener('click', function() {
        const isExpanded = layout.classList.toggle('guide-expanded');
        localStorage.setItem('guide_expanded', isExpanded ? 'true' : 'false');
        const icon = guideToggle.querySelector('i');
        const text = guideToggle.querySelector('span');
        if (isExpanded) { icon.className = 'ph ph-arrows-in-simple'; text.textContent = 'Compatta'; }
        else             { icon.className = 'ph ph-arrows-out-simple'; text.textContent = 'Espandi'; }
      });
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬ Canale iniziale da URL Ã¢â€â‚¬Ã¢â€â‚¬
    const params = new URLSearchParams(window.location.search);
    let currentChannel = null;
    
    // Determina la categoria di default: 'all' (Tutte) se disponibile, altrimenti la prima disponibile
    const defaultCatKey = Object.keys(CATEGORIES).includes('all') ? 'all' : Object.keys(CATEGORIES)[0];
    let currentCat = defaultCatKey;

    // Pulisce parametri URL su caricamento fresco/ricaricamento per allineamento visivo
    if (window.location.search) {
      history.replaceState({}, '', window.location.pathname);
    }

    // Ã¢â€ â‚¬Ã¢â€ â‚¬ EPG: usa i dati iniettati da PHP, fetch() solo per aggiornamento Ã¢â€ â‚¬Ã¢â€ â‚¬
    let epgData = window.__EPG_DATA__ || [];
    let epgMap = null;
    let favorites = window.__ACTIVE_PROFILE_FAVORITES__ || [];
    window.favorites = favorites;
    const EPG_CACHE_KEY    = 'pz8_epg_cache';
    const EPG_CACHE_TS_KEY = 'pz8_epg_cache_ts';
    const EPG_CACHE_MAX_AGE = 120000; // 2 minuti

    function timeToMinutes(timeStr) {
      if (!timeStr || !timeStr.includes(':')) return 0;
      const [hours, minutes] = timeStr.split(':').map(Number);
      return (hours * 60) + minutes;
    }

    function buildEpgMap() {
      epgMap = new Map();
      if (!epgData || epgData.length === 0) return;
      for (let i = 0; i < epgData.length; i++) {
        const item = epgData[i];
        if (item.canale) epgMap.set(item.canale.toUpperCase(), item);
      }
    }

    function saveEpgToCache() {
      try {
        sessionStorage.setItem(EPG_CACHE_KEY, JSON.stringify(epgData));
        sessionStorage.setItem(EPG_CACHE_TS_KEY, String(Date.now()));
      } catch(e) {}
    }

    // Inizializza con dati PHP (istantaneo)
    buildEpgMap();

    // Ã¢â€â‚¬Ã¢â€â‚¬ Sidebar Categorie Ã¢â€â‚¬Ã¢â€â‚¬
    const catList = document.getElementById('dash-cat-list');

    function restoreIconColors() {
      document.querySelectorAll('.dash-cat-item').forEach(el => {
        el.classList.remove('active');
        const icon = el.querySelector('i');
        if (icon) icon.style.color = el.dataset.color || '';
      });
    }

    Object.keys(CATEGORIES).forEach(key => {
      if (key === 'all') return;
      const c = CATEGORIES[key];
      const count = getChannelsByCategory(key).length;
      const a = document.createElement('a');
      a.href = '#';
      a.dataset.color = c.color;
      a.className = 'dash-cat-item' + (key === currentCat ? ' active' : '');
      a.innerHTML = `<i class="ph ${c.icon}" style="color: ${key === currentCat ? 'var(--text-primary)' : c.color}"></i> ${c.label} <span class="dash-cat-count">${count}</span>`;
      a.onclick = (e) => {
        e.preventDefault();
        renderChannels(key);
        restoreIconColors();
        a.classList.add('active');
        const activeIcon = a.querySelector('i');
        if (activeIcon) activeIcon.style.color = 'var(--text-primary)';
        currentCat = key;
      };
      catList.appendChild(a);
    });

    let pName = window.__ACTIVE_PROFILE_NAME__ ? window.__ACTIVE_PROFILE_NAME__.toLowerCase() : '';
    let pId   = window.__ACTIVE_PROFILE_ID__ ? window.__ACTIVE_PROFILE_ID__.toLowerCase() : '';
    if (!pName.includes('bambini') && !pName.includes('kids') && !pId.includes('bambini') && !pId.includes('kids')) {
      const aAll = document.createElement('a');
      aAll.href = '#';
      aAll.dataset.color = '#FFFF00';
      aAll.className = 'dash-cat-item' + ('all' === currentCat ? ' active' : '');
      const allCount = CHANNELS.length;
      aAll.innerHTML = `<i class="ph ph-squares-four" style="color: ${'all' === currentCat ? 'var(--text-primary)' : '#FFFF00'}"></i> Tutte <span class="dash-cat-count">${allCount}</span>`;
      aAll.onclick = (e) => {
        e.preventDefault();
        renderChannels('all');
        restoreIconColors();
        aAll.classList.add('active');
        const activeIcon = aAll.querySelector('i');
        if (activeIcon) activeIcon.style.color = 'var(--text-primary)';
        currentCat = 'all';
      };
      catList.insertBefore(aAll, catList.firstChild);
    }

    // Categoria Virtuale "I Miei Preferiti"
    const aFav = document.createElement('a');
    aFav.href = '#';
    aFav.dataset.color = '#ffc107';
    aFav.className = 'dash-cat-item' + ('favorites' === currentCat ? ' active' : '');
    aFav.innerHTML = `<i class="ph ph-star" style="color: ${'favorites' === currentCat ? 'var(--text-primary)' : '#ffc107'}"></i> Preferiti <span class="dash-cat-count" id="fav-count">${favorites.length}</span>`;
    aFav.onclick = (e) => {
      e.preventDefault();
      renderChannels('favorites');
      restoreIconColors();
      aFav.classList.add('active');
      const activeIcon = aFav.querySelector('i');
      if (activeIcon) activeIcon.style.color = 'var(--text-primary)';
      currentCat = 'favorites';
    };
    catList.insertBefore(aFav, catList.firstChild);

    // Ã¢â€â‚¬Ã¢â€â‚¬ Render Canali Ã¢â€â‚¬Ã¢â€â‚¬
    function renderChannels(catKey, searchQuery = '') {
      const list = document.getElementById('dash-ch-list');
      if (!list) return;
      list.innerHTML = '';

      let filtered = getChannelsByCategory(catKey);
      const query = searchQuery.toLowerCase().trim();

      if (query) {
        filtered = filtered.filter(ch => {
          const chNameMatch  = ch.name.toLowerCase().includes(query);
          const chCatMatch   = ch.cat.toLowerCase().includes(query);
          const catLabelMatch = (CATEGORIES[ch.cat] && CATEGORIES[ch.cat].label.toLowerCase().includes(query));
          if (chNameMatch || chCatMatch || catLabelMatch) return true;
          const channelEpgObj = epgMap ? epgMap.get(ch.name.toUpperCase()) : null;
          if (channelEpgObj && channelEpgObj.programmi) {
            return channelEpgObj.programmi.some(p => p.titolo.toLowerCase().includes(query));
          }
          return false;
        });
      }

      document.getElementById('ch-count').textContent = filtered.length;

      const panelTitleEl = document.getElementById('panel-cat-title');
      if (panelTitleEl) {
        panelTitleEl.textContent = catKey === 'all' ? 'TUTTI I CANALI'
          : (catKey === 'favorites' ? 'I MIEI PREFERITI'
          : (CATEGORIES[catKey] ? 'CANALI ' + CATEGORIES[catKey].label.toUpperCase() : 'CANALI LIVE'));
      }

      if (filtered.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'dash-empty-state';
        emptyDiv.innerHTML = `
          <i class="ph ph-magnifying-glass"></i>
          <div class="dash-empty-title">Nessun canale trovato</div>
          <div class="dash-empty-hint">${query ? 'Nessun risultato per "' + query + '"' : 'Nessun canale in questa categoria'}</div>
        `;
        list.appendChild(emptyDiv);
        return;
      }

      const fragment = document.createDocumentFragment();
      const nowDate    = new Date();
      const nowMinutes = (nowDate.getHours() * 60) + nowDate.getMinutes();

      for (let i = 0; i < filtered.length; i++) {
        const ch       = filtered[i];
        const isPlaying = currentChannel && ch.id === currentChannel.id;
        const a        = document.createElement('a');
        a.href         = `?id=${ch.id}`;
        a.className    = 'dash-ch-row' + (isPlaying ? ' active' : '');

        a.addEventListener('click', (e) => { e.preventDefault(); selectChannel(ch); });

        const channelEpg   = getChannelEpg(ch.name);
        const currentProgram = channelEpg.now;
        const cMeta        = CATEGORIES[ch.cat];
        const catColor     = cMeta ? cMeta.color : '#888';
        a.style.setProperty('--ch-hover-accent', catColor);

        let searchMatchHtml = '';
        const channelEpgObj = epgMap ? epgMap.get(ch.name.toUpperCase()) : null;
        if (query && channelEpgObj && channelEpgObj.programmi) {
          const isChNameMatch    = ch.name.toLowerCase().includes(query);
          const isChCatMatch     = ch.cat.toLowerCase().includes(query) || (cMeta && cMeta.label.toLowerCase().includes(query));
          const isCurrentMatch   = currentProgram && currentProgram.titolo.toLowerCase().includes(query);
          const isNextMatch      = channelEpg.next && channelEpg.next.titolo.toLowerCase().includes(query);
          if (!isChNameMatch && !isChCatMatch && !isCurrentMatch && !isNextMatch) {
            const matchedProg = channelEpgObj.programmi.find(p => p.titolo.toLowerCase().includes(query));
            if (matchedProg) {
              searchMatchHtml = `<div class="dash-ch-search-match" style="font-size:0.75rem;color:#10b981;font-weight:700;margin-top:3px;display:inline-flex;align-items:center;gap:3px;">
                <i class="ph ph-calendar-check" style="font-size:0.85rem"></i>
                In Guida: ${matchedProg.ora} - ${matchedProg.titolo}
              </div>`;
            }
          }
        }

        const isFav = favorites.includes(ch.id);
        let html = `<div class="dash-ch-num">${ch.id}</div>`;
        html += `<div class="dash-ch-icon" style="background:${catColor}15;color:${catColor}"><i class="ph ${ch.icon}"></i></div>`;
        html += `<div class="dash-ch-col-info"><div class="dash-ch-name" style="display:flex;align-items:center;justify-content:space-between;width:100%;gap:6px;">`;
        html += `<span style="display:inline-flex;align-items:center;gap:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">`;
        if (isPlaying) html += `<span class="dash-ch-live-badge">LIVE</span>`;
        html += `${ch.name}</span>`;
        html += `<button class="ch-fav-star-btn" onclick="event.stopPropagation(); event.preventDefault(); toggleFavorite(${ch.id});" style="background:transparent;border:none;color:${isFav ? '#ffc107' : 'rgba(255,255,255,0.22)'};cursor:pointer;padding:4px;display:inline-flex;align-items:center;justify-content:center;transition:all 0.2s;outline:none;" title="${isFav ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'}"><i class="${isFav ? 'ph-fill ph-star' : 'ph ph-star'}" style="font-size:1.15rem;"></i></button>`;
        html += `</div><div class="dash-ch-cat" style="color:${catColor}">${cMeta ? cMeta.label : ch.cat}</div>${searchMatchHtml}</div>`;

        if (currentProgram) {
          let startMin = timeToMinutes(currentProgram.ora);
          let endMin   = channelEpg.next ? timeToMinutes(channelEpg.next.ora) : startMin + 120;
          if (endMin < startMin) endMin += 1440;
          let adjNow = nowMinutes;
          if (adjNow < startMin && startMin > 1000) adjNow += 1440;

          const dur = endMin - startMin;
          const rem = Math.max(0, endMin - adjNow);
          const pct = dur > 0 ? Math.min(100, Math.max(0, ((adjNow - startMin) / dur) * 100)) : 100;

          html += `<div class="dash-ch-col-epg">`;
          html += `<div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px">`;
          html += `<span style="font-weight:700;font-size:0.95rem;color:${isPlaying ? 'var(--danger)' : 'var(--text-primary)'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${currentProgram.titolo}</span>`;
          html += `<span style="font-family:var(--font-alt);font-size:0.8rem;color:var(--accent);font-weight:700;flex-shrink:0">${currentProgram.ora}</span></div>`;
          html += `<div style="display:flex;align-items:center;gap:10px;margin-top:2px">`;
          html += `<div style="flex:1;height:4px;background:#1a1a24;border-radius:99px;overflow:hidden"><div style="width:${pct}%;height:100%;background:linear-gradient(90deg,#ef4444,#b91c1c);border-radius:99px"></div></div>`;
          html += `<span style="font-size:0.72rem;color:var(--text-muted);flex-shrink:0;font-weight:600">${rem > 0 ? 'Mancano ' + rem + ' min' : 'In conclusione'}</span></div></div>`;

        } else {
          html += `<div class="dash-ch-col-epg" style="justify-content:center;display:flex;flex-direction:column"><div style="font-size:0.85rem;color:var(--text-muted);font-style:italic;font-weight:500"></div><div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px"></div></div>`;
        }

        a.innerHTML = html;
        fragment.appendChild(a);
      }
      list.appendChild(fragment);
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬ EPG Lookup Ã¢â€â‚¬Ã¢â€â‚¬
    function getChannelEpg(channelName) {
      if (!epgMap || epgMap.size === 0) return { now: null, next: null };
      const channelEpg = epgMap.get(channelName.toUpperCase());
      if (!channelEpg || !channelEpg.programmi || channelEpg.programmi.length === 0) return { now: null, next: null };

      const now            = new Date();
      const currentMinutes = (now.getHours() * 60) + now.getMinutes();
      let activeIndex      = -1;

      for (let i = 0; i < channelEpg.programmi.length; i++) {
        if (timeToMinutes(channelEpg.programmi[i].ora) <= currentMinutes) { activeIndex = i; }
        else break;
      }
      if (activeIndex === -1 && channelEpg.programmi.length > 0) activeIndex = 0;

      return {
        now: channelEpg.programmi[activeIndex],
        next: activeIndex + 1 < channelEpg.programmi.length ? channelEpg.programmi[activeIndex + 1] : null
      };
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬ Aggiornamento Info Player Ã¢â€â‚¬Ã¢â€â‚¬
    function updatePlayerEpg() {
      if (!currentChannel) return;
      const epg           = getChannelEpg(currentChannel.name);
      const nowContainer  = document.getElementById('player-ch-now-container');
      const nextContainer = document.getElementById('player-ch-next-container');
      if (!nowContainer || !nextContainer) return;

      if (!epg.now) {
        const catMeta = CATEGORIES[currentChannel.cat];
        nowContainer.innerHTML = `
          <div class="dash-info-now-box">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="color:#00e676;font-weight:800;text-transform:uppercase;letter-spacing:1px;font-size:0.7rem;background:rgba(0,230,118,0.1);padding:2px 8px;border-radius:4px;">LIVE</span>
              <span style="font-family:var(--font-alt);font-size:0.8rem;color:var(--text-secondary);font-weight:700;">Live</span>
            </div>
            <div style="font-size:1.1rem;font-weight:800;color:var(--text-primary);line-height:1.3;margin-top:2px;">Eventi ${catMeta ? catMeta.label : currentChannel.cat}</div>
          </div>`;
        nextContainer.innerHTML = `
          <div class="dash-info-next-box">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="color:#60a5fa;font-weight:800;text-transform:uppercase;letter-spacing:1px;font-size:0.7rem;background:rgba(96,165,250,0.1);padding:2px 8px;border-radius:4px;">A SEGUIRE</span>
              <span style="font-family:var(--font-alt);font-size:0.8rem;color:var(--text-secondary);font-weight:700;">Live</span>
            </div>
            <div style="font-size:0.95rem;font-weight:700;color:var(--text-primary);line-height:1.3;margin-top:2px;">Programmazione live continua</div>
          </div>`;
        return;
      }

      const now          = new Date();
      let nowMinutes     = (now.getHours() * 60) + now.getMinutes();
      let startMinutes   = timeToMinutes(epg.now.ora);
      let endMinutes     = epg.next ? timeToMinutes(epg.next.ora) : startMinutes + 120;
      if (endMinutes < startMinutes) endMinutes += 1440;
      if (nowMinutes < startMinutes && startMinutes > 1000) nowMinutes += 1440;

      const duration        = endMinutes - startMinutes;
      let elapsed           = nowMinutes - startMinutes;
      if (elapsed < 0) elapsed = 0;
      let remaining         = endMinutes - nowMinutes;
      if (remaining < 0) remaining = 0;
      const progressPercent = duration > 0 ? Math.min(100, Math.max(0, (elapsed / duration) * 100)) : 100;

      nowContainer.innerHTML = `
        <div class="dash-info-now-box">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="color:#00e676;font-weight:800;text-transform:uppercase;letter-spacing:1px;font-size:0.7rem;background:rgba(0,230,118,0.1);padding:2px 8px;border-radius:4px;display:inline-flex;align-items:center;gap:4px;">
              <span style="width:6px;height:6px;border-radius:50%;background:#00e676;display:inline-block;"></span> LIVE
            </span>
            <span style="font-family:var(--font-alt);font-size:0.8rem;color:var(--accent);font-weight:700;">${epg.now.ora}</span>
          </div>
          <div style="font-size:1.1rem;font-weight:800;color:var(--text-primary);line-height:1.3;margin-top:4px;">${epg.now.titolo}</div>
          ${epg.now.descrizione ? `<div style="font-size:0.85rem;color:var(--text-secondary);margin-top:8px;line-height:1.4;">${epg.now.descrizione}</div>` : ''}
          <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
            <div style="flex:1;height:4px;background:rgba(255,255,255,0.06);border-radius:99px;overflow:hidden;">
              <div style="width:${progressPercent}%;height:100%;background:linear-gradient(90deg,#00e676,#00b0ff);border-radius:99px;box-shadow:0 0 6px rgba(0,230,118,0.3);"></div>
            </div>
            <span style="font-size:0.72rem;color:var(--text-muted);font-weight:600;flex-shrink:0;">${remaining > 0 ? 'Mancano ' + remaining + ' min' : 'In conclusione'}</span>
          </div>
        </div>`;

      if (epg.next) {
        nextContainer.innerHTML = `
          <div class="dash-info-next-box">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="color:#60a5fa;font-weight:800;text-transform:uppercase;letter-spacing:1px;font-size:0.7rem;background:rgba(96,165,250,0.1);padding:2px 8px;border-radius:4px;">A SEGUIRE</span>
              <span style="font-family:var(--font-alt);font-size:0.8rem;color:var(--text-secondary);font-weight:700;">${epg.next.ora}</span>
            </div>
            <div style="font-size:0.95rem;font-weight:700;color:var(--text-primary);line-height:1.3;margin-top:4px;">${epg.next.titolo}</div>
            ${epg.next.descrizione ? `<div style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;line-height:1.3;">${epg.next.descrizione}</div>` : ''}
          </div>`;
      } else {
        nextContainer.innerHTML = `
          <div class="dash-info-next-box">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="color:var(--text-muted);font-weight:800;text-transform:uppercase;letter-spacing:1px;font-size:0.7rem;background:rgba(255,255,255,0.05);padding:2px 8px;border-radius:4px;">A SEGUIRE</span>
              <span style="font-family:var(--font-alt);font-size:0.8rem;color:var(--text-secondary);font-weight:700;">--:--</span>
            </div>
            <div style="font-size:0.95rem;font-weight:700;color:var(--text-muted);font-style:italic;line-height:1.3;margin-top:4px;">Nessun programma successivo</div>
          </div>`;
      }

      // Aggiorna anche l'overlay fullscreen del PC
      const pcOverlayEpg = document.getElementById('pc-overlay-epg');
      if (pcOverlayEpg) {
        pcOverlayEpg.innerHTML = epg.now ? `<span class="epg-label">In onda:</span> <span class="epg-title">${epg.now.titolo}</span>` : '<span class="epg-label">In onda:</span> <span class="epg-title">Diretta continua</span>';
      }
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬ Fetch in background (aggiorna ogni 60s) Ã¢â€â‚¬Ã¢â€â‚¬
    async function fetchEpgData() {
      try {
        const response = await fetch('epg.php');
        if (!response.ok) throw new Error('EPG fetch failed');
        epgData = await response.json();
        buildEpgMap();
        saveEpgToCache();
        renderChannels(currentCat);
        updatePlayerEpg();
      } catch(err) {
        console.warn('Aggiornamento EPG fallito, uso dati esistenti:', err);
      }
    }

    // Search
    document.getElementById('dash-search').addEventListener('input', (e) => {
      renderChannels(currentCat, e.target.value);
    });

    // Ã¢â€â‚¬Ã¢â€â‚¬ Renderizza Agenda Ã¢â€â‚¬Ã¢â€â‚¬
    function renderAgenda() {
      const container = document.getElementById('dash-agenda-list');
      if (!container) return;
      container.innerHTML = '';
      
      const events = window.__AGENDA_DATA__ || [];
      if (events.length === 0) {
        container.innerHTML = `<div style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 2rem 0;">Nessun evento in agenda per oggi</div>`;
        return;
      }

      events.forEach(event => {
        let channelIds = [];
        if (Array.isArray(event.channel_id)) {
          channelIds = event.channel_id;
        } else if (event.channel_id) {
          channelIds = [event.channel_id];
        }

        const channels = channelIds.map(id => getChannelById(id)).filter(ch => !!ch);
        const primaryColor = channels.length > 0 ? (CATEGORIES[channels[0].cat] ? CATEGORIES[channels[0].cat].color : '#eab308') : '#eab308';

        const row = document.createElement('div');
        row.className = 'dash-event-row';
        row.style.borderLeftColor = primaryColor;
        if (channels.length > 0) {
          row.setAttribute('onclick', `selectChannel(getChannelById(${channels[0].id}));`);
        }

        let badgesHtml = '';
        channels.forEach(ch => {
          const catColor = (CATEGORIES[ch.cat]) ? CATEGORIES[ch.cat].color : '#eab308';
          badgesHtml += `
            <span onclick="event.stopPropagation(); selectChannel(getChannelById(${ch.id}));" 
                  style="background: ${catColor}15; color: ${catColor}; border: 1px solid ${catColor}40; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;"
                  onmouseover="this.style.background='${catColor}30'" 
                  onmouseout="this.style.background='${catColor}15'">
              <i class="ph ${ch.icon}" style="font-size: 0.8rem;"></i> ${ch.name}
            </span>
          `;
        });

        if (badgesHtml === '') {
          badgesHtml = `<span style="color: #eab308; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">${event.channel_name || 'Sport'}</span>`;
        }
        
        row.innerHTML = `
          <div class="dash-event-time" style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center; width: 100%;">
            ${badgesHtml}
            <span style="margin-left: auto; font-family: var(--font-alt); font-size: 0.75rem; color: var(--text-muted); font-weight: 700; flex-shrink: 0;">${event.time}</span>
          </div>
          <div class="dash-event-title" style="margin-top: 6px;">${event.title}</div>
          <div class="dash-event-desc">${event.desc || ''}</div>
        `;
        container.appendChild(row);
      });
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬ Avvio: rendering immediato con dati PHP, poi fetch in background Ã¢â€â‚¬Ã¢â€â‚¬
    renderChannels(currentCat);
    deselectChannel();
    renderAgenda();
    // Aggiornamento in background dopo 5s, poi ogni 60s
    setTimeout(() => fetchEpgData(), 5000);
    setInterval(() => fetchEpgData(), 60000);

    // Ã¢â€â‚¬Ã¢â€â‚¬ Cambio canale dinamico Ã¢â€â‚¬Ã¢â€â‚¬
    // ─── Deselezione canale (Stato Iniziale / Nessun Canale) ───
    function deselectChannel() {
      currentChannel = null;
      document.title = "PZ8";
      
      const btnFav = document.getElementById('btn-toggle-favorite');
      if (btnFav) btnFav.style.display = 'none';
      
      const chNameEl = document.getElementById('player-ch-name');
      if (chNameEl) chNameEl.textContent = "Nessun Canale";
      
      const metaEl = document.getElementById('player-ch-meta');
      if (metaEl) metaEl.innerHTML = "";
      
      const frame = document.getElementById('player-frame');
      if (frame) frame.src = 'about:blank';

      const nowContainer = document.getElementById('player-ch-now-container');
      if (nowContainer) {
        nowContainer.innerHTML = `
          <div class="dash-info-now-box" style="text-align: center; padding: 1.5rem 1rem;">
            <i class="ph ph-television-simple" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.5rem; display: block;"></i>
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-secondary);">Scegli un canale dalla lista</div>
          </div>`;
      }
      
      const nextContainer = document.getElementById('player-ch-next-container');
      if (nextContainer) {
        nextContainer.innerHTML = "";
      }

      document.querySelectorAll('.dash-ch-row').forEach(row => {
        row.classList.remove('active');
        const badge = row.querySelector('.dash-ch-live-badge');
        if (badge) badge.remove();
      });

      const btnGuidaTv = document.getElementById('btn-guida-tv');
      if (btnGuidaTv) btnGuidaTv.href = 'guida.php';
    }

    async function toggleFavorite(channelId) {
      try {
        const response = await fetch('toggle_favorite.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
          },
          body: JSON.stringify({ channel_id: channelId })
        });
        if (!response.ok) throw new Error('Toggle favorite failed');
        const resData = await response.json();
        if (resData.success) {
          favorites = resData.favorites || [];
          window.favorites = favorites;
          
          // Aggiorna il contatore della categoria
          const favCountEl = document.getElementById('fav-count');
          if (favCountEl) favCountEl.textContent = favorites.length;
          
          // Aggiorna il pulsante se il canale modificato è quello attivo nel player
          if (currentChannel && currentChannel.id === channelId) {
            const btnFav = document.getElementById('btn-toggle-favorite');
            if (btnFav) {
              const isFav = favorites.includes(channelId);
              btnFav.className = 'btn-favorite-toggle' + (isFav ? ' is-favorite' : '');
              btnFav.querySelector('i').className = isFav ? 'ph-fill ph-star' : 'ph ph-star';
            }
          }
          
          // Re-renderizza i canali per aggiornare le icone stella
          renderChannels(currentCat, document.getElementById('ch-search') ? document.getElementById('ch-search').value : '');
        } else {
          console.error('Errore durante la modifica dei preferiti:', resData.error);
        }
      } catch (err) {
        console.error('Errore di connessione durante la modifica dei preferiti:', err);
      }
    }

    function selectChannel(ch) {
      if (currentChannel && currentChannel.id === ch.id) return;

      document.querySelectorAll('.dash-ch-row').forEach(row => {
        row.classList.remove('active');
        const badge = row.querySelector('.dash-ch-live-badge');
        if (badge) badge.remove();
      });

      currentChannel = ch;

      document.querySelectorAll('.dash-ch-row').forEach(row => {
        if (row.getAttribute('href') === `?id=${ch.id}`) {
          row.classList.add('active');
          const nameEl = row.querySelector('.dash-ch-name');
          if (nameEl && !nameEl.querySelector('.dash-ch-live-badge')) {
            const badgeSpan = document.createElement('span');
            badgeSpan.className = 'dash-ch-live-badge';
            badgeSpan.textContent = 'LIVE';
            nameEl.insertBefore(badgeSpan, nameEl.firstChild);
          }
        }
      });

      document.title = ch.name + ' ';
      document.getElementById('player-ch-name').textContent = ch.name;

      // Aggiorna l'overlay fullscreen del PC
      const pcOverlayName = document.getElementById('pc-overlay-name');
      if (pcOverlayName) pcOverlayName.textContent = ch.name;
      const pcOverlayEpg = document.getElementById('pc-overlay-epg');
      if (pcOverlayEpg) pcOverlayEpg.innerHTML = "<span class='epg-label'>In onda:</span> <span class='epg-title'>Caricamento...</span>";

      // Aggiorna bottone preferiti del player
      const btnFav = document.getElementById('btn-toggle-favorite');
      if (btnFav) {
        btnFav.style.display = 'inline-flex';
        const isFav = favorites.includes(ch.id);
        btnFav.className = 'btn-favorite-toggle' + (isFav ? ' is-favorite' : '');
        btnFav.querySelector('i').className = isFav ? 'ph-fill ph-star' : 'ph ph-star';
        btnFav.onclick = () => toggleFavorite(ch.id);
      }

      const chMeta = CATEGORIES[ch.cat];
      const metaEl = document.getElementById('player-ch-meta');
      if (metaEl && chMeta) {
        metaEl.innerHTML = `
          <span class="dash-info-ch-num">CH ${ch.id}</span>
          <span class="dash-info-cat-badge" style="background:${chMeta.color}20;color:${chMeta.color};border:1px solid ${chMeta.color}40;">
            <i class="ph ${chMeta.icon}" style="margin-right:3px;vertical-align:middle;"></i>${chMeta.label}
          </span>`;
      }

      const frame = document.getElementById('player-frame');
      if (frame) frame.src = getStreamUrl(ch);

      updatePlayerEpg();
      history.pushState({ id: ch.id }, '', `?id=${ch.id}`);

      const btnGuidaTv = document.getElementById('btn-guida-tv');
      if (btnGuidaTv) btnGuidaTv.href = `guida.php?id=${ch.id}`;
    }

    // Back/Forward browser
    window.addEventListener('popstate', (e) => {
      const params = new URLSearchParams(window.location.search);
      const id = parseInt(params.get('id'));
      if (id) {
        const ch = getChannelById(id);
        if (ch) selectChannel(ch);
      } else {
        deselectChannel();
      }
    });

    // ─── GESTIONE FULLSCREEN CUSTOM PER OVERLAY ───
    const playerAreaContainer = document.getElementById('dash-player-area');
    const topShield = document.getElementById('player-top-shield');
    const btnCustomFs = document.getElementById('btn-custom-fullscreen');

    function toggleCustomFullscreen() {
      if (!document.fullscreenElement) {
        if (playerAreaContainer.requestFullscreen) {
          playerAreaContainer.requestFullscreen();
        } else if (playerAreaContainer.webkitRequestFullscreen) {
          playerAreaContainer.webkitRequestFullscreen();
        }
      } else {
        if (document.exitFullscreen) {
          document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
          document.webkitExitFullscreen();
        }
      }
    }

    if (btnCustomFs) {
      btnCustomFs.addEventListener('click', toggleCustomFullscreen);
    }
    
    // Gestisci timeout per far scomparire l'overlay quando il mouse Ã¨ fermo a schermo intero
    let pcFsTimeout;
    const pcOverlay = document.getElementById('pc-fullscreen-overlay');
    playerAreaContainer.addEventListener('mousemove', () => {
      if (document.fullscreenElement === playerAreaContainer) {
        pcOverlay.classList.remove('hidden');
        document.body.style.cursor = 'default';
        btnCustomFs.style.opacity = '1';
        clearTimeout(pcFsTimeout);
        pcFsTimeout = setTimeout(() => {
          pcOverlay.classList.add('hidden');
          document.body.style.cursor = 'none';
          btnCustomFs.style.opacity = '0';
        }, 4000);
      }
    });
    
    document.addEventListener('fullscreenchange', () => {
      if (document.fullscreenElement === playerAreaContainer) {
        // Appena entrati a schermo intero
        pcOverlay.classList.remove('hidden');
        clearTimeout(pcFsTimeout);
        pcFsTimeout = setTimeout(() => {
          pcOverlay.classList.add('hidden');
          document.body.style.cursor = 'none';
          btnCustomFs.style.opacity = '0';
        }, 4000);
      } else {
        // Usciti dallo schermo intero
        pcOverlay.classList.remove('hidden'); // Reset per via del CSS
        document.body.style.cursor = 'default';
        btnCustomFs.style.opacity = '';
        clearTimeout(pcFsTimeout);
      }
    });

  </script>
  <!-- Settings Modal -->
  <div id="settings-modal" class="settings-modal-overlay">
    <div class="settings-modal-content">
      <div class="settings-modal-header" style="flex-direction: column; align-items: stretch; padding-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h2 style="margin: 0;"><i class="ph ph-gear"></i> Impostazioni</h2>
          <button id="close-settings-btn" onclick="document.getElementById('settings-modal').classList.remove('open')" class="settings-modal-close">&times;</button>
        </div>
        
        <div class="settings-tabs-header" style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: -1px;">
          <button class="settings-tab-btn active" data-tab="tab-interfaccia"><i class="ph ph-desktop"></i> Interfaccia</button>
          <button class="settings-tab-btn" data-tab="tab-profilo"><i class="ph ph-user"></i> Profilo</button>
          <button class="settings-tab-btn" data-tab="tab-gestione"><i class="ph ph-users"></i> Gestione</button>
        </div>
      </div>

      <style>
        .settings-tab-btn {
          background: transparent;
          border: none;
          color: var(--text-muted);
          padding: 0.8rem 1rem;
          cursor: pointer;
          font-weight: 600;
          font-size: 0.9rem;
          border-bottom: 2px solid transparent;
          transition: all 0.2s;
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }
        .settings-tab-btn:hover {
          color: var(--text-primary);
        }
        .settings-tab-btn.active {
          color: var(--accent);
          border-bottom-color: var(--accent);
        }
        .settings-tab-pane {
          display: none;
          animation: fadeInTab 0.3s ease;
        }
        .settings-tab-pane.active {
          display: block;
        }
        @keyframes fadeInTab {
          from { opacity: 0; transform: translateY(5px); }
          to { opacity: 1; transform: translateY(0); }
        }
      </style>

      <div class="settings-modal-body" style="padding-top: 1.5rem;">
        
        <!-- TAB INTERFACCIA -->
        <div id="tab-interfaccia" class="settings-tab-pane active">
          <div class="settings-section">
            <h3><i class="ph ph-palette"></i> Aspetto</h3>
            <div class="settings-row" style="margin-bottom: 1rem;">
              <span>Tema dell'interfaccia</span>
              <button id="theme-toggle" class="settings-theme-btn">
                <i class="ph ph-sun"></i> Modalità 
              </button>
            </div>
            
            <div class="settings-row" style="margin-bottom: 1rem; align-items: flex-start; flex-direction: column; gap: 0.8rem;">
              <span style="font-weight: 600;">Colore Principale</span>
              <div id="accent-color-picker" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <!-- Generato via JS -->
              </div>
            </div>

            <div class="settings-row">
              <span>Formato Orologio</span>
              <select id="clock-format-select" style="background: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border-subtle); padding: 0.4rem 0.8rem; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border-subtle)'">
                <option value="24">24 Ore</option>
                <option value="12">12 Ore (AM/PM)</option>
              </select>
            </div>
            
            <div class="settings-row" style="margin-top: 1rem; border-top: 1px solid var(--border-subtle); padding-top: 1rem;">
              <span>Dispositivo / Interfaccia</span>
              <a href="index.php?view_mode=mobile" class="settings-theme-btn" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; text-align: center; justify-content: center; font-weight: 700; color: var(--accent); border-color: var(--accent);">
                <i class="ph ph-phone"></i> Passa a Versione Mobile/TV
              </a>
            </div>
          </div>
        </div>

        <!-- TAB PROFILO -->
        <div id="tab-profilo" class="settings-tab-pane">
          <div class="settings-section" style="margin-bottom: 2.5rem;">
            <div class="premium-profile-card" style="background: linear-gradient(135deg, <?= $active_profile['color'] ?>15 0%, var(--bg-input) 100%); border: 1px solid <?= $active_profile['color'] ?>40; border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; gap: 1.5rem; position: relative; overflow: hidden;">
              <!-- Decorative glow -->
              <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, <?= $active_profile['color'] ?>15 0%, transparent 60%); pointer-events: none;"></div>
              
              <div class="settings-profile-avatar" style="width: 76px; height: 76px; border-radius: 50%; background: var(--bg-surface); display: flex; align-items: center; justify-content: center; border: 2px solid <?= $active_profile['color'] ?>; box-shadow: 0 4px 20px <?= $active_profile['color'] ?>40; flex-shrink: 0; z-index: 1;">
                <i class="ph <?= htmlspecialchars($active_profile['avatar']) ?>" style="color: <?= $active_profile['color'] ?>; font-size: 2.8rem;"></i>
              </div>
              
              <div class="settings-profile-details" style="display: flex; flex-direction: column; gap: 0.4rem; z-index: 1;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                  <span class="settings-profile-name" style="font-weight: 800; color: var(--text-primary); font-size: 1.5rem; letter-spacing: -0.5px;"><?= htmlspecialchars($active_profile['name']) ?></span>
                  <span style="background: var(--accent-glow); color: var(--accent); border: 1px solid var(--accent-glow); padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 0 10px var(--accent-glow);">Attivo</span>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.4rem; background: rgba(0,0,0,0.2); padding: 4px 10px; border-radius: 6px; width: fit-content; border: 1px solid var(--border-subtle);">
                  <i class="ph ph-crown" style="color: #eab308; font-size: 1.1rem;"></i>
                  <span>Scadenza Abbonamento: <strong style="color: #eab308; margin-left: 2px;"><?= date('d/m/Y', strtotime($subscription_expiry)) ?></strong></span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="settings-section">
            <h3 style="margin-bottom: 1rem;"><i class="ph ph-lightning"></i> Azioni Rapide</h3>
            <div class="settings-actions-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <a href="select_profile.php" class="settings-action-btn" style="padding: 1rem; text-align: center; border-radius: 12px; background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary); font-weight: 600; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; transition: all 0.2s; text-decoration: none;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-subtle)'; this.style.transform='translateY(0)';">
                <i class="ph ph-users-three" style="font-size: 2rem; color: var(--accent);"></i>
                Cambia Profilo
              </a>
              <a href="logout.php" class="settings-action-btn logout" style="padding: 1rem; text-align: center; border-radius: 12px; background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.3); color: #f43f5e; font-weight: 600; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; transition: all 0.2s; text-decoration: none;" onmouseover="this.style.background='rgba(244, 63, 94, 0.2)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='rgba(244, 63, 94, 0.1)'; this.style.transform='translateY(0)';">
                <i class="ph ph-sign-out" style="font-size: 2rem;"></i>
                Disconnetti
              </a>
            </div>
          </div>
        </div>

        <!-- TAB GESTIONE PROFILI -->
        <div id="tab-gestione" class="settings-tab-pane">
          <div class="settings-section">
            <h3><i class="ph ph-users"></i> Gestione Profili</h3>
            <div id="profiles-manager-list" style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1rem;">
              <!-- Profils injected by JS -->
            </div>
            <button id="add-profile-btn" style="background: var(--bg-input); border: 1px dashed var(--border-strong); color: var(--text-primary); padding: 0.6rem; border-radius: var(--radius-sm); width: 100%; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: border-color 0.2s;">
              <i class="ph ph-plus-circle"></i> Aggiungi Profilo
            </button>
            <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
              <button id="save-profiles-btn" style="background: var(--accent); color: #000; border: none; padding: 0.6rem 1.2rem; border-radius: var(--radius-sm); font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: opacity 0.2s;">
                <i class="ph ph-floppy-disk"></i> Salva Profili
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script>
    // Theme logic and other quick UI settings
    document.addEventListener('DOMContentLoaded', () => {
        // Accent Colors Logic
        const accentPresets = [
            { name: 'Ciano', hex: '#00f2fe', glow: 'rgba(0, 242, 254, 0.3)' },
            { name: 'Rosso Netflix', hex: '#e50914', glow: 'rgba(229, 9, 20, 0.3)' },
            { name: 'Oro Premium', hex: '#eab308', glow: 'rgba(234, 179, 8, 0.3)' },
            { name: 'Smeraldo', hex: '#10b981', glow: 'rgba(16, 185, 129, 0.3)' },
            { name: 'Viola', hex: '#a855f7', glow: 'rgba(168, 85, 247, 0.3)' },
            { name: 'Rosa', hex: '#ec4899', glow: 'rgba(236, 72, 153, 0.3)' }
        ];

        const pickerContainer = document.getElementById('accent-color-picker');
        let currentAccent = localStorage.getItem('accent_color') || '#00f2fe';

        function renderAccentPicker() {
            if (!pickerContainer) return;
            pickerContainer.innerHTML = '';
            accentPresets.forEach(preset => {
                const isSelected = currentAccent.toLowerCase() === preset.hex.toLowerCase();
                const btn = document.createElement('div');
                btn.style.width = '36px';
                btn.style.height = '36px';
                btn.style.borderRadius = '50%';
                btn.style.background = preset.hex;
                btn.style.cursor = 'pointer';
                btn.style.border = isSelected ? '3px solid #fff' : '2px solid transparent';
                btn.style.boxShadow = isSelected ? `0 0 0 2px ${preset.hex}` : 'none';
                btn.style.transform = isSelected ? 'scale(1.1)' : 'scale(1)';
                btn.style.transition = 'all 0.2s';
                btn.title = preset.name;
                
                btn.onclick = () => {
                    currentAccent = preset.hex;
                    localStorage.setItem('accent_color', preset.hex);
                    localStorage.setItem('accent_glow', preset.glow);
                    document.documentElement.style.setProperty('--accent', preset.hex);
                    document.documentElement.style.setProperty('--accent-glow', preset.glow);
                    renderAccentPicker();
                };
                pickerContainer.appendChild(btn);
            });
        }
        renderAccentPicker();
    });
  </script>

  <script>
    // Tab switching logic
    document.querySelectorAll('.settings-tab-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.settings-tab-pane').forEach(p => p.classList.remove('active'));
        
        const target = e.currentTarget;
        target.classList.add('active');
        document.getElementById(target.getAttribute('data-tab')).classList.add('active');
      });
    });
  </script>

  <script>
    // Profile Management Logic
    (function() {
      let profiles = JSON.parse(JSON.stringify(window.__ALL_PROFILES__ || []));
      window.__CURRENT_EDITING_PROFILES__ = profiles;
      const listContainer = document.getElementById('profiles-manager-list');
      const addBtn = document.getElementById('add-profile-btn');
      const saveBtn = document.getElementById('save-profiles-btn');

      const PRESETS = [
          { avatar: 'ph-smiley', color: '#00e676' },
          { avatar: 'ph-star', color: '#ffea00' },
          { avatar: 'ph-heart', color: '#ff4081' },
          { avatar: 'ph-game-controller', color: '#d500f9' },
          { avatar: 'ph-airplane-tilt', color: '#00b0ff' },
          { avatar: 'ph-music-notes', color: '#ff9100' },
          { avatar: 'ph-coffee', color: '#795548' },
          { avatar: 'ph-cat', color: '#ff6e40' },
          { avatar: 'ph-user-circle', color: '#00f2fe' }
      ];

      function renderProfiles() {
        listContainer.innerHTML = '';
        profiles.forEach((p, index) => {
          const row = document.createElement('div');
          row.style.display = 'flex';
          row.style.alignItems = 'center';
          row.style.gap = '0.8rem';
          row.style.background = 'var(--bg-input)';
          row.style.padding = '0.5rem 0.8rem';
          row.style.borderRadius = 'var(--radius-sm)';
          row.style.border = '1px solid var(--border-subtle)';

          row.innerHTML = `
            <div class="edit-appearance-btn" data-index="${index}" style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-surface); display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid transparent; transition: all 0.2s;" title="Cambia Icona e Colore" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='transparent'">
              <i class="ph ${p.avatar}" style="color: ${p.color}; font-size: 1.1rem;"></i>
            </div>
            <input type="text" value="${p.name.replace(/"/g, '&quot;')}" data-index="${index}" class="profile-name-input" style="flex: 1; background: transparent; border: none; color: var(--text-primary); font-size: 0.9rem; font-weight: 600; outline: none; border-bottom: 1px solid transparent; transition: border-color 0.2s;" onfocus="this.style.borderBottomColor='var(--accent)'" onblur="this.style.borderBottomColor='transparent'">
            <button class="edit-permissions-btn" data-index="${index}" style="background: var(--bg-surface); border: 1px solid var(--border-strong); color: var(--accent); cursor: pointer; padding: 4px 8px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: all 0.2s; font-size: 0.8rem; font-weight: 600;" title="Configura Permessi">
              <i class="ph ph-sliders" style="font-size: 1.1rem; margin-right: 4px;"></i> Permessi
            </button>
            <button class="delete-profile-btn" data-index="${index}" style="background: transparent; border: none; color: #f43f5e; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s;" ${profiles.length <= 1 ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : ''} title="Elimina Profilo">
              <i class="ph ph-trash" style="font-size: 1.1rem;"></i>
            </button>
          `;
          listContainer.appendChild(row);
        });

        // Add event listeners
        document.querySelectorAll('.profile-name-input').forEach(input => {
          input.addEventListener('input', (e) => {
            const idx = e.target.getAttribute('data-index');
            profiles[idx].name = e.target.value.trim() || 'Senza Nome';
          });
        });

        document.querySelectorAll('.delete-profile-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
            if (profiles.length > 1) {
              const idx = e.currentTarget.getAttribute('data-index');
              profiles.splice(idx, 1);
              renderProfiles();
            }
          });
        });

        document.querySelectorAll('.edit-permissions-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
            const idx = e.currentTarget.getAttribute('data-index');
            openPermissionsModal(idx);
          });
        });

        document.querySelectorAll('.edit-appearance-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
            const idx = e.currentTarget.getAttribute('data-index');
            openAppearanceModal(idx);
          });
        });
      }

      addBtn.addEventListener('click', () => {
        if (profiles.length >= 6) {
          alert('Puoi avere al massimo 6 profili.');
          return;
        }
        const preset = PRESETS[Math.floor(Math.random() * PRESETS.length)];
        profiles.push({
          id: '', // Will be generated by server
          name: 'Nuovo Profilo',
          avatar: preset.avatar,
          color: preset.color
        });
        renderProfiles();
      });

      saveBtn.addEventListener('click', async () => {
        saveBtn.style.opacity = '0.7';
        saveBtn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Salvataggio...';
        
        try {
          const res = await fetch('save_profiles.php', {
            method: 'POST',
            headers: { 
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.__CSRF_TOKEN__
            },
            body: JSON.stringify({ profiles, csrf_token: window.__CSRF_TOKEN__ })
          });
          const data = await res.json();
          if (data.success) {
            saveBtn.innerHTML = '<i class="ph ph-check"></i> Salvato!';
            saveBtn.style.background = '#00e676';
            setTimeout(() => {
              window.location.reload(); // Ricarica per applicare le modifiche (incluso il nome nella dashboard e in JS)
            }, 600);
          } else {
            alert('Errore: ' + (data.error || 'Impossibile salvare'));
            saveBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Salva Profili';
            saveBtn.style.opacity = '1';
          }
        } catch (err) {
          alert('Errore di rete.');
          saveBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Salva Profili';
          saveBtn.style.opacity = '1';
        }
      });

      // Listen for modal open to ensure UI is up-to-date
      document.getElementById('open-settings-btn').addEventListener('click', renderProfiles);
      
      // Initial render
      renderProfiles();
    })();
  </script>
    </div>
  </div>

  <div id="permissions-modal" class="settings-modal-overlay">
    <div class="settings-modal-content" style="max-height: 90vh;">
      <div class="settings-modal-header">
        <h2><i class="ph ph-sliders"></i> Permessi Profilo</h2>
        <button id="close-permissions-btn" class="settings-modal-close">&times;</button>
      </div>
      <div class="settings-modal-body" id="permissions-modal-body" style="overflow-y: auto;">
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Seleziona le categorie o i singoli canali visibili per questo profilo.</p>
        <div id="permissions-categories-container" style="display: flex; flex-direction: column; gap: 1rem;">
          <!-- Popolato dinamicamente via JS -->
        </div>
      </div>
      <div style="padding: 1.2rem 1.5rem; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 1rem; background: var(--bg-surface);">
        <button id="cancel-permissions-btn" style="background: transparent; border: 1px solid var(--border-strong); color: var(--text-primary); padding: 0.6rem 1.2rem; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600;">Annulla</button>
        <button id="apply-permissions-btn" style="background: var(--accent); color: #000; border: none; padding: 0.6rem 1.2rem; border-radius: var(--radius-sm); font-weight: 800; cursor: pointer;">Applica</button>
      </div>
    </div>
  </div>

  <div id="appearance-modal" class="settings-modal-overlay">
    <div class="settings-modal-content" style="max-height: 90vh;">
      <div class="settings-modal-header">
        <h2><i class="ph ph-palette"></i> Aspetto Profilo</h2>
        <button id="close-appearance-btn" class="settings-modal-close">&times;</button>
      </div>
      <div class="settings-modal-body" id="appearance-modal-body" style="overflow-y: auto;">
        
        <div style="display: flex; justify-content: center; margin-bottom: 2rem;">
          <div id="appearance-preview" style="width: 80px; height: 80px; border-radius: 50%; background: var(--bg-input); display: flex; align-items: center; justify-content: center; border: 2px solid var(--border-subtle); box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
            <i class="ph ph-user" style="font-size: 3rem; color: #ffffff;"></i>
          </div>
        </div>

        <h3 style="font-size: 0.9rem; margin-bottom: 0.8rem; color: var(--text-muted);">Scegli Icona</h3>
        <div id="appearance-icons" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.8rem; margin-bottom: 2rem;">
          <!-- Generato via JS -->
        </div>

        <h3 style="font-size: 0.9rem; margin-bottom: 0.8rem; color: var(--text-muted);">Scegli Colore</h3>
        <div id="appearance-colors" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.8rem; margin-bottom: 1rem;">
          <!-- Generato via JS -->
        </div>

      </div>
      <div style="padding: 1.2rem 1.5rem; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 1rem; background: var(--bg-surface);">
        <button id="cancel-appearance-btn" style="background: transparent; border: 1px solid var(--border-strong); color: var(--text-primary); padding: 0.6rem 1.2rem; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600;">Annulla</button>
        <button id="apply-appearance-btn" style="background: var(--accent); color: #000; border: none; padding: 0.6rem 1.2rem; border-radius: var(--radius-sm); font-weight: 800; cursor: pointer;">Applica</button>
      </div>
    </div>
  </div>

  <script>
    (function() {
      // Gestione Logica Modale Aspetto
      const appModal = document.getElementById('appearance-modal');
      const closeBtn = document.getElementById('close-appearance-btn');
      const cancelBtn = document.getElementById('cancel-appearance-btn');
      const applyBtn = document.getElementById('apply-appearance-btn');
      const iconsContainer = document.getElementById('appearance-icons');
      const colorsContainer = document.getElementById('appearance-colors');
      const previewIcon = document.querySelector('#appearance-preview i');
      
      let currentAppIndex = null;
      let tempAvatar = 'ph-user-circle';
      let tempColor = '#ffffff';

      const availableIcons = [
          'ph-user-circle', 'ph-user', 'ph-smiley', 'ph-baby', 
          'ph-crown', 'ph-star', 'ph-heart', 'ph-alien', 
          'ph-cat', 'ph-dog', 'ph-ghost', 'ph-robot',
          'ph-game-controller', 'ph-basketball', 'ph-music-note', 'ph-television',
          'ph-rocket-launch', 'ph-planet', 'ph-sword', 'ph-shield',
          'ph-car', 'ph-bicycle', 'ph-motorcycle', 'ph-boat',
          'ph-flower-tulip', 'ph-tree', 'ph-leaf', 'ph-paw-print',
          'ph-coffee', 'ph-beer-bottle', 'ph-pizza', 'ph-hamburger'
      ];

      const availableColors = [
          '#ffffff', '#00f2fe', '#4facfe', '#f43f5e', 
          '#eab308', '#10b981', '#a855f7', '#ec4899',
          '#ff5722', '#00e676', '#ffeb3b', '#e91e63',
          '#f59e0b', '#3b82f6', '#14b8a6', '#8b5cf6',
          '#f97316', '#84cc16', '#0ea5e9', '#6366f1',
          '#d946ef', '#f472b6', '#cbd5e1', '#0f172a'
      ];

      closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        appModal.classList.remove('open');
      });
      cancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        appModal.classList.remove('open');
      });

      window.openAppearanceModal = function(index) {
        currentAppIndex = index;
        const p = window.__CURRENT_EDITING_PROFILES__[index];
        tempAvatar = p.avatar || 'ph-user-circle';
        tempColor = p.color || '#ffffff';
        
        renderAppearanceUI();
        appModal.classList.add('open');
      };

      function updatePreview() {
        previewIcon.className = `ph ${tempAvatar}`;
        previewIcon.style.color = tempColor;
      }

      function renderAppearanceUI() {
        updatePreview();

        // Render Icons
        iconsContainer.innerHTML = '';
        availableIcons.forEach(icon => {
            const div = document.createElement('div');
            const isSelected = tempAvatar === icon;
            div.style.aspectRatio = '1/1';
            div.style.borderRadius = '8px';
            div.style.background = isSelected ? 'rgba(0, 242, 254, 0.2)' : 'var(--bg-surface)';
            div.style.border = isSelected ? '2px solid var(--accent)' : '1px solid var(--border-subtle)';
            div.style.display = 'flex';
            div.style.alignItems = 'center';
            div.style.justifyContent = 'center';
            div.style.cursor = 'pointer';
            div.style.transition = 'all 0.2s';
            div.innerHTML = `<i class="ph ${icon}" style="font-size: 1.5rem; color: ${isSelected ? 'var(--accent)' : 'var(--text-secondary)'};"></i>`;
            
            div.onclick = () => {
                tempAvatar = icon;
                renderAppearanceUI();
            };
            iconsContainer.appendChild(div);
        });

        // Render Colors
        colorsContainer.innerHTML = '';
        availableColors.forEach(color => {
            const div = document.createElement('div');
            const isSelected = tempColor.toLowerCase() === color.toLowerCase();
            div.style.aspectRatio = '1/1';
            div.style.borderRadius = '50%';
            div.style.background = color;
            div.style.cursor = 'pointer';
            div.style.border = isSelected ? '3px solid #fff' : '2px solid transparent';
            div.style.boxShadow = isSelected ? `0 0 0 2px ${color}` : 'none';
            div.style.transform = isSelected ? 'scale(1.1)' : 'scale(1)';
            div.style.transition = 'all 0.2s';
            
            div.onclick = () => {
                tempColor = color;
                renderAppearanceUI();
            };
            colorsContainer.appendChild(div);
        });
      }

      applyBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const p = window.__CURRENT_EDITING_PROFILES__[currentAppIndex];
        p.avatar = tempAvatar;
        p.color = tempColor;
        
        // Update input element and visual representation in the profiles list
        const profileRow = document.querySelector(`.edit-appearance-btn[data-index="${currentAppIndex}"]`);
        if (profileRow) {
            profileRow.innerHTML = `<i class="ph ${tempAvatar}" style="color: ${tempColor}; font-size: 1.1rem;"></i>`;
        }
        
        appModal.classList.remove('open');
      });

    })();
  </script>

  <script>
    (function() {
      // Gestione Logica Modale Permessi
      const permModal = document.getElementById('permissions-modal');
      const closeBtn = document.getElementById('close-permissions-btn');
      const cancelBtn = document.getElementById('cancel-permissions-btn');
      const applyBtn = document.getElementById('apply-permissions-btn');
      const container = document.getElementById('permissions-categories-container');
      let currentEditingIndex = null;
      let tempAllowedCategories = [];
      let tempAllowedChannels = [];

      function getProfileProfilesRef() {
        // We can access 'profiles' array indirectly or we need to expose it
        // Or simply we attach a global function in the previous IIFE
        return window.__CURRENT_EDITING_PROFILES__;
      }

      closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        permModal.classList.remove('open');
      });
      cancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        permModal.classList.remove('open');
      });

      window.openPermissionsModal = function(index) {
        currentEditingIndex = index;
        const p = window.__CURRENT_EDITING_PROFILES__[index];
        
        // Backward compatibility logic
        let allowedCats = p.allowed_categories || [];
        let allowedChs = p.allowed_channels || [];

        if (allowedCats.length === 0 && allowedChs.length === 0) {
            // Check if it's a legacy kids profile
            const isKidProfile = p.name.toLowerCase().includes('bambini') || p.name.toLowerCase().includes('kids') ||
                          (p.id && (p.id.toLowerCase().includes('bambini') || p.id.toLowerCase().includes('kids')));
            if (isKidProfile) {
                allowedCats = ['kids']; // Special kids category
            } else {
                allowedCats = ['*'];
            }
        }

        // Migrate 'bambini' to 'kids' on the fly
        if (allowedCats.includes('bambini')) {
            allowedCats = allowedCats.map(c => c === 'bambini' ? 'kids' : c);
        }
        
        tempAllowedCategories = [...allowedCats];
        tempAllowedChannels = [...allowedChs];

        renderPermissionsUI();
        permModal.classList.add('open');
      };

      function renderPermissionsUI() {
        container.innerHTML = '';
        
        const isAllCategories = tempAllowedCategories.includes('*');

        // Checkbox Tutte le Categorie
        const allCatDiv = document.createElement('div');
        allCatDiv.style.marginBottom = '1rem';
        allCatDiv.style.padding = '0.8rem';
        allCatDiv.style.background = 'var(--accent-glow)';
        allCatDiv.style.border = '1px solid rgba(0, 242, 254, 0.3)';
        allCatDiv.style.borderRadius = '8px';
        allCatDiv.innerHTML = `
          <label style="display: flex; align-items: center; gap: 0.8rem; cursor: pointer; font-weight: bold; color: var(--accent);">
            <input type="checkbox" id="perm-all-cats" ${isAllCategories ? 'checked' : ''} style="width: 18px; height: 18px;">
            <i class="ph ph-globe" style="font-size: 1.2rem;"></i> Consenti Tutto (Tutte le Categorie e Canali)
          </label>
        `;
        container.appendChild(allCatDiv);

        allCatDiv.querySelector('input').addEventListener('change', (e) => {
            if (e.target.checked) {
                tempAllowedCategories = ['*'];
                tempAllowedChannels = [];
            } else {
                tempAllowedCategories = [];
            }
            renderPermissionsUI(); // Re-render per abilitare/disabilitare i figli
        });

        // Crea le sezioni per le categorie
        const mergedCategories = { ...CATEGORIES };

        Object.keys(mergedCategories).forEach(catKey => {
            if (catKey === 'all') return;
            const catMeta = mergedCategories[catKey];
            
            // Trova i canali di questa categoria
            let catChannels = CHANNELS.filter(c => c.cat === catKey);
            if (catChannels.length === 0) return;

            const isCatSelected = tempAllowedCategories.includes(catKey);

            const catDiv = document.createElement('div');
            catDiv.style.background = 'var(--bg-input)';
            catDiv.style.border = '1px solid var(--border-subtle)';
            catDiv.style.borderRadius = '8px';
            catDiv.style.overflow = 'hidden';
            catDiv.style.opacity = isAllCategories ? '0.5' : '1';
            catDiv.style.pointerEvents = isAllCategories ? 'none' : 'auto';

            catDiv.innerHTML = `
              <div style="padding: 0.8rem; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02);">
                <label style="display: flex; align-items: center; gap: 0.8rem; cursor: pointer; font-weight: 600;">
                  <input type="checkbox" class="cat-checkbox" data-cat="${catKey}" ${isCatSelected ? 'checked' : ''} style="width: 16px; height: 16px;">
                  <span style="color: ${catMeta.color}; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="ph ${catMeta.icon}"></i> ${catMeta.label}
                  </span>
                </label>
                <button class="toggle-channels-btn" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 4px;"><i class="ph ph-caret-down"></i></button>
              </div>
              <div class="channels-list" style="display: none; padding: 0.8rem; flex-direction: column; gap: 0.5rem; background: var(--bg-surface);">
              </div>
            `;

            const channelsListDiv = catDiv.querySelector('.channels-list');
            
            catChannels.forEach(ch => {
                const isChSelected = isCatSelected || tempAllowedChannels.includes(ch.id);
                const chDiv = document.createElement('div');
                chDiv.innerHTML = `
                  <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-secondary);">
                    <input type="checkbox" class="ch-checkbox" data-id="${ch.id}" ${isChSelected ? 'checked' : ''} ${isCatSelected ? 'disabled' : ''}>
                    ${ch.name}
                  </label>
                `;
                channelsListDiv.appendChild(chDiv);
            });

            // Logica interattiva
            const catCheck = catDiv.querySelector('.cat-checkbox');
            catCheck.addEventListener('change', (e) => {
                if (e.target.checked) {
                    tempAllowedCategories.push(catKey);
                    // Rimuovi eventuali canali di questa categoria da tempAllowedChannels per pulizia
                    const catChIds = catChannels.map(c => c.id);
                    tempAllowedChannels = tempAllowedChannels.filter(id => !catChIds.includes(id));
                } else {
                    tempAllowedCategories = tempAllowedCategories.filter(c => c !== catKey);
                }
                renderPermissionsUI();
            });

            catDiv.querySelectorAll('.ch-checkbox').forEach(chk => {
                chk.addEventListener('change', (e) => {
                    const chId = parseInt(e.target.getAttribute('data-id'));
                    if (e.target.checked) {
                        if (!tempAllowedChannels.includes(chId)) tempAllowedChannels.push(chId);
                    } else {
                        tempAllowedChannels = tempAllowedChannels.filter(id => id !== chId);
                    }
                });
            });

            catDiv.querySelector('.toggle-channels-btn').addEventListener('click', (e) => {
                const isHidden = channelsListDiv.style.display === 'none';
                channelsListDiv.style.display = isHidden ? 'flex' : 'none';
                e.currentTarget.innerHTML = isHidden ? '<i class="ph ph-caret-up"></i>' : '<i class="ph ph-caret-down"></i>';
            });

            container.appendChild(catDiv);
        });
      }

      applyBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const p = window.__CURRENT_EDITING_PROFILES__[currentEditingIndex];
        p.allowed_categories = tempAllowedCategories;
        p.allowed_channels = tempAllowedChannels;
        permModal.classList.remove('open');
      });

    })();
  </script>

  <script src="js/theme.js?v=1.2"></script>
</body>

</html>


