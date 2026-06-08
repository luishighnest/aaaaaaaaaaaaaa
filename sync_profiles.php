<?php
// sync_profiles.php
// Questo script scarica i profili da Supabase e li salva nel JSON locale.

$supabase_url = getenv('SUPABASE_URL') . '/rest/v1/user_profiles';
$supabase_key = getenv('SUPABASE_KEY');
$profiles_file = __DIR__ . '/user_profiles.json';

if ($supabase_url && $supabase_key) {
    // Scarichiamo i profili per l'utente corrente dalla sessione
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
        $query_url = getenv('SUPABASE_URL') . '/rest/v1/user_profiles?username=eq.' . urlencode($username);
        
        $ch = curl_init($query_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURL_INFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $supabase_profiles = json_decode($response, true);
            
            // Riorganizziamo i dati come li vuole il tuo JSON
            $all_profiles = [];
            if ($supabase_profiles) {
                foreach ($supabase_profiles as $p) {
                    $all_profiles[$username][] = [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'avatar' => $p['avatar'],
                        'color' => $p['color'],
                        'allowed_categories' => $p['allowed_categories'],
                        'allowed_channels' => $p['allowed_channels'],
                        'favorites' => $p['favorites'],
                        'vod_favorites' => $p['vod_favorites']
                    ];
                }
                // Salviamo localmente
                file_put_contents($profiles_file, json_encode($all_profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }
}
?>
