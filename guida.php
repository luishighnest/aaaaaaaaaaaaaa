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

// Legge il JSON EPG dal file aggiornato dallo scraper Python
$epg_file = __DIR__ . '/guida_tv_sky.json';
$epg_data = [];
if (file_exists($epg_file)) {
    $json_raw = file_get_contents($epg_file);
    $epg_data = json_decode($json_raw, true) ?? [];
}
$epg_json    = json_encode($epg_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$last_update = file_exists($epg_file) ? date('H:i', filemtime($epg_file)) : '--:--';
?>
<!DOCTYPE html>
<html lang="it">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>PZ8 - Guida TV</title>
  <meta name="description" content="Guida TV completa dei canali PZ8">
  <link rel="stylesheet" href="css/style.css?v=1.2">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
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
  <style>
    body {
      background: #0a0a0a;
      background-image: radial-gradient(circle at top right, rgba(15, 23, 42, 0.5) 0%, transparent 40%),
                        radial-gradient(circle at bottom left, rgba(2, 6, 23, 0.8) 0%, transparent 40%);
      color: var(--text-primary);
      font-family: var(--font-main);
      height: 100vh;
      overflow: hidden;
      margin: 0;
      padding: 0;
    }

    :root.light-mode body {
      background: var(--bg-dashboard, #f1f5f9);
      background-image: none;
    }

    /* Dark Mode overrides to match index.php */
    :root:not(.light-mode) .guida-sidebar {
      background: rgba(17, 17, 21, 0.65);
      border-right: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    :root:not(.light-mode) .guida-brand {
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    :root:not(.light-mode) .guida-search-wrapper {
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    :root:not(.light-mode) .guida-ch-item {
      background: #141419;
      border: 1px solid transparent;
    }

    :root:not(.light-mode) .guida-ch-item:hover {
      background: #1e1e2a;
      border-color: rgba(255, 255, 255, 0.06);
    }

    :root:not(.light-mode) .guida-ch-item.active {
      background: linear-gradient(90deg, rgba(0, 242, 254, 0.15) 0%, rgba(20, 20, 25, 0.8) 100%);
      border-color: rgba(0, 242, 254, 0.5);
      box-shadow: inset 4px 0 0 var(--accent), 0 0 15px rgba(0, 242, 254, 0.15);
    }

    :root:not(.light-mode) .guida-header {
      background: rgba(17, 17, 21, 0.65);
      backdrop-filter: blur(24px) saturate(1.2);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    :root:not(.light-mode) .timeline-item {
      background: rgba(17, 17, 21, 0.65);
      backdrop-filter: blur(24px) saturate(1.2);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    :root:not(.light-mode) .timeline-item:hover {
      background: rgba(30, 30, 40, 0.8);
      border-color: rgba(255, 255, 255, 0.12);
    }

    :root:not(.light-mode) .timeline-item.live {
      background: linear-gradient(135deg, rgba(0, 230, 118, 0.08) 0%, rgba(0, 176, 255, 0.05) 100%);
      border-color: rgba(0, 230, 118, 0.2);
    }


    .guida-layout {
      display: grid;
      grid-template-columns: 340px 1fr;
      height: 100vh;
    }

    /* Sidebar */
    .guida-sidebar {
      background: var(--bg-panel);
      backdrop-filter: blur(24px) saturate(1.2);
      border-right: 1px solid var(--border-subtle);
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow: hidden;
    }

    .guida-brand {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 1.5rem;
      border-bottom: 1px solid var(--border-subtle);
    }

    .guida-brand-icon {
      width: 32px;
      height: 32px;
      background: var(--accent);
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      color: #07070d;
    }

    .guida-brand-text {
      font-size: 1.2rem;
      font-weight: 800;
      letter-spacing: -0.5px;
    }

    .guida-brand-text span { color: var(--accent); }

    .guida-search-wrapper {
      padding: 1rem;
      border-bottom: 1px solid var(--border-subtle);
    }

    .guida-search-container { position: relative; width: 100%; }

    .guida-search-container i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1rem;
      pointer-events: none;
    }

    .guida-search-input {
      width: 100%;
      background: var(--bg-input);
      border: 1px solid var(--border-subtle);
      border-radius: 8px;
      padding: 8px 12px 8px 36px;
      color: var(--text-primary);
      font-size: 0.85rem;
      outline: none;
      transition: var(--transition);
    }

    .guida-search-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 8px rgba(0, 242, 254, 0.2);
    }

    .guida-sidebar-content {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.8rem;
    }

    .guida-sidebar-content::-webkit-scrollbar { width: 4px; }
    .guida-sidebar-content::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 2px;
    }

    .guida-cat-section { display: flex; flex-direction: column; gap: 0.4rem; }

    .guida-cat-header {
      font-size: 0.72rem;
      color: var(--text-muted);
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      padding-left: 0.5rem;
      margin-top: 0.5rem;
      margin-bottom: 0.2rem;
    }

    .guida-ch-item {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 0.7rem 1rem;
      border-radius: var(--radius-sm);
      color: var(--text-secondary);
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.02);
      transition: var(--transition);
    }

    .guida-ch-item:hover { background: var(--bg-row-hover); color: var(--text-primary); }

    .guida-ch-item.active {
      background: var(--bg-row-hover);
      border-color: var(--accent);
      color: var(--text-primary);
      box-shadow: inset 4px 0 0 var(--accent);
    }

    .guida-ch-item-icon {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
    }

    /* Main Content */
    .guida-main { display: flex; flex-direction: column; height: 100%; overflow: hidden; }

    .guida-header {
      height: 70px;
      border-bottom: 1px solid var(--border-subtle);
      background: var(--bg-surface);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      flex-shrink: 0;
    }

    .guida-header-left { display: flex; align-items: center; gap: 1rem; }

    .guida-header-ch-name {
      font-size: 1.4rem;
      font-weight: 800;
      letter-spacing: -0.5px;
      margin: 0;
    }

    .guida-header-ch-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .guida-btn-back {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.6rem 1.2rem;
      border-radius: 99px;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--accent);
      border: 1px solid var(--accent-glow);
      background: transparent;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
    }

    .guida-btn-back:hover {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
      box-shadow: 0 0 15px var(--accent-glow);
    }

    .guida-content-area {
      flex: 1;
      overflow-y: auto;
      padding: 2rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .guida-content-area::-webkit-scrollbar { width: 6px; }
    .guida-content-area::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.08);
      border-radius: 3px;
    }

    /* Program Timeline */
    .timeline-container { display: flex; flex-direction: column; gap: 0.8rem; }

    .timeline-item {
      display: flex;
      gap: 2rem;
      padding: 1.4rem;
      border-radius: var(--radius);
      background: var(--bg-surface);
      border: 1px solid var(--border-subtle);
      position: relative;
      overflow: hidden;
      transition: var(--transition);
    }

    .timeline-item:hover {
      background: var(--bg-hover);
      transform: translateY(-2px);
    }

    .timeline-item.live {
      background: var(--bg-row-hover);
      border-color: var(--border-strong);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.03);
    }

    .timeline-item.live::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 4px; height: 100%;
      background: linear-gradient(180deg, #00e676, #00b0ff);
    }

    .timeline-item.past { opacity: 0.45; }

    .timeline-time {
      width: 80px;
      font-family: var(--font-alt);
      font-size: 1.15rem;
      font-weight: 800;
      color: #60a5fa;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
    }

    .timeline-item.live .timeline-time { color: #00e676; }
    .timeline-item.past .timeline-time { color: var(--text-muted); }

    .timeline-details {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }

    .timeline-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--text-primary);
      line-height: 1.3;
      margin: 0;
    }

    .timeline-item.live .timeline-title {
      font-size: 1.25rem;
      font-weight: 800;
      text-shadow: 0 0 10px rgba(0, 230, 118, 0.1);
    }

    .timeline-item.past .timeline-title { color: var(--text-secondary); }

    .timeline-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 2px 8px;
      border-radius: 4px;
      width: fit-content;
    }

    .timeline-badge.live   { background: rgba(0,230,118,0.1);  color: #00e676; border: 1px solid rgba(0,230,118,0.2); }
    .timeline-badge.upcoming { background: rgba(96,165,250,0.1); color: #60a5fa; border: 1px solid rgba(96,165,250,0.2); }
    .timeline-badge.past   { background: var(--bg-input); color: var(--text-muted); border: 1px solid var(--border-subtle); }

    .timeline-progress-container {
      margin-top: 0.6rem;
      display: flex;
      align-items: center;
      gap: 12px;
      width: 100%;
    }

    .timeline-progress-bar {
      flex: 1;
      height: 5px;
      background: var(--bg-input);
      border-radius: 99px;
      overflow: hidden;
    }

    .timeline-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #00e676, #00b0ff);
      border-radius: 99px;
      box-shadow: 0 0 8px rgba(0, 230, 118, 0.4);
    }

    .timeline-progress-text {
      font-size: 0.72rem;
      color: var(--text-muted);
      font-weight: 700;
      flex-shrink: 0;
    }

    .guida-no-data {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      padding: 5rem 1rem;
      text-align: center;
      color: var(--text-muted);
    }

    .guida-no-data i   { font-size: 3.5rem; opacity: 0.4; }
    .guida-no-data p   { font-size: 1.1rem; font-weight: 600; color: var(--text-secondary); margin: 0; }

    @media (max-width: 900px) { .guida-layout { grid-template-columns: 280px 1fr; } }
    .btn-show-sidebar-mobile { display: none; }
    .btn-close-sidebar-mobile { display: none !important; }
    @media (max-width: 768px) {
      .guida-layout { grid-template-columns: 1fr; }
      .guida-sidebar { display: none; }
      .guida-sidebar.show-mobile { display: flex; position: fixed; inset: 0; z-index: 1000; width: 100%; max-width: 100%; background: var(--bg-base); }
      .btn-show-sidebar-mobile { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary); cursor: pointer; font-size: 1.4rem; }
      .btn-close-sidebar-mobile { display: block !important; }
      .guida-header { padding: 0 1rem; }
      .guida-btn-back span { display: none; } /* nasconde testo Guarda Ora su mobile */
      .guida-btn-back { padding: 0.6rem; border-radius: 12px; }
      .guida-header-ch-name { font-size: 1.1rem; }
    }
  </style>
</head>

<body>
  <!-- EPG iniettato lato server da PHP -->
  <script>
    window.__EPG_DATA__    = <?= $epg_json ?>;
    window.__EPG_UPDATED__ = "<?= $last_update ?>";
    window.__ACTIVE_PROFILE_NAME__ = "<?= isset($active_profile['name']) ? addslashes($active_profile['name']) : '' ?>";
    window.__ACTIVE_PROFILE_ID__   = "<?= isset($active_profile['id']) ? addslashes($active_profile['id']) : '' ?>";
    window.__ACTIVE_PROFILE_FAVORITES__ = <?= json_encode($active_profile['favorites'] ?? []) ?>;
    window.__CSRF_TOKEN__          = "<?= $_SESSION['csrf_token'] ?? '' ?>";
    <?php
    $view_mode_cookie = $_COOKIE['pz8_view_mode'] ?? '';
    $back_url = 'index.php';
    if ($view_mode_cookie === 'mobile') $back_url = 'mobile.php';
    if ($view_mode_cookie === 'tv') $back_url = 'tv.php';
    ?>
    window.__BACK_URL_BASE__       = "<?= $back_url ?>";
  </script>

  <div class="guida-layout">
    <!-- Sidebar -->
    <aside class="guida-sidebar" id="guida-sidebar">
      <div class="guida-brand" style="display:flex; justify-content:space-between; align-items:center;">
        <div style="display:flex; align-items:center; gap:0.6rem;">
          <div class="guida-brand-icon"><i class="ph ph-calendar"></i></div>
          <div class="guida-brand-text"><span></span> Guida TV</div>
        </div>
        <button id="btn-close-sidebar" class="btn-close-sidebar-mobile" style="background:none; border:none; color:var(--text-muted); font-size:1.5rem; cursor:pointer;"><i class="ph ph-x"></i></button>
      </div>
      <div class="guida-search-wrapper">
        <div class="guida-search-container">
          <i class="ph ph-magnifying-glass"></i>
          <input type="text" id="ch-search" class="guida-search-input" placeholder="Cerca canale o evento...">
        </div>
      </div>



      <div class="guida-sidebar-content" id="guida-sidebar-content">
        <!-- Dynamic Category-wise Channel Listing -->
      </div>
    </aside>

    <!-- Main Schedule Panel -->
    <main class="guida-main">
      <header class="guida-header">
        <div class="guida-header-left" style="display:flex;align-items:center;gap:0.8rem;">
          <button id="btn-show-sidebar" class="btn-show-sidebar-mobile"><i class="ph ph-list"></i></button>
          <div class="guida-header-ch-icon" id="header-ch-icon"></div>
          <h1 class="guida-header-ch-name" id="header-ch-name">Seleziona Canale</h1>
          <button id="btn-toggle-favorite" class="btn-favorite-toggle" style="display:none;" title="Aggiungi ai preferiti">
            <i class="ph ph-star"></i>
          </button>
        </div>
        <a id="btn-back" href="index.php" class="guida-btn-back">
          <i class="ph ph-house"></i> <span>Torna alla Home</span>
        </a>
      </header>

      <div class="guida-content-area" id="guida-schedule">
        <!-- Schedule details populated dynamically -->
      </div>
    </main>
  </div>

  <script src="js/channels_v2.js?v=<?= time() ?>"></script>
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
                allowedCats = ['kids']; // RetrocompatibilitÃƒÂ 
            } else {
                allowedCats = ['*'];
            }
        }

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
    // EPG data injected by PHP: available immediately
    let epgData = window.__EPG_DATA__ || [];
    let epgMap  = new Map();
    let currentChannel = null;
    let favorites = window.__ACTIVE_PROFILE_FAVORITES__ || [];
    window.favorites = favorites;

    // Canale iniziale da URL
    const params  = new URLSearchParams(window.location.search);
    let urlChId   = parseInt(params.get('id'));
    if (urlChId) currentChannel = getChannelById(urlChId);
    if (!currentChannel) currentChannel = CHANNELS[0];

    document.getElementById('btn-back').href = `${window.__BACK_URL_BASE__}?id=${currentChannel.id}`;

    // Costruisce mappa EPG per lookup O(1)
    function buildEpgMap() {
      epgMap.clear();
      epgData.forEach(item => {
        if (item.canale) epgMap.set(item.canale.toUpperCase(), item);
      });
    }
    buildEpgMap(); // Eseguito subito con dati PHP
    function timeToMinutes(timeStr) {
      if (!timeStr || !timeStr.includes(':')) return 0;
      const [hours, minutes] = timeStr.split(':').map(Number);
      return (hours * 60) + minutes;
    }

    // ─── Sidebar ───
    function renderSidebar(searchQuery = '') {
      const container = document.getElementById('guida-sidebar-content');
      if (!container) return;
      container.innerHTML = '';

      const query = searchQuery.toLowerCase().trim();
      const categoriesMap = {};
      Object.keys(CATEGORIES).forEach(key => {
        if (key !== 'all') categoriesMap[key] = [];
      });

      CHANNELS.forEach(ch => {
        let matches = false;
        if (!query) {
          matches = true;
        } else {
          const chNameMatch   = ch.name.toLowerCase().includes(query);
          const chCatMatch    = ch.cat.toLowerCase().includes(query);
          const catLabelMatch = (CATEGORIES[ch.cat] && CATEGORIES[ch.cat].label.toLowerCase().includes(query));
          if (chNameMatch || chCatMatch || catLabelMatch) {
            matches = true;
          } else {
            const channelEpgObj = epgMap.get(ch.name.toUpperCase());
            if (channelEpgObj && channelEpgObj.programmi) {
              matches = channelEpgObj.programmi.some(p => p.titolo.toLowerCase().includes(query));
            }
          }
        }
        if (!matches) return;
        if (categoriesMap[ch.cat]) categoriesMap[ch.cat].push(ch);
      });

      // Sezione virtuale "I Miei Preferiti" in cima
      const favoritesChannels = CHANNELS.filter(ch => favorites.includes(ch.id));
      const filteredFavs = favoritesChannels.filter(ch => {
        let matches = false;
        if (!query) {
          matches = true;
        } else {
          const chNameMatch   = ch.name.toLowerCase().includes(query);
          const chCatMatch    = ch.cat.toLowerCase().includes(query);
          const catLabelMatch = (CATEGORIES[ch.cat] && CATEGORIES[ch.cat].label.toLowerCase().includes(query));
          if (chNameMatch || chCatMatch || catLabelMatch) {
            matches = true;
          } else {
            const channelEpgObj = epgMap.get(ch.name.toUpperCase());
            if (channelEpgObj && channelEpgObj.programmi) {
              matches = channelEpgObj.programmi.some(p => p.titolo.toLowerCase().includes(query));
            }
          }
        }
        return matches;
      });

      if (filteredFavs.length > 0) {
        const catSection = document.createElement('div');
        catSection.className = 'guida-cat-section';

        const header = document.createElement('div');
        header.className = 'guida-cat-header';
        header.style.color = '#ffc107';
        header.innerHTML = `<i class="ph-fill ph-star" style="margin-right:4px;"></i> I Miei Preferiti`;
        catSection.appendChild(header);

        filteredFavs.forEach(ch => {
          const item      = document.createElement('div');
          const isSelected = ch.id === currentChannel.id;
          item.className  = 'guida-ch-item' + (isSelected ? ' active' : '');
          
          let matchText = '';
          if (query) {
            const chNameMatch   = ch.name.toLowerCase().includes(query);
            const chCatMatch    = ch.cat.toLowerCase().includes(query);
            const catLabelMatch = (CATEGORIES[ch.cat] && CATEGORIES[ch.cat].label.toLowerCase().includes(query));
            if (!chNameMatch && !chCatMatch && !catLabelMatch) {
              const channelEpgObj = epgMap.get(ch.name.toUpperCase());
              if (channelEpgObj && channelEpgObj.programmi) {
                const matchedProg = channelEpgObj.programmi.find(p => p.titolo.toLowerCase().includes(query));
                if (matchedProg) matchText = `${matchedProg.ora} - ${matchedProg.titolo}`;
              }
            }
          }

          item.innerHTML = `
            <div class="guida-ch-item-icon" style="background:#ffc10715;color:#ffc107">
              <i class="ph ${ch.icon}"></i>
            </div>
            <div style="display:flex;flex-direction:column;min-width:0;flex:1;">
              <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${ch.name}</span>
              ${matchText ? `
                <span style="font-size:0.72rem;color:#10b981;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;display:inline-flex;align-items:center;gap:2px;">
                  <i class="ph ph-calendar-check" style="font-size:0.8rem;"></i>
                  ${matchText}
                </span>
              ` : ''}
            </div>
            <button class="ch-fav-star-btn" onclick="event.stopPropagation(); event.preventDefault(); toggleFavorite(${ch.id});" style="background:transparent;border:none;color:#ffc107;cursor:pointer;padding:4px;display:inline-flex;align-items:center;justify-content:center;transition:all 0.2s;outline:none;" title="Rimuovi dai preferiti"><i class="ph-fill ph-star" style="font-size:1.15rem;"></i></button>
          `;
          item.addEventListener('click', () => selectChannel(ch));
          catSection.appendChild(item);
        });

        container.appendChild(catSection);
      }

      Object.keys(CATEGORIES).forEach(key => {
        if (key === 'all') return;
        const channels = categoriesMap[key];
        if (!channels || channels.length === 0) return;

        const catMeta   = CATEGORIES[key];
        const catSection = document.createElement('div');
        catSection.className = 'guida-cat-section';

        const header = document.createElement('div');
        header.className = 'guida-cat-header';
        header.textContent = catMeta.label;
        catSection.appendChild(header);

        channels.forEach(ch => {
          const item      = document.createElement('div');
          const isSelected = ch.id === currentChannel.id;
          item.className  = 'guida-ch-item' + (isSelected ? ' active' : '');
          const catColor  = catMeta ? catMeta.color : '#888';
          const isFav = favorites.includes(ch.id);

          let matchText = '';
          if (query) {
            const chNameMatch   = ch.name.toLowerCase().includes(query);
            const chCatMatch    = ch.cat.toLowerCase().includes(query);
            const catLabelMatch = (CATEGORIES[ch.cat] && CATEGORIES[ch.cat].label.toLowerCase().includes(query));
            if (!chNameMatch && !chCatMatch && !catLabelMatch) {
              const channelEpgObj = epgMap.get(ch.name.toUpperCase());
              if (channelEpgObj && channelEpgObj.programmi) {
                const matchedProg = channelEpgObj.programmi.find(p => p.titolo.toLowerCase().includes(query));
                if (matchedProg) matchText = `${matchedProg.ora} - ${matchedProg.titolo}`;
              }
            }
          }

          item.innerHTML = `
            <div class="guida-ch-item-icon" style="background:${catColor}15;color:${catColor}">
              <i class="ph ${ch.icon}"></i>
            </div>
            <div style="display:flex;flex-direction:column;min-width:0;flex:1;">
              <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${ch.name}</span>
              ${matchText ? `
                <span style="font-size:0.72rem;color:#10b981;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;display:inline-flex;align-items:center;gap:2px;">
                  <i class="ph ph-calendar-check" style="font-size:0.8rem;"></i>
                  ${matchText}
                </span>
              ` : ''}
            </div>
            <button class="ch-fav-star-btn" onclick="event.stopPropagation(); event.preventDefault(); toggleFavorite(${ch.id});" style="background:transparent;border:none;color:${isFav ? '#ffc107' : 'rgba(255,255,255,0.22)'};cursor:pointer;padding:4px;display:inline-flex;align-items:center;justify-content:center;transition:all 0.2s;outline:none;" title="${isFav ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'}"><i class="${isFav ? 'ph-fill ph-star' : 'ph ph-star'}" style="font-size:1.15rem;"></i></button>
          `;
          item.addEventListener('click', () => selectChannel(ch));
          catSection.appendChild(item);
        });

        container.appendChild(catSection);
      });
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
          
          // Aggiorna pulsante preferito in header
          if (currentChannel && currentChannel.id === channelId) {
            const btnFav = document.getElementById('btn-toggle-favorite');
            if (btnFav) {
              const isFav = favorites.includes(channelId);
              btnFav.className = 'btn-favorite-toggle' + (isFav ? ' is-favorite' : '');
              btnFav.querySelector('i').className = isFav ? 'ph-fill ph-star' : 'ph ph-star';
            }
          }
          
          // Re-render sidebar
          renderSidebar(document.getElementById('ch-search') ? document.getElementById('ch-search').value : '');
        } else {
          console.error('Errore preferiti:', resData.error);
        }
      } catch (err) {
        console.error('Errore preferiti:', err);
      }
    }

    // Selezione canale
    function selectChannel(ch) {
      currentChannel = ch;
      history.pushState({ id: ch.id }, '', `?id=${ch.id}`);
      document.getElementById('btn-back').href = `${window.__BACK_URL_BASE__}?id=${ch.id}`;
      document.getElementById('guida-sidebar').classList.remove('show-mobile');

      // Configura pulsante preferito in header
      const btnFav = document.getElementById('btn-toggle-favorite');
      if (btnFav) {
        btnFav.style.display = 'inline-flex';
        const isFav = favorites.includes(ch.id);
        btnFav.className = 'btn-favorite-toggle' + (isFav ? ' is-favorite' : '');
        btnFav.querySelector('i').className = isFav ? 'ph-fill ph-star' : 'ph ph-star';
        btnFav.onclick = () => toggleFavorite(ch.id);
      }

      renderSidebar(document.getElementById('ch-search').value);
      renderSchedule();
    }

    // Ã¢â€ â‚¬Ã¢â€ â‚¬ EPG Lookup Ã¢â€ â‚¬Ã¢â€ â‚¬
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
        now:         channelEpg.programmi[activeIndex],
        activeIndex: activeIndex,
        next:        activeIndex + 1 < channelEpg.programmi.length ? channelEpg.programmi[activeIndex + 1] : null
      };
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬ Render Palinsesto Ã¢â€â‚¬Ã¢â€â‚¬
    function renderSchedule() {
      const headerIcon       = document.getElementById('header-ch-icon');
      const headerName       = document.getElementById('header-ch-name');
      const scheduleContainer = document.getElementById('guida-schedule');
      if (!currentChannel || !scheduleContainer) return;

      const catMeta  = CATEGORIES[currentChannel.cat];
      const catColor = catMeta ? catMeta.color : '#888';

      headerName.textContent = currentChannel.name;
      if (headerIcon) {
        headerIcon.innerHTML = `<i class="ph ${currentChannel.icon}"></i>`;
        headerIcon.style.color      = catColor;
        headerIcon.style.background = catColor + '15';
      }

      scheduleContainer.innerHTML = '';

      const channelName = currentChannel.name.toUpperCase();
      const channelEpg  = epgMap.get(channelName);

      if (channelEpg && channelEpg.programmi && channelEpg.programmi.length > 0) {
        const epgStatus  = getChannelEpg(currentChannel.name);
        const activeProg = epgStatus.now;
        const activeIndex = epgStatus.activeIndex;

        const timeline = document.createElement('div');
        timeline.className = 'timeline-container';

        const now        = new Date();
        const nowMinutes = (now.getHours() * 60) + now.getMinutes();

        channelEpg.programmi.forEach((p, idx) => {
          const isLive     = activeProg && p.ora === activeProg.ora && p.titolo === activeProg.titolo && idx === activeIndex;
          const isPast     = activeIndex !== -1 && idx < activeIndex;
          const isUpcoming = activeIndex !== -1 && idx > activeIndex;

          const item = document.createElement('div');
          item.className = 'timeline-item' + (isLive ? ' live' : '') + (isPast ? ' past' : '');

          let startMin = timeToMinutes(p.ora);
          let endMin   = (idx + 1 < channelEpg.programmi.length)
            ? timeToMinutes(channelEpg.programmi[idx + 1].ora)
            : startMin + 120;
          if (endMin < startMin) endMin += 1440;
          const duration = endMin - startMin;

          let badgeHtml = '';
          if (isLive)     badgeHtml = `<span class="timeline-badge live"><i class="ph ph-play-circle"></i> In Onda Ora</span>`;
          else if (isUpcoming) badgeHtml = `<span class="timeline-badge upcoming"><i class="ph ph-calendar"></i> A Seguire</span>`;
          else            badgeHtml = `<span class="timeline-badge past"><i class="ph ph-check-circle"></i> Trasmesso</span>`;

          let progressHtml = '';
          if (isLive) {
            let adjNow = nowMinutes;
            if (adjNow < startMin && startMin > 1000) adjNow += 1440;
            const rem = Math.max(0, endMin - adjNow);
            const pct = duration > 0 ? Math.min(100, Math.max(0, ((adjNow - startMin) / duration) * 100)) : 100;
            progressHtml = `
              <div class="timeline-progress-container">
                <div class="timeline-progress-bar">
                  <div class="timeline-progress-fill" style="width:${pct}%"></div>
                </div>
                <span class="timeline-progress-text">${rem > 0 ? 'Mancano ' + rem + ' min' : 'In conclusione'}</span>
              </div>`;
          }

          item.innerHTML = `
            <div class="timeline-time">
              <span>${p.ora}</span>
              <span style="font-size:0.72rem;color:var(--text-muted);font-weight:500;">
                ${duration >= 60 ? Math.floor(duration / 60) + 'h ' + (duration % 60) + 'm' : duration + 'm'}
              </span>
            </div>
            <div class="timeline-details">
              <h3 class="timeline-title">${p.titolo}</h3>
              ${badgeHtml}
              ${p.descrizione ? `<div style="font-size:0.85rem;color:var(--text-secondary);margin-top:6px;line-height:1.4;">${p.descrizione}</div>` : ''}
              ${progressHtml}
            </div>`;

          timeline.appendChild(item);
        });

        scheduleContainer.appendChild(timeline);
      } else {
        scheduleContainer.innerHTML = `
          <div class="guida-no-data">
            <i class="ph ph-calendar-x"></i>
            <p>Nessun programma disponibile per "${currentChannel.name}"</p>
            <span style="font-size:0.85rem;color:var(--text-muted);">Questo canale trasmette una programmazione in diretta continua.</span>
          </div>`;
      }
    }

    // ── Fetch aggiornamento in background ──
    async function fetchEpgData() {
      try {
        const response = await fetch('epg.php');
        if (!response.ok) throw new Error('EPG fetch failed: ' + response.status);
        const newData = await response.json();
        // Aggiorna SOLO se la risposta è un array valido e non vuoto
        // (protegge da errori/timeout che restituiscono oggetti vuoti)
        if (Array.isArray(newData) && newData.length > 0) {
          epgData = newData;
          buildEpgMap();
          renderSidebar(document.getElementById('ch-search').value);
          renderSchedule();
          console.log('EPG aggiornato: ' + newData.length + ' canali');
        } else {
          console.warn('EPG fetch: risposta non valida, uso dati esistenti', newData);
        }
      } catch(err) {
        console.warn('Aggiornamento EPG fallito, uso dati esistenti:', err);
      }
    }

    // Search
    document.getElementById('ch-search').addEventListener('input', (e) => {
      renderSidebar(e.target.value);
    });

    // Back/Forward browser
    window.addEventListener('popstate', (e) => {
      const params = new URLSearchParams(window.location.search);
      const id = parseInt(params.get('id'));
      if (id) { const ch = getChannelById(id); if (ch) selectChannel(ch); }
    });

    // Mobile sidebar toggle
    const btnShowSidebar = document.getElementById('btn-show-sidebar');
    if (btnShowSidebar) btnShowSidebar.addEventListener('click', () => {
        document.getElementById('guida-sidebar').classList.add('show-mobile');
    });
    const btnCloseSidebar = document.getElementById('btn-close-sidebar');
    if (btnCloseSidebar) btnCloseSidebar.addEventListener('click', () => {
        document.getElementById('guida-sidebar').classList.remove('show-mobile');
    });

    // Avvio: rendering immediato con dati PHP
    renderSidebar();
    renderSchedule();

    // Configura pulsante preferito in header per il canale iniziale
    if (currentChannel) {
      const btnFav = document.getElementById('btn-toggle-favorite');
      if (btnFav) {
        btnFav.style.display = 'inline-flex';
        const isFav = favorites.includes(currentChannel.id);
        btnFav.className = 'btn-favorite-toggle' + (isFav ? ' is-favorite' : '');
        btnFav.querySelector('i').className = isFav ? 'ph-fill ph-star' : 'ph ph-star';
        btnFav.onclick = () => toggleFavorite(currentChannel.id);
      }
    }

    // Aggiornamento in background dopo 5s, poi ogni 60s
    setTimeout(() => fetchEpgData(), 5000);
    setInterval(() => renderSchedule(), 60000); // Aggiorna barre progresso
  </script>

  <script src="js/theme.js?v=1.2"></script>
</body>

</html>



