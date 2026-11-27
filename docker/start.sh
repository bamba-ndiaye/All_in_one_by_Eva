#!/bin/sh

set -e

# Mettre la base à jour (crée les tables, dont « sessions », sur une base vide)
php artisan migrate --force

# Préparer Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Démarrer PHP-FPM
php-fpm -D

# Démarrer Nginx au premier plan
nginx -g "daemon off;"
