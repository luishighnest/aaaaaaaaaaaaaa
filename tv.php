<?php
session_start();

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

// Rileva se profilo bambini
$pname_check = isset($active_profile['name']) ? strtolower($active_profile['name']) : '';
$pid_check = isset($active_profile['id']) ? strtolower($active_profile['id']) : '';
$is_kids_profile = (
    strpos($pname_check, 'bambini') !== false || 
    strpos($pname_check, 'kids') !== false || 
    strpos($pid_check, 'bambini') !== false || 
    strpos($pid_check, 'kids') !== false
);

// Genera token CSRF se mancante
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>PZ8 Smart TV</title>
  <meta name="description" content="Dashboard StreamHub Smart TV">
  <link rel="stylesheet" href="css/style_tv.css?v=<?= time() ?>">
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
  </script>
</head>
<body>

  <script>
    window.__EPG_DATA__    = <?= $epg_json ?>;
    window.__EPG_UPDATED__ = "<?= $last_update ?>";
    window.__ACTIVE_PROFILE_NAME__ = "<?= isset($active_profile['name']) ? addslashes($active_profile['name']) : '' ?>";
    window.__ACTIVE_PROFILE_ID__   = "<?= isset($active_profile['id']) ? addslashes($active_profile['id']) : '' ?>";
    window.__ACTIVE_PROFILE_FAVORITES__ = <?= json_encode($active_profile['favorites'] ?? []) ?>;
    window.__ALL_PROFILES__        = <?= $all_profiles_json ?>;
    window.__CSRF_TOKEN__          = "<?= $_SESSION['csrf_token'] ?>";
    window.__IS_KIDS_PROFILE__     = <?= $is_kids_profile ? 'true' : 'false' ?>;
  </script>

  <!-- Player Background -->
  <div class="tv-player-container">
    <video id="tv-video" autoplay playsinline></video>
    <iframe id="tv-iframe" style="display:none; width:100%; height:100%; border:none; background:#000;"></iframe>
  </div>

  <!-- TV Overlay UI -->
  <div class="tv-ui-overlay" id="tv-ui">
    
    <!-- Top Bar (Informazioni Canale e Ricerca) -->
    <header class="tv-top-bar">
      <div class="tv-top-left">
        <div class="tv-brand"><i class="ph ph-television"></i> <span></span></div>
        <div class="tv-current-info">
          <div class="tv-current-channel" id="tv-channel-name">PZ8 TV</div>
          <div class="tv-current-epg" id="tv-channel-epg">Scegli un canale per iniziare</div>
        </div>
      </div>
      <div class="tv-top-right">
        <div class="tv-search-bar tv-focusable" tabindex="-1">
          <i class="ph ph-magnifying-glass"></i>
          <input type="text" id="tv-search" placeholder="Cerca canale...">
        </div>
        <div class="tv-clock" id="tv-clock">00:00</div>
      </div>
    </header>

    <!-- Bottom Panel (Categorie e Canali) -->
    <div class="tv-bottom-panel">
      <!-- Riga Categorie (Scorrimento Orizzontale) -->
      <div class="tv-categories-row" id="tv-categories">
        <!-- Generato dinamicamente -->
      </div>
      
      <!-- Riga Canali (Scorrimento Orizzontale) -->
      <div class="tv-channels-row" id="tv-channels-row">
        <!-- Generato dinamicamente -->
      </div>
    </div>
    
  </div>

  <!-- Librerie -->
  <script src="js/channels.js?v=<?= time() ?>"></script>
  <script>
    if (typeof CHANNELS !== 'undefined') {
        const _activeProf = <?= json_encode($active_profile) ?>;
        let allowedCats = _activeProf.allowed_categories || [];
        let allowedChs = _activeProf.allowed_channels || [];

        if (allowedCats.length === 0 && allowedChs.length === 0) {
            if (window.__IS_KIDS_PROFILE__) {
                allowedCats = ['kids'];
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
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.7.6/shaka-player.compiled.js"></script>
  <script src="js/tv.js?v=<?= time() ?>"></script>

</body>
</html>
