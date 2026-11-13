<?php

return [
    'items' => [
        ['label' => 'Tableau de bord', 'icon' => 'layout-dashboard', 'route' => 'dashboard',           'pattern' => 'dashboard',          'permission' => null],
        ['label' => 'Clients',         'icon' => 'users',            'route' => 'clients.index',       'pattern' => 'clients.*',          'permission' => 'clients.view'],
        ['label' => 'Rendez-vous',     'icon' => 'calendar-days',    'route' => 'rendez-vous.index',   'pattern' => 'rendez-vous.*',      'permission' => ['appointments.view', 'appointments.view_own']],
        ['label' => 'Prestations',     'icon' => 'scissors',         'route' => 'services.index',      'pattern' => 'services.*',         'permission' => 'services.view'],
        ['label' => 'Caisse',          'icon' => 'wallet',           'route' => 'paiements.index',     'pattern' => 'paiements.*',        'permission' => 'payments.view'],
        ['label' => 'Dépenses',        'icon' => 'receipt',          'route' => 'depenses.index',      'pattern' => 'depenses.*',         'permission' => 'expenses.manage'],
        ['label' => 'Stock',           'icon' => 'package',          'route' => 'produits.index',      'pattern' => 'produits.*',         'permission' => 'stock.view'],
        ['label' => 'Équipe',          'icon' => 'user-cog',         'route' => 'employes.index',      'pattern' => 'employes.*',         'permission' => 'staff.view'],
        ['label' => 'Anniversaires',   'icon' => 'cake',             'route' => 'anniversaires.index', 'pattern' => 'anniversaires.*',    'permission' => 'birthdays.view'],
        ['label' => 'Campagnes',       'icon' => 'message-circle',   'route' => 'campagnes.index',     'pattern' => 'campagnes.*',        'permission' => 'campaigns.manage'],
        ['label' => 'Fidélité',        'icon' => 'gift',             'route' => 'fidelite.index',      'pattern' => 'fidelite.*',         'permission' => 'loyalty.view'],
        ['label' => 'Rapports',        'icon' => 'trending-up',      'route' => 'rapports.index',      'pattern' => 'rapports.*',         'permission' => 'reports.view'],
        ['label' => 'Utilisateurs',    'icon' => 'shield-check',     'route' => 'utilisateurs.index',  'pattern' => 'utilisateurs.*',     'permission' => 'users.manage'],
        ['label' => 'Paramètres',     'icon' => 'settings',         'route' => 'parametres.index',    'pattern' => 'parametres.*',       'permission' => 'settings.manage'],
    ],
];
