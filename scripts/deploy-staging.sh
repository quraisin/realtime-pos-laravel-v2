#!/bin/bash
# ==============================================================================
# Script Otomatisasi Deployment STAGING PHASE - Qurais Kitchen POS
# Jalankan di VPS: bash scripts/deploy-staging.sh
# ==============================================================================

set -e

APP_DIR="/var/www/pos-staging"
BRANCH="staging"

echo "🚀 [STAGING] Memulai proses deployment staging..."
cd $APP_DIR

# 1. Tarik pembaruan kode terbaru dari Git
echo "📥 Menarik branch $BRANCH terbaru..."
git fetch origin
git checkout $BRANCH
git pull origin $BRANCH

# 2. Pastikan file database SQLite ada dan dapat diakses
if [ ! -f "$APP_DIR/database/database.sqlite" ]; then
    echo "📦 Membuat database SQLite staging..."
    touch "$APP_DIR/database/database.sqlite"
fi

# 3. Instal dependensi Composer (termasuk dev tools untuk testing di staging)
echo "📦 Menginstal dependensi PHP (Composer)..."
composer install --no-interaction --prefer-dist

# 4. Instal dependensi NPM dan build frontend bundle
echo "🎨 Membangun aset frontend dengan Vite..."
npm install --no-audit
npm run build

# 5. Jalankan migrasi database
echo "🗄️ Menjalankan migrasi database..."
php artisan migrate --force

# 6. Bersihkan cache lama agar perubahan terbaca seketika
echo "🧹 Membersihkan application cache..."
php artisan optimize:clear

# 7. Pastikan permission folder tepat
echo "🔒 Memperbaiki hak akses file..."
chown -R www-data:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache $APP_DIR/database
chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache
chmod 664 $APP_DIR/database/database.sqlite

# 8. Restart background queue worker
echo "⚙️ Memperbarui worker queue..."
if command -v supervisorctl >/dev/null 2>&1; then
    sudo supervisorctl restart pos-staging-worker:* || true
fi

echo "✅ [STAGING] Deployment berhasil selesai!"
