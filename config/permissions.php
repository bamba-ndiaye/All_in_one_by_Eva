<?php

return [

    /*
    | Catalogue de toutes les permissions de l'application.
    | Clé = nom utilisé dans le code (@can, $user->can, hasPermission), valeur = libellé affiché.
    */
    'catalogue' => [
        // Clients
        'clients.view' => 'Consulter les clients',
        'clients.create' => 'Ajouter un client',
        'clients.update' => 'Modifier un client',
        'clients.archive' => 'Archiver ou réactiver un client',
        'clients.export' => 'Exporter les clients en CSV',

        // Prestations
        'services.view' => 'Consulter les prestations et les tarifs',
        'services.manage' => 'Modifier les prestations, prix et durées',

        // Équipe
        'staff.view' => 'Consulter les fiches de l equipe',
        'staff.manage' => 'Gérer les employés et leurs horaires',
        'staff.view_earnings' => 'Voir commissions et performances financières',

        // Rendez-vous
        'appointments.view' => 'Consulter tous les rendez-vous',
        'appointments.view_own' => 'Consulter ses propres rendez-vous',
        'appointments.manage' => 'Créer, déplacer et annuler des rendez-vous',
        'appointments.update_status' => 'Mettre à jour le statut des prestations',
        'appointments.create_past' => 'Créer un rendez-vous dans le passé',

        // Caisse et finances
        'payments.view' => 'Consulter les paiements',
        'payments.record' => 'Enregistrer un paiement',
        'payments.correct' => 'Corriger ou annuler un paiement',
        'finance.view_revenue' => 'Voir le chiffre d affaires et le résultat',
        'expenses.manage' => 'Gérer les dépenses',

        // Stock
        'stock.view' => 'Consulter le stock',
        'stock.manage' => 'Gérer les produits et mouvements de stock',

        // Communication et fidélisation
        'messages.send' => 'Envoyer des messages WhatsApp individuels',
        'campaigns.manage' => 'Gérer les campagnes WhatsApp',
        'birthdays.view' => 'Consulter les anniversaires',
        'loyalty.view' => 'Consulter la fidélité des clients',
        'loyalty.manage' => 'Corriger les points et gérer les récompenses',

        // Rapports et administration
        'reports.view' => 'Consulter les rapports',
        'reports.export' => 'Exporter les rapports',
        'users.manage' => 'Gérer les utilisateurs et leurs droits',
        'settings.manage' => 'Modifier les paramètres du salon',
    ],

    /*
    | Droits par défaut des rôles. L'administratrice a tout : elle n'est pas listée.
    */
    'roles' => [
        'receptionniste' => [
            'clients.view', 'clients.create', 'clients.update',
            'services.view',
            'staff.view',
            'appointments.view', 'appointments.manage', 'appointments.update_status',
            'payments.view', 'payments.record',
            'messages.send', 'birthdays.view', 'loyalty.view',
        ],

        'professionnel' => [
            'services.view',
            'appointments.view_own', 'appointments.update_status',
        ],
    ],
];