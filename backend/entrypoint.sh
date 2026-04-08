#!/bin/bash
set -e

echo "Waiting for database to be ready..."
max_retries=30
counter=0
until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    counter=$((counter + 1))
    if [ $counter -gt $max_retries ]; then
        echo "Database connection failed after $max_retries attempts"
        exit 1
    fi
    echo "   Attempt $counter/$max_retries - waiting 2s..."
    sleep 2
done

echo "Database connected"

echo "Running migrations..."
php artisan migrate --force --no-interaction

echo "Seeding database..."
php artisan db:seed --force --no-interaction 2>/dev/null || true

echo "Starting Laravel server..."
exec "$@"
