#!/bin/sh
set -e

cd /var/www

mkdir -p storage/app/public storage/app/private storage/logs \
    storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

until php -r 'new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE")), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' > /dev/null 2>&1; do
  echo "En attente de PostgreSQL (${DB_HOST}:${DB_PORT:-5432}/${DB_DATABASE})..."
  sleep 2
done

# public/storage n'est pas dans l'image : le lien est recréé à chaque démarrage
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Les commandes ci-dessus tournent en root : sans ce second passage, PHP-FPM
# (www-data) ne pourrait plus écrire dans les fichiers qu'elles ont créés.
chown -R www-data:www-data storage/framework storage/logs bootstrap/cache

exec "$@"
