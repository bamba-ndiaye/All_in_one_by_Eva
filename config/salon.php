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

    'fidelite' => [
        // Montant encaissé (FCFA) qui rapporte 1 point. 0 = programme de fidélité désactivé.
        'fcfa_par_point' => (int) env('FIDELITE_FCFA_PAR_POINT', 1000),
    ],

    // Coordonnées imprimées sur les reçus (facultatives).
    'recu' => [
        'adresse' => env('SALON_ADRESSE'),
        'telephone' => env('SALON_TELEPHONE'),
    ],
];
