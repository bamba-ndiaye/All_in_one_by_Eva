<?php

return [

    /*
    | Compte administratrice créé par AdminSeeder.
    | Les valeurs viennent de .env : ne jamais les écrire ici.
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administratrice'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'rendez_vous' => [
        // Pause minimale entre deux rendez-vous d'une même personne (en minutes).
        'pause_minutes' => (int) env('RDV_PAUSE_MINUTES', 0),
    ],
];