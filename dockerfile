FROM php:8.2-apache

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Copiar archivos al contenedor
COPY . /var/www/html/

# Configurar Apache para usar la carpeta public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Configuración de Apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Exponer puerto 8080 (Railway usa este puerto)
EXPOSE 8080

# Comando de inicio
CMD ["apache2-foreground"]
