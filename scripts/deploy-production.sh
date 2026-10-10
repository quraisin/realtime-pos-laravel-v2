#!/bin/bash
# ==============================================================================
# Script Otomatisasi Deployment PRODUCTION PHASE - Qurais Kitchen POS
# Jalankan di VPS: bash scripts/deploy-production.sh
# ==============================================================================

set -e

APP_DIR="/var/www/pos-production"
BRANCH="main"

echo "⚡ [PRODUCTION] Memulai proses deployment production zero-downtime..."
cd $APP_DIR

# 1. Aktifkan Maintenance Mode dengan secret bypass key (opsional)
echo "🛡️ Mengaktifkan maintenance mode..."
php artisan down --retry=60 || true

# 2. Tarik kode rilis terbaru dari Git branch main
echo "📥 Menarik branch $BRANCH terbaru..."
git fetch origin
git checkout $BRANCH
git pull origin $BRANCH

# 3. Instal dependensi PHP produksi (hanya non-dev dengan optimasi autoloader)
echo "📦 Menginstal dependensi Composer (Production optimized)..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 4. Instalasi NPM dan Build Aset Terkompresi
echo "🎨 Mengompilasi frontend bundle (Vite Production)..."
npm ci --no-audit
npm run build

# 5. Jalankan migrasi basis data (tanpa seeder destruktif)
echo "🗄️ Menjalankan migrasi basis data..."
php artisan migrate --force

# 6. Kompilasi & Cache Seluruh Konfigurasi, Rute, View, dan Event
echo "⚡ Mengaktifkan production caching..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Pastikan integritas hak akses folder
echo "🔒 Memverifikasi permission direktori..."
chown -R www-data:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache $APP_DIR/database
chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache
if [ -f "$APP_DIR/database/database.sqlite" ]; then
    chmod 664 $APP_DIR/database/database.sqlite
fi

# 8. Restart Supervisor Queue Workers
echo "⚙️ Memuat ulang Supervisor queue worker..."
if command -v supervisorctl >/dev/null 2>&1; then
    sudo supervisorctl restart pos-worker:* || true
fi

# 9. Reload PHP-FPM dan Nginx
echo "🔄 Memuat ulang PHP-FPM..."
sudo systemctl reload php8.2-fpm || true

# 10. Nonaktifkan Maintenance Mode
echo "🚀 Menonaktifkan maintenance mode..."
php artisan up

echo "🎉 [PRODUCTION] Deployment selesai dengan sukses dan sistem siap melayani pelanggan!"
