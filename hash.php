<?php
$user_passwords = [
    'a' => 'a'
    'Tyto' =>'Sassuolomerda'
];

// Configuro le opzioni per forzare il costo a 12
$options = ['cost' => 12];

foreach ($user_passwords as $u => $p) {
    // Ora usiamo PASSWORD_BCRYPT con il costo esatto richiesto dal tuo sistema
    echo $u . ' => ' . $p . ' => ' . password_hash($p, PASSWORD_BCRYPT, $options) . "\n";
}
