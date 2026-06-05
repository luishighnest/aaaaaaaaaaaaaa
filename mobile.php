<?php
// Avvia sessione e verifica autenticazione
session_start();

// Genera il token CSRF se non presente in sessione
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Previeni il caching della pagina
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// Gestione della modalità di visualizzazione (PC vs Mobile/TV)
function isMobileOrTV() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|smarttv|smart-tv|hbbtv|appletv|googletv|tizen|webos|chromecast|roku|philips|sony|panasonic|lg-tv/i', $ua);
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

// Se l'utente visita mobile.php, forziamo il cookie a 'mobile' in modo che sia persistente.
if ($view_mode !== 'pc') {
    $view_mode = 'mobile';
    if (!isset($_COOKIE['pz8_view_mode']) || $_COOKIE['pz8_view_mode'] !== 'mobile') {
        $secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('pz8_view_mode', 'mobile', [
            'expires' => time() + (365 * 24 * 3600),
            'path' => '/',
            'secure' => $secure_cookie,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}

if ($view_mode === 'pc') {
    $redirect_url = 'index.php';
    if (isset($_GET['id'])) {
        $redirect_url .= '?id=' . urlencode($_GET['id']);
    }
    header('Location: ' . $redirect_url);
    exit;
}

$config_file = __DIR__ . '/users_config.php';
$config = file_exists($config_file) ? require $config_file : [];
$subscription_expiry = $config['subscription_expiry'] ?? '2027-12-31';

if (time() > strtotime($subscription_expiry . ' 23:59:59')) {
    $secure_cookie = isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (isset($_COOKIE['remember_user'])) {
        setcookie('remember_user', '', time() - 3600, '/', '', $secure_cookie, true);
    }
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', $secure_cookie, true);
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

// Carica profili dell'utente
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

// Carica EPG
$epg_file = __DIR__ . '/guida_tv_sky.json';
$epg_data = [];
if (file_exists($epg_file)) {
    $json_raw = file_get_contents($epg_file);
    $epg_data = json_decode($json_raw, true) ?? [];
}
$epg_json = json_encode($epg_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$last_update = file_exists($epg_file) ? date('H:i', filemtime($epg_file)) : '--:--';

// Carica Agenda
$agenda_file = __DIR__ . '/agenda.json';
$agenda_data = [];
if (file_exists($agenda_file)) {
    $agenda_raw = file_get_contents($agenda_file);
    $agenda_data = json_decode($agenda_raw, true) ?? [];
}
$agenda_json = json_encode($agenda_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

// Rileva se profilo bambini
$pname_check = isset($active_profile['name']) ? strtolower($active_profile['name']) : '';
$pid_check = isset($active_profile['id']) ? strtolower($active_profile['id']) : '';
$is_kids_profile = (
    strpos($pname_check, 'bambini') !== false || 
    strpos($pname_check, 'kids') !== false || 
    strpos($pid_check, 'bambini') !== false || 
    strpos($pid_check, 'kids') !== false
);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>PZ8 Mobile</title>
  <meta name="description" content="Dashboard StreamHub Mobile">
  <link rel="stylesheet" href="css/style_mobile.css?v=<?= time() ?>">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
    // Iniezione preventiva colore principale e tema
    (function() {
      const accent = localStorage.getItem('accent_color');
      const glow = localStorage.getItem('accent_glow');
      if (accent && glow) {
        document.documentElement.style.setProperty('--accent', accent);
        document.documentElement.style.setProperty('--accent-glow', glow);
      }
      if (localStorage.getItem('theme') === 'light') {
        document.documentElement.classList.add('light-mode');
      }
    })();
  </script>
</head>
<body>

  <!-- Dati iniettati per JS -->
  <script>
    window.__EPG_DATA__    = <?= $epg_json ?>;
    window.__EPG_UPDATED__ = "<?= $last_update ?>";
    window.__AGENDA_DATA__ = <?= $agenda_json ?>;
    window.__ACTIVE_PROFILE_NAME__ = "<?= isset($active_profile['name']) ? addslashes($active_profile['name']) : '' ?>";
    window.__ACTIVE_PROFILE_ID__   = "<?= isset($active_profile['id']) ? addslashes($active_profile['id']) : '' ?>";
    window.__ACTIVE_PROFILE_FAVORITES__ = <?= json_encode($active_profile['favorites'] ?? []) ?>;
    window.__ALL_PROFILES__        = <?= $all_profiles_json ?>;
    window.__CSRF_TOKEN__          = "<?= $_SESSION['csrf_token'] ?>";
    window.__IS_KIDS_PROFILE__     = <?= $is_kids_profile ? 'true' : 'false' ?>;
  </script>

  <div class="mobile-app-container">
    
    <!-- Header -->
    <header class="mobile-header">
      <div class="header-left">
        <button id="menu-btn" class="menu-trigger" aria-label="Apri menu">
          <i class="ph ph-list"></i>
        </button>
        <div class="mobile-brand">PZ<span>8</span></div>
      </div>
      <div class="header-right">
        <div id="mobile-clock" class="mobile-clock">00:00</div>
        <button id="profile-btn" class="profile-avatar-btn" style="border-color: <?= $active_profile['color'] ?>; background: <?= $active_profile['color'] ?>15;">
          <i class="ph <?= htmlspecialchars($active_profile['avatar']) ?>" style="color: <?= $active_profile['color'] ?>;"></i>
        </button>
      </div>
    </header>

    <!-- Player Area -->
    <div id="player-container" class="player-container">
      <iframe id="player-frame" src="about:blank" allowfullscreen allow="autoplay; encrypted-media; fullscreen" style="display: none; width: 100%; height: 100%; border: none;"></iframe>
      <video id="native-video-player" controls autoplay playsinline style="display: none; width: 100%; height: 100%; background: #000; z-index: 4; position: absolute; inset: 0;"></video>
      
      <!-- Overlay di stato inizializzazione / nessun canale -->
      <div id="no-stream-overlay" class="no-stream-overlay">
        <i class="ph ph-television-simple"></i>
        <span>Scegli un canale per iniziare la visione</span>
      </div>
    </div>

    <!-- Player Meta Bar -->
    <div class="player-meta-bar">
      <div class="player-meta-info">
        <div id="player-ch-title" class="player-meta-title">Nessun Canale</div>
        <div id="player-ch-epg" class="player-meta-epg">Seleziona un canale dalla lista qui sotto</div>
      </div>
      <div class="player-actions">
        <button id="player-fav-btn" class="player-fav-btn" style="display: none;" title="Aggiungi ai preferiti">
          <i class="ph ph-star"></i>
        </button>
      </div>
    </div>

    <!-- Main Content Area -->
    <main class="app-content-area">
      <!-- Cerca -->
      <div class="search-container">
        <i class="ph ph-magnifying-glass"></i>
        <input type="text" id="ch-search" placeholder="Cerca canale o programma...">
      </div>

      <!-- Category info banner -->
      <div class="category-header">
        <div id="category-title" class="category-title">Tutti i Canali</div>
        <div id="channels-count" class="channels-count-badge">0</div>
      </div>

      <!-- Channels list -->
      <div id="channels-list-container" class="channels-list">
        <!-- Inseriti dinamicamente via JS -->
      </div>
    </main>

  </div>

  <!-- MENU A TENDINA A SINISTRA (Drawer) -->
  <div id="drawer-overlay" class="drawer-overlay"></div>
  <aside id="drawer-menu" class="drawer-menu">
    <div class="drawer-header">
      <div class="drawer-logo">PZ<span>8</span> Menu</div>
      <button id="drawer-close" class="drawer-close-btn"><i class="ph ph-x"></i></button>
    </div>
    <div class="drawer-content">
      
      <!-- Sezione Categorie -->
      <div>
        <div class="drawer-section-title">Categorie Canali</div>
        <nav class="drawer-list" id="drawer-categories">
          <!-- Inseriti dinamicamente da JS basandosi sulle categorie abilitate -->
        </nav>
      </div>

      <!-- Sezione Generica / Collegamenti -->
      <div>
        <div class="drawer-section-title">Navigazione</div>
        <nav class="drawer-list">
          <?php if (!$is_kids_profile): ?>
            <div id="btn-agenda" class="drawer-item"><i class="ph ph-clock"></i> Agenda Eventi</div>
          <?php endif; ?>
          <a href="guida.php" class="drawer-item"><i class="ph ph-calendar"></i> Guida TV</a>
          <div id="btn-settings" class="drawer-item"><i class="ph ph-gear"></i> Impostazioni</div>
          <a href="select_profile.php" class="drawer-item"><i class="ph ph-users"></i> Cambia Profilo</a>
          <a href="logout.php" class="drawer-item" style="color: var(--danger);"><i class="ph ph-sign-out"></i> Esci dall'Account</a>
        </nav>
      </div>

    </div>
    <div class="drawer-footer">
      <div style="font-size: 0.75rem; color: var(--text-muted); text-align: center;">PZ8 Mobile v1.6 &bull; EPG: <span id="epg-last-update"><?= $last_update ?></span></div>
    </div>
  </aside>

  <!-- MODAL AGENDA EVENTI -->
  <div id="agenda-modal" class="mobile-modal-overlay">
    <div class="mobile-modal-content">
      <div class="modal-header">
        <h2><i class="ph ph-clock" style="color:#eab308;"></i> Agenda Eventi</h2>
        <button class="modal-close-btn" onclick="closeModal('agenda-modal')">&times;</button>
      </div>
      <div class="modal-body" id="agenda-events-list">
        <!-- Popolato via JS -->
      </div>
    </div>
  </div>

  <!-- MODAL IMPOSTAZIONI -->
  <div id="settings-modal" class="mobile-modal-overlay">
    <div class="mobile-modal-content">
      <div class="modal-header">
        <h2><i class="ph ph-gear" style="color:var(--accent);"></i> Impostazioni</h2>
        <button class="modal-close-btn" onclick="closeModal('settings-modal')">&times;</button>
      </div>
      <div class="modal-body">
        
        <!-- Aspetto -->
        <div class="settings-color-row">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
            <span style="font-weight:700; font-size:0.95rem;">Tema dell'Interfaccia</span>
            <button id="theme-toggle" class="profile-avatar-btn" style="width:auto; padding:0 12px; border-radius:10px; font-size:0.8rem; font-weight:700; height:32px; display:flex; gap:6px;">
              <i class="ph ph-sun"></i> Tema
            </button>
          </div>
          
          <div style="margin-top:0.8rem;">
            <span style="font-weight:700; font-size:0.95rem; display:block; margin-bottom:0.5rem;">Colore Principale</span>
            <div class="color-option-grid" id="mobile-color-picker">
              <!-- Inseriti via JS -->
            </div>
          </div>
        </div>

        <!-- Profilo Attivo -->
        <div style="background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 1rem; margin-top: 1.5rem; display: flex; align-items: center; gap: 1rem;">
          <div style="width:48px; height:48px; border-radius:50%; background:<?= $active_profile['color'] ?>15; border:1px solid <?= $active_profile['color'] ?>40; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="ph <?= htmlspecialchars($active_profile['avatar']) ?>" style="color:<?= $active_profile['color'] ?>; font-size:1.6rem;"></i>
          </div>
          <div style="display:flex; flex-direction:column; flex:1; min-width:0;">
            <span style="font-weight:700; font-size:1rem;"><?= htmlspecialchars($active_profile['name']) ?></span>
            <span style="font-size:0.75rem; color:var(--text-muted);">Profilo attivo</span>
          </div>
          <a href="select_profile.php" class="profile-avatar-btn" style="border-radius:10px; width:auto; padding:0 12px; height:32px; font-size:0.8rem; font-weight:700; color:var(--text-secondary);"><i class="ph ph-users" style="margin-right:4px;"></i> Cambia</a>
        </div>

      </div>
    </div>
  </div>

  <!-- Script JS -->
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
                allowedCats = ['kids']; // Retrocompatibilità profili bambini vecchi
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.7.6/shaka-player.compiled.js"></script>
  <script src="js/mobile.js?v=<?= time() ?>"></script>
</body>
</html>
