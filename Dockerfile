# Usa la imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instala dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip exif pcntl bcmath

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configura directorio de Laravel
WORKDIR /var/www/html

# Copia archivos de la app
COPY . .

# Instala dependencias de PHP
RUN composer install --no-dev --optimize-autoloader

# Copia configuración personalizada de Apache
COPY ./docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Habilita mod_rewrite de Apache para Laravel
RUN a2enmod rewrite

# Asigna permisos a storage y bootstrap
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copia y usa el entrypoint personalizado
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

# Expone el puerto
EXPOSE 80