#!/bin/sh

set -e

# Préparer Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Démarrer PHP-FPM
php-fpm -D

# Démarrer Nginx au premier plan
nginx -g "daemon off;"
