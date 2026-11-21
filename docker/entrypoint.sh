#!/bin/sh
# Arranque del contenedor: certificado de la base, migraciones, configuración
# inicial de AcadeSys (idempotente) y Apache.
set -e
cd /var/www/html

# Aiven exige TLS: DB_CA_CERT trae el contenido del "CA certificate" del servicio.
if [ -n "$DB_CA_CERT" ]; then
  printf '%s\n' "$DB_CA_CERT" > /var/www/html/storage/db-ca.pem
  export MYSQL_ATTR_SSL_CA=/var/www/html/storage/db-ca.pem
fi

# APP_KEY puede ser cualquier secreto largo: si no tiene el formato de Laravel
# se deriva una clave de 32 bytes estable a partir de él.
if [ -n "$APP_KEY" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
  APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_KEY"), true));')"
  export APP_KEY
fi
if [ -z "$APP_KEY" ]; then
  echo "Falta APP_KEY" >&2
  exit 1
fi

# El puerto por defecto de Apache se reemplaza por el que asigna la plataforma.
sed -i 's/^Listen 80$//' /etc/apache2/ports.conf

php artisan config:clear >/dev/null 2>&1 || true
php artisan migrate --force
php artisan acadesys:preparar || echo "acadesys:preparar falló; revisa las variables FACT_ADMIN_*"
php artisan storage:link >/dev/null 2>&1 || true
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
php artisan route:cache || true

exec apache2-foreground
