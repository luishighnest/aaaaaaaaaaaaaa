<?php
// Impedisci l'accesso diretto via URL
if (count(get_included_files()) === 1) {
    http_response_code(403);
    die('Accesso diretto non consentito.');
}

/**
 * users_config.php
 * Configurazione degli account e dei profili.
 * Contiene gli utenti reali con i rispettivi profili e hash delle password.
 */
return [
    'subscription_expiry' => '2027-12-31',
    'tmdb_api_key'        => '2e0b38cfb2936cec8ab1ce48e4335ac3',
    'users' => [
        'a' => [
            'password' => '$2y$12$5SbJLnbGLVzLwUTTrFjBi.WlgKiFPlYeEb8uvhWJyN0zA2nsA26MK',
            'profiles' => [
                ['id' => 'ale_main', 'name' => 'ale', 'avatar' => 'ph-user-circle', 'color' => '#00f2fe'],
                ['id' => 'ale_kids', 'name' => 'Bambini', 'avatar' => 'ph-smiley', 'color' => '#00e676'],
                ['id' => 'ale_guest', 'name' => 'Ospiti', 'avatar' => 'ph-users', 'color' => '#ff9800']
            ]
        ], // <-- Mancava questa chiusura e la virgola
        'Tyto' => [
            'password' => '$2y$12$XG2zFwgLQ9JFcfu4/gUPUeRqYoXsbuCPojKzG/jS5yJbtmOvPxfiW',
            'profiles' => [
                ['id' => 'Tyto_main', 'name' => 'Tyto', 'avatar' => 'ph-user-circle', 'color' => '#00f2fe'],
            ]
        ]
    ]
];
