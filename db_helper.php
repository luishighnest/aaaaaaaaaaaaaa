<?php
/**
 * db_helper.php
 * Funzioni centralizzate per l'accesso al database PostgreSQL.
 */
require_once __DIR__ . '/config_db.php';

/**
 * Recupera il profilo utente dal database.
 */
function get_user_profile($pdo, $profile_id) {
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE id = ?");
    $stmt->execute([$profile_id]);
    return $stmt->fetch();
}

/**
 * Aggiorna il profilo utente nel database.
 */
function update_user_profile($pdo, $profile_id, $data) {
    // Supponendo che i campi JSON siano già preparati come stringhe JSON
    $stmt = $pdo->prepare("
        UPDATE user_profiles 
        SET name = ?, avatar = ?, color = ?, 
            allowed_categories = ?, allowed_channels = ?, 
            favorites = ?, vod_favorites = ? 
        WHERE id = ?
    ");
    return $stmt->execute([
        $data['name'], $data['avatar'], $data['color'],
        json_encode($data['allowed_categories']), json_encode($data['allowed_channels']),
        json_encode($data['favorites']), json_encode($data['vod_favorites']),
        $profile_id
    ]);
}
?>
