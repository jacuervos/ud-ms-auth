#!/bin/bash

# Limpia y cachea configuración de Laravel
php artisan config:clear
php artisan cache:clear
php artisan config:cache

# Opcional: Ejecutar migraciones (si tu BD ya está lista)
# php artisan migrate --force

# Inicia Apache (comando original del contenedor)
exec apache2-foreground
