# Dokumentasi Teknis Sistem Qurais Kitchen POS Realtime, Dashboard & Kitchen Display System (KDS)

> **Terakhir Diperbarui Otomatis:** 12 October 2026, 18:07:31 UTC  
> **Versi Sistem:** 1.1.0 (Dashboard & Multi-Portal Production Ready)  
> **Status Unit Test:** 54 Passed (280 Assertions) across 13 test files  

## 1. Arsitektur & Teknologi (Tech Stack)

| Komponen | Teknologi | Deskripsi |
| --- | --- | --- |
| **Backend Framework** | Laravel 12.x (PHP 8.2+) | RESTful APIs, Auth Session, Event Broadcasting, Blade Rendering |
| **Database** | SQLite (PDO SQLite) | Database relasional lokal cepat & portabel |
| **Role & Permission** | Spatie `laravel-permission` v6.25 | RBAC (Role-Based Access Control) bertingkat |
| **Frontend Core** | React 19 / Vite 7 | Embedded SPA React components di dalam Blade views |
| **Styling** | Tailwind CSS v4 & Custom Themes | Dark glassmorphism, responsive micro-animations |
| **Realtime Engine** | Server-Sent Events (SSE) & Broadcast Events | Realtime push `/api/kds/stream` + Laravel Events |
| **Thermal Printer** | Native ESC/POS Binary Engine & Web Print | Struk 58mm / 80mm, ESC/POS generator, Web Serial USB |
| **Audio Notifikasi** | Web Audio API Synthesized Chime | Bell chime 2-tone tanpa dependensi file eksternal |

## 2. Kredensial Autentikasi & Hak Akses (RBAC)

Sistem menyediakan 3 portal login terpisah dengan isolasi hak akses serta pengalihan otomatis berdasarkan peran:

| Peran (Role) | Username / Email | Password | Landing Route | Hak Akses Utama |
| --- | --- | --- | --- | --- |
| **Superadmin (Dashboard)** | `adnan@superadmin.com` | `Blessings777!` | `/cms` | Manajemen Menu, Kategori, Analitik Transaksi & Penjualan, Pengaturan Sistem |
| **Kasir (Cashier - Qurais Kitchen)** | `cashier@admin` | `behemoth` | `/pos` | Kasir POS, Katalog, Order, Transaksi, Void, Analitik, Printer |
| **Chef (KDS)** | `chef@admin` | `behemoth` | `/kds` | Antrean Dapur, Checklist Item, Status Masak, Tiket Dapur |

### Matriks Permission Spatie

- **Role `cashier`**:
  - `pos.access`
  - `transactions.access`
  - `transactions.void`
  - `analytics.access`
  - `printer.settings`
- **Role `kitchen`**:
  - `kds.access`
  - `kds.update_status`
- **Role `superadmin`**:
  - `pos.access`
  - `transactions.access`
  - `transactions.void`
  - `analytics.access`
  - `kds.access`
  - `kds.update_status`
  - `printer.settings`
  - `cms.access`
  - `cms.categories`
  - `cms.products`

## 3. Integrasi Mesin Thermal POS Printer & Putian POS

Sistem dirancang untuk terhubung dengan berbagai merek printer thermal POS, secara khusus dioptimalkan untuk **Putian POS Thermal** (USB002 / Generic Text Only) melalui 4 jalur terintegrasi:

### A. Perintah Standar ESC/POS Yang Dihasilkan (`EscPosReceiptService`)
- **Inisialisasi Printer:** `ESC @` (`\x1B\x40`)
- **Perataan Teks:** `ESC a 0` (Kiri), `ESC a 1` (Tengah), `ESC a 2` (Kanan)
- **Format Font:** Bold (`ESC E 1/0`), Ukuran Ganda Judul (`GS ! 0x11`), Normal (`GS ! 0x00`)
- **Pemotong Kertas Otomatis (Auto-Cutter):** `GS V 66 0` (`\x1D\x56\x42\x00`)
- **Tendang Laci Kasir (Cash Drawer Kick):** `ESC p 0 25 250` (`\x1B\x70\x00\x19\xFA`)
- **Format Ukuran Kertas:**
  - **58mm:** 32 kolom karakter per baris (Standar Putian POS-58)
  - **80mm:** 48 kolom karakter per baris (Standar Putian POS-80)

### B. Jalur Koneksi Printer Thermal
1. **Direct Windows Hardware Spooler (Putian POS Silent Auto-Print):**
   - Menggunakan skrip Win32 `winspool.Drv` `SendBytesToPrinter` RAW datatype (`scripts/raw_print.ps1`).
   - Mengirim byte stream ESC/POS langsung ke printer fisik Windows (`Putian POS Thermal 2` pada port USB002).
   - **100% Otomatis & Silent:** Setiap kali kasir menekan tombol bayar / menyelesaikan checkout, struk dicetak seketika tanpa membuka dialog print browser.
   - Dilengkapi deteksi otomatis daftar printer Windows (`GET /api/printer/installed-printers`) dan endpoint cetak ulang langsung (`POST /api/printer/print-order/{id}`).
2. **Windows System Print Driver (`window.print()`):** Fallback browser print menggunakan styling `@media print` terisolasi pada `#print-section` tanpa header/footer margin browser.
3. **Direct Web Serial API (Chrome/Edge):** Mengirim stream binary ESC/POS langsung melalui port USB/COM printer dengan baud rate 9600.
4. **Raw Binary File Download (`/api/orders/{id}/escpos-download`):** Menghasilkan file binary `.bin` yang dapat di-spooling via CLI (`copy /b file.bin USB002`).
5. **Thermal Receipt Simulator Modal:** Menampilkan visual struk interaktif dengan preview thermal paper, tombol *Cetak Putian POS*, *Download .bin*, dan tab Base64 ESC/POS inspection.

## 4. Mekanisme Notifikasi Realtime KDS (Kitchen Display System)

Setiap kali pesanan baru dibuat oleh kasir (`POST /api/pos/orders`):
1. Order disimpan dalam database transaksi atomic (stok produk langsung dikurangi).
2. Struk kasir langsung dikirim otomatis ke printer fisik Putian POS Thermal.
3. Event `App\Events\NewOrderCreated` dipancarkan.
4. Kunci cache `kds_last_update` diperbarui dengan timestamp mikrodetik.
5. Endpoint Server-Sent Events (`GET /api/kds/stream`) mendeteksi perubahan dan langsung mengirim event `update` ke layar KDS.
6. Browser Chef menerima order baru, memicu nada bel ganda (*Audio Chime* via Web Audio API 880Hz -> 1320Hz), dan memunculkan notifikasi visual berkedip.
7. Fitur fallback polling otomatis aktif apabila koneksi SSE terputus sementara.

## 5. Struktur Basis Data (Database Schema)

### Tabel `users`
Kolom: `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `username`

### Tabel `categories`
Kolom: `id`, `name`, `slug`, `icon`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`

### Tabel `products`
Kolom: `id`, `category_id`, `name`, `sku`, `price`, `cost_price`, `stock`, `image_url`, `description`, `is_available`, `created_at`, `updated_at`

### Tabel `orders`
Kolom: `id`, `order_number`, `customer_name`, `table_number`, `order_type`, `payment_method`, `payment_status`, `subtotal`, `tax_rate`, `tax_amount`, `discount_amount`, `total_amount`, `cash_tendered`, `change_due`, `kitchen_status`, `cashier_id`, `notes`, `kitchen_started_at`, `kitchen_ready_at`, `kitchen_completed_at`, `created_at`, `updated_at`, `receipt_printed_at`, `receipt_print_latency_ms`, `receipt_print_method`, `receipt_print_count`

### Tabel `order_items`
Kolom: `id`, `order_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`, `notes`, `is_prepared`, `created_at`, `updated_at`

### Tabel `printer_settings`
Kolom: `id`, `store_name`, `store_tagline`, `store_address`, `store_phone`, `paper_size`, `receipt_footer`, `auto_print_cashier`, `auto_print_kitchen`, `cut_paper`, `open_cash_drawer`, `created_at`, `updated_at`, `windows_printer_name`, `direct_hardware_print`

### Tabel `roles`
Kolom: `id`, `name`, `guard_name`, `created_at`, `updated_at`

### Tabel `permissions`
Kolom: `id`, `name`, `guard_name`, `created_at`, `updated_at`

## 6. Daftar Rute & Endpoint API

| Method | URI | Nama Rute | Action Controller |
| --- | --- | --- | --- |
| `GET` | `/log-viewer/api/hosts` | `log-viewer.hosts` | `HostsController@index` |
| `GET` | `/log-viewer/api/folders` | `log-viewer.folders` | `FoldersController@index` |
| `GET` | `/log-viewer/api/folders/{folderIdentifier}/download/request` | `log-viewer.folders.request-download` | `FoldersController@requestDownload` |
| `POST` | `/log-viewer/api/folders/{folderIdentifier}/clear-cache` | `log-viewer.folders.clear-cache` | `FoldersController@clearCache` |
| `DELETE` | `/log-viewer/api/folders/{folderIdentifier}` | `log-viewer.folders.delete` | `FoldersController@delete` |
| `GET` | `/log-viewer/api/files` | `log-viewer.files` | `FilesController@index` |
| `GET` | `/log-viewer/api/files/{fileIdentifier}/download/request` | `log-viewer.files.request-download` | `FilesController@requestDownload` |
| `POST` | `/log-viewer/api/files/{fileIdentifier}/clear-cache` | `log-viewer.files.clear-cache` | `FilesController@clearCache` |
| `DELETE` | `/log-viewer/api/files/{fileIdentifier}` | `log-viewer.files.delete` | `FilesController@delete` |
| `POST` | `/log-viewer/api/clear-cache-all` | `log-viewer.files.clear-cache-all` | `FilesController@clearCacheAll` |
| `POST` | `/log-viewer/api/delete-multiple-files` | `log-viewer.files.delete-multiple-files` | `FilesController@deleteMultipleFiles` |
| `GET` | `/log-viewer/api/logs` | `log-viewer.logs` | `LogsController@index` |
| `GET` | `/log-viewer/api/folders/{folderIdentifier}/download` | `log-viewer.folders.download` | `FoldersController@download` |
| `GET` | `/log-viewer/api/files/{fileIdentifier}/download` | `log-viewer.files.download` | `FilesController@download` |
| `GET` | `/log-viewer/{view?}` | `log-viewer.index` | `IndexController` |
| `GET` | `/up` | `-` | `Closure` |
| `GET` | `/` | `-` | `Closure` |
| `GET` | `/login` | `login` | `AuthController@showLogin` |
| `GET` | `/login/cashier` | `login.cashier` | `AuthController@showCashierLogin` |
| `GET` | `/login/kds` | `login.kds` | `AuthController@showKdsLogin` |
| `POST` | `/login` | `login.submit` | `AuthController@login` |
| `POST` | `/logout` | `logout` | `AuthController@logout` |
| `GET` | `/api/auth/me` | `auth.me` | `AuthController@me` |
| `GET` | `/cms/login` | `cms.login` | `CmsAuthController@showLogin` |
| `POST` | `/cms/login` | `cms.login.submit` | `CmsAuthController@login` |
| `POST` | `/cms/logout` | `cms.logout` | `CmsAuthController@logout` |
| `GET` | `/cms` | `cms.index` | `CmsController@index` |
| `GET` | `/cms/products` | `cms.products` | `CmsController@products` |
| `GET` | `/cms/categories` | `cms.categories` | `CmsController@categories` |
| `GET` | `/api/cms/categories` | `api.cms.categories` | `CmsController@getCategories` |
| `POST` | `/api/cms/categories` | `api.cms.categories.store` | `CmsController@storeCategory` |
| `PUT` | `/api/cms/categories/{id}` | `api.cms.categories.update` | `CmsController@updateCategory` |
| `DELETE` | `/api/cms/categories/{id}` | `api.cms.categories.delete` | `CmsController@deleteCategory` |
| `GET` | `/api/cms/products` | `api.cms.products` | `CmsController@getProducts` |
| `POST` | `/api/cms/products` | `api.cms.products.store` | `CmsController@storeProduct` |
| `GET` | `/api/cms/products/{id}` | `api.cms.products.show` | `CmsController@showProduct` |
| `PUT` | `/api/cms/products/{id}` | `api.cms.products.update` | `CmsController@updateProduct` |
| `PATCH` | `/api/cms/products/{id}/toggle-status` | `api.cms.products.toggle_status` | `CmsController@toggleProductStatus` |
| `PATCH` | `/api/cms/products/{id}/stock` | `api.cms.products.stock` | `CmsController@updateProductStock` |
| `DELETE` | `/api/cms/products/{id}` | `api.cms.products.delete` | `CmsController@deleteProduct` |
| `GET` | `/api/cms/analytics` | `api.cms.analytics` | `CmsController@getAnalytics` |
| `GET` | `/api/orders/{order}/receipt` | `api.orders.receipt` | `ThermalPrinterController@getReceipt` |
| `GET` | `/api/orders/{order}/kitchen-ticket` | `api.orders.kitchen_ticket` | `ThermalPrinterController@getKitchenTicket` |
| `GET` | `/api/items/{item}/kitchen-ticket` | `api.items.kitchen_ticket` | `ThermalPrinterController@getKitchenItemTicket` |
| `GET` | `/api/orders/{order}/escpos-download` | `api.orders.escpos_download` | `ThermalPrinterController@downloadEscPos` |
| `GET` | `/api/printer/settings` | `api.printer.settings` | `ThermalPrinterController@getSettings` |
| `POST` | `/api/printer/settings` | `api.printer.settings.update` | `ThermalPrinterController@updateSettings` |
| `GET` | `/api/printer/installed-printers` | `api.printer.installed` | `ThermalPrinterController@getInstalledPrinters` |
| `POST` | `/api/printer/print-order/{order}` | `api.printer.print_order` | `ThermalPrinterController@printOrderDirect` |
| `POST` | `/api/printer/print-item/{item}` | `api.printer.print_item` | `ThermalPrinterController@printItemDirect` |
| `POST` | `/api/printer/record-print-latency/{order}` | `api.printer.record_latency` | `ThermalPrinterController@recordClientPrintLatency` |
| `POST` | `/api/printer/test` | `api.printer.test` | `ThermalPrinterController@testPrint` |
| `GET` | `/pos` | `pos.index` | `PosController@index` |
| `GET` | `/api/pos/categories` | `api.pos.categories` | `PosController@getCategories` |
| `GET` | `/api/pos/products` | `api.pos.products` | `PosController@getProducts` |
| `POST` | `/api/pos/orders` | `api.pos.orders.create` | `PosController@createOrder` |
| `GET` | `/transactions` | `transactions.index` | `TransactionController@index` |
| `GET` | `/api/transactions` | `api.transactions.list` | `TransactionController@getTransactions` |
| `GET` | `/api/transactions/{id}` | `api.transactions.show` | `TransactionController@show` |
| `POST` | `/api/transactions/items/{itemId}/note` | `api.transactions.items.note` | `TransactionController@updateItemNote` |
| `POST` | `/api/transactions/{id}/notes` | `api.transactions.notes` | `TransactionController@updateOrderNote` |
| `POST` | `/api/transactions/{id}/void` | `api.transactions.void` | `TransactionController@voidOrder` |
| `GET` | `/api/transactions-export` | `api.transactions.export` | `TransactionController@export` |
| `GET` | `/analytics` | `analytics.index` | `AnalyticsController@index` |
| `GET` | `/api/analytics/summary` | `api.analytics.summary` | `AnalyticsController@getSummary` |
| `GET` | `/logs` | `logs.index` | `Closure` |
| `GET` | `/kds` | `kds.index` | `KdsController@index` |
| `GET` | `/api/kds/orders` | `api.kds.orders` | `KdsController@getOrders` |
| `POST` | `/api/kds/orders/{id}/status` | `api.kds.orders.status` | `KdsController@updateStatus` |
| `POST` | `/api/kds/items/{itemId}/toggle` | `api.kds.items.toggle` | `KdsController@toggleItemPrepared` |
| `GET` | `/api/kds/stream` | `api.kds.stream` | `KdsController@stream` |
| `GET` | `/storage/{path}` | `storage.local` | `Closure` |
| `PUT` | `/storage/{path}` | `storage.local.upload` | `Closure` |

## 7. Pengujian & Unit Tests (100% Passed)

Aplikasi dilengkapi dengan rangkaian unit & feature test yang mencakup seluruh alur bisnis sistem:
- `EscPosReceiptServiceTest`: Pengujian formatting teks, 2 kolom, centering, byte ESC/POS struk, dan tiket dapur.
- `AuthenticationTest`: Pengujian login kasir, login kds, validasi password, proteksi peran, dan logout.
- `PosSystemTest`: Pengujian katalog produk, pembuatan order, pemotongan stok otomatis, event realtime, validasi uang tunai.
- `KitchenDisplaySystemTest`: Pengujian daftar antrean, pergantian status (pending -> preparing -> ready -> completed), checklist item, stream SSE.
- `TransactionManagementTest`: Pengujian riwayat transaksi, detail struk, void transaksi & pengembalian stok, ekspor CSV.
- `AnalyticsTest`: Pengujian kalkulasi omset, AOV, produk terlaris, jam ramai, dan filter periode.
- `ThermalPrinterTest`: Pengujian konfigurasi printer, test print, download file binary `.bin`, deteksi printer Windows, dan cetak langsung order ke printer hardware.

Jalankan pengujian dengan perintah:
```bash
php artisan test
```

## 8. Panduan Deployment Server VPS DigitalOcean (Staging & Production Phase)

Dokumentasi ini menyajikan panduan komprehensif untuk melakukan deployment sistem **Qurais Kitchen POS & KDS** ke server **Virtual Private Server (VPS) DigitalOcean Droplet** berbasis sistem operasi **Ubuntu 24.04 LTS / 22.04 LTS**. Panduan ini terbagi secara ketat ke dalam 2 fase: **Staging Phase** (lingkungan pengujian & simulasi fitur) dan **Production Phase** (lingkungan operasional riil dengan stabilitas & keamanan maksimal).

---

### A. Matriks Perbandingan: Staging Phase vs. Production Phase

| Parameter | Staging Phase (Pengujian) | Production Phase (Operasional Riil) |
| :--- | :--- | :--- |
| **Tujuan Lingkungan** | UAT (*User Acceptance Test*), QA, uji coba fitur baru & regresi sebelum rilis | Transaksi kasir operasional harian, integritas finansial bisnis, zero-downtime |
| **Spesifikasi Droplet DigitalOcean** | Basic Droplet: 1 - 2 vCPU, 2 GB RAM, 50 GB SSD (Region: SGP1 / Singapore) | General Purpose / CPU-Optimized: 2+ vCPU, 4 - 8 GB RAM, 80+ GB NVMe (Region: SGP1) |
| **Domain / Subdomain** | `https://staging-pos.domainanda.com` | `https://pos.domainanda.com` |
| **Branch Git Target** | `staging` atau `develop` | `main` atau tag rilis `v1.x.x` |
| **Environment (`APP_ENV`)** | `staging` | `production` |
| **Debug Mode (`APP_DEBUG`)** | `true` (atau `false` terkontrol untuk simulasi log) | `false` (**Wajib `false`** untuk mencegah kebocoran kredensial & struktur kode) |
| **Basis Data (Database)** | SQLite Terpisah (`database/database.sqlite`) | SQLite (High-Performance WAL Mode) atau DigitalOcean Managed MySQL 8.0 |
| **Perlakuan Data Awal** | Dapat di-reset berkala (`migrate:fresh --seed`) | Migrasi aman tanpa drop data (`migrate --force`), dilarang seeder destruktif |
| **Manajemen Dependensi Composer** | `composer install` (termasuk dev tools untuk testing) | `composer install --no-dev --optimize-autoloader --no-interaction` |
| **Frontend Vite Build** | `npm install && npm run build` | `npm ci && npm run build` (Minified & Cache Busted) |
| **Optimasi Caching Laravel** | Dimatikan / di-clear berkala (`optimize:clear`) | Di-cache penuh (`config:cache`, `route:cache`, `view:cache`, `event:cache`) |
| **Queue Worker Daemon** | Supervisor worker 1 proses (`pos-staging-worker`) | Supervisor multi-worker 2-4 proses (`pos-worker`) dengan auto-restart |
| **Sertifikat SSL / HTTPS** | Let's Encrypt Certbot SSL Otomatis | Let's Encrypt Certbot SSL Otomatis + HTTP/2 + Security Headers Ketat |
| **Pencadangan Data (Backup)** | Manual sebelum pengujian besar | Otomatis harian via Cron ke DigitalOcean Spaces / AWS S3 + Droplet Backup |

---

### B. Prasyarat & Persiapan Awal Server VPS DigitalOcean (Droplet Setup)

Langkah awal ini dilakukan sekali saat pertama kali menyiapkan Droplet di DigitalOcean:

#### 1. Pembuatan Droplet DigitalOcean
1. Buka console DigitalOcean dan klik **Create > Droplets**.
2. Pilih Distribusi: **Ubuntu 24.04 LTS x64** (atau **Ubuntu 22.04 LTS x64**).
3. Pilih Plan:
   - **Staging**: *Basic*, Regular SSD, 2 GB RAM / 1 AMD vCPU (~$12/bln).
   - **Production**: *Basic* atau *General Purpose*, 4 GB RAM / 2 vCPU NVMe (~$24/bln).
4. Pilih Datacenter Region: **Singapore (SGP1)** (direkomendasikan untuk pengguna di Indonesia agar latensi di bawah 25ms).
5. Autentikasi: Pilih **SSH Key** (sangat disarankan daripada root password).
6. Tambahkan opsi: **Enable Backups** (terutama untuk Production).
7. Klik **Create Droplet** dan catat IP publik server (misal: `159.65.xxx.xxx`).

#### 2. Konfigurasi Pengguna Non-Root & Firewall UFW
Masuk ke server via SSH:
```bash
ssh root@159.65.xxx.xxx
```

Buat pengguna non-root bernama `deployer` dan berikan hak sudo:
```bash
adduser deployer
usermod -aG sudo deployer
usermod -aG www-data deployer

# Salin SSH keys ke pengguna deployer
rsync --archive --chown=deployer:deployer ~/.ssh /home/deployer
```

Aktifkan firewall UFW:
```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

#### 3. Instalasi PHP 8.2 FPM, Nginx, & Ekstensi yang Dibutuhkan
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip supervisor nginx ufw sqlite3 libsqlite3-dev
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalasi PHP 8.2 FPM beserta modul-modul Laravel
sudo apt install -y php8.2-fpm php8.2-cli php8.2-common php8.2-sqlite3 php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip php8.2-intl php8.2-opcache
```

Pastikan layanan PHP-FPM dan Nginx aktif:
```bash
sudo systemctl enable php8.2-fpm
sudo systemctl start php8.2-fpm
sudo systemctl enable nginx
sudo systemctl start nginx
```

#### 4. Instalasi Composer 2.x
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

#### 5. Instalasi Node.js (v22.x LTS) & NPM
```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

#### 6. Instalasi Certbot (Let's Encrypt SSL)
```bash
sudo apt install -y certbot python3-certbot-nginx
```

---

### C. FASE 1: Panduan Deployment Staging Phase

Lingkungan staging digunakan untuk pengujian fungsionalitas, uji alur kasir & KDS, verifikasi receipt, serta demo kepada stakeholder sebelum rilis ke produksi.

#### 1. Persiapan Direktori & Kloning Kode
```bash
sudo mkdir -p /var/www/pos-staging
sudo chown -R deployer:www-data /var/www/pos-staging

# Pindah ke user deployer
su - deployer

cd /var/www
git clone -b staging https://github.com/username/realtime-pos-system.git pos-staging
cd /var/www/pos-staging
```

#### 2. Konfigurasi Environment Staging (`.env`)
Salin file konfigurasi contoh:
```bash
cp .env.example .env
nano .env
```
Sesuaikan parameter penting untuk staging:
```ini
APP_NAME="Qurais Kitchen POS (Staging)"
APP_ENV=staging
APP_KEY=
APP_DEBUG=true
APP_URL=https://staging-pos.domainanda.com

DB_CONNECTION=sqlite
# Gunakan absolute path agar konsisten
DB_DATABASE=/var/www/pos-staging/database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=database

LOG_CHANNEL=stack
LOG_LEVEL=debug
```

#### 3. Instalasi Dependensi, Database, & Build Frontend
```bash
cd /var/www/pos-staging

# Buat file database SQLite staging
touch database/database.sqlite
chmod 664 database/database.sqlite
sudo chown deployer:www-data database/database.sqlite

# Install dependensi PHP
composer install

# Generate application key
php artisan key:generate

# Migrasi dan seed data demo lengkap
php artisan migrate:fresh --seed

# Install npm & compile bundle Vite
npm install
npm run build
```

#### 4. Pengaturan Hak Akses (Permissions)
```bash
sudo chown -R deployer:www-data /var/www/pos-staging
sudo chmod -R 775 /var/www/pos-staging/storage
sudo chmod -R 775 /var/www/pos-staging/bootstrap/cache
sudo chmod -R 775 /var/www/pos-staging/database
sudo chmod 664 /var/www/pos-staging/database/database.sqlite
```

#### 5. Konfigurasi Nginx Server Block Staging
Buat file konfigurasi Nginx:
```bash
sudo nano /etc/nginx/sites-available/pos-staging
```
Isi konfigurasi (perhatikan penanganan khusus unbuffered SSE untuk `/api/kds/stream`):
```nginx
server {
    listen 80;
    server_name staging-pos.domainanda.com;
    root /var/www/pos-staging/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 32M;

    # Log file
    access_log /var/log/nginx/pos-staging-access.log;
    error_log /var/log/nginx/pos-staging-error.log error;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Penanganan Khusus Server-Sent Events (SSE) KDS Realtime
    location /api/kds/stream {
        try_files $uri $uri/ /index.php?$query_string;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;

        # Disable FastCGI buffering agar event SSE terkirim secara instan
        fastcgi_buffering off;
        fastcgi_cache off;
        fastcgi_read_timeout 3600s;
        proxy_buffering off;
        chunked_transfer_encoding off;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ \.(sqlite|sqlite3|db|env|log)$ {
        deny all;
    }
}
```
Aktifkan konfigurasi dan periksa sintaks:
```bash
sudo ln -s /etc/nginx/sites-available/pos-staging /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### 6. Penerbitan Sertifikat SSL Staging (Certbot)
```bash
sudo certbot --nginx -d staging-pos.domainanda.com
```

#### 7. Konfigurasi Supervisor Queue Worker Staging
Buat file `/etc/supervisor/conf.d/pos-staging-worker.conf`:
```ini
[program:pos-staging-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pos-staging/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/pos-staging/storage/logs/worker.log
```
Muat ulang supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pos-staging-worker:*
```

#### 8. Eksekusi Skrip Pembaruan Staging Otomatis
Telah disediakan skrip `scripts/deploy-staging.sh` di dalam repositori. Kapan pun ada commit baru di branch `staging`, jalankan:
```bash
bash /var/www/pos-staging/scripts/deploy-staging.sh
```

---

### D. FASE 2: Panduan Deployment Production Phase

Lingkungan produksi dirancang untuk keandalan maksimal (*high availability*), isolasi keamanan ketat, integritas catatan keuangan, serta performa tinggi dengan caching terkompilasi.

#### 1. Persiapan Direktori & Kloning Kode Rilis
```bash
sudo mkdir -p /var/www/pos-production
sudo chown -R deployer:www-data /var/www/pos-production

cd /var/www
git clone -b main https://github.com/username/realtime-pos-system.git pos-production
cd /var/www/pos-production
```

#### 2. Konfigurasi Environment Production (`.env`)
```bash
cp .env.example .env
nano .env
```
Konfigurasi wajib untuk produksi:
```ini
APP_NAME="Qurais Kitchen POS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pos.domainanda.com

# Database Opsi A: SQLite Berkecepatan Tinggi (WAL Mode)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/pos-production/database/database.sqlite

# Atau Database Opsi B: DigitalOcean Managed MySQL Database
# DB_CONNECTION=mysql
# DB_HOST=db-mysql-sgp1-xxxx-do-user-xxxx.b.db.ondigitalocean.com
# DB_PORT=25060
# DB_DATABASE=qurais_kitchen_pos
# DB_USERNAME=doadmin
# DB_PASSWORD=SecretProdPassword!
# MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
CACHE_STORE=database

LOG_CHANNEL=daily
LOG_LEVEL=warning
```

#### 3. Setup Basis Data SQLite Mode Produksi (WAL Mode)
Jika menggunakan SQLite pada VPS produksi, aktifkan mode **Write-Ahead Logging (WAL)** agar pembacaan (KDS streaming / analitik) dan penulisan (checkout kasir) tidak saling mengunci (*lock starvation*):
```bash
cd /var/www/pos-production
touch database/database.sqlite
chmod 664 database/database.sqlite
sudo chown deployer:www-data database/database.sqlite

# Optimasi WAL mode dan busy timeout
sqlite3 database/database.sqlite "PRAGMA journal_mode=WAL;"
sqlite3 database/database.sqlite "PRAGMA busy_timeout=5000;"
sqlite3 database/database.sqlite "PRAGMA synchronous=NORMAL;"
```

#### 4. Instalasi Dependensi & Build Aset Produksi
```bash
# Instalasi dependensi PHP murni produksi (tanpa development packages)
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# Generate production application key
php artisan key:generate --force

# Jalankan migrasi database
php artisan migrate --force

# Seed pengguna administratif awal (hanya jika database baru)
php artisan db:seed --class=RoleAndPermissionSeeder --force
php artisan db:seed --class=UserSeeder --force

# Kompilasi frontend bundle menggunakan npm ci (clean install)
npm ci --no-audit
npm run build
```

#### 5. Optimasi & Pemanasan Cache Laravel (*Performance Warmup*)
Jalankan kompilasi cache konfigurasi, rute, view Blade, dan event:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

#### 6. Hak Akses File Ketat
```bash
sudo chown -R deployer:www-data /var/www/pos-production
sudo chmod -R 775 /var/www/pos-production/storage
sudo chmod -R 775 /var/www/pos-production/bootstrap/cache
sudo chmod -R 775 /var/www/pos-production/database
sudo chmod 664 /var/www/pos-production/database/database.sqlite
```

#### 7. Konfigurasi Nginx Production (Menggunakan Template `scripts/nginx-pos.conf`)
Salin template konfigurasi Nginx yang telah disediakan:
```bash
sudo cp /var/www/pos-production/scripts/nginx-pos.conf /etc/nginx/sites-available/pos-production
sudo nano /etc/nginx/sites-available/pos-production
```
Pastikan `server_name` disesuaikan dengan domain produksi (`pos.domainanda.com`), lalu aktifkan:
```bash
sudo ln -s /etc/nginx/sites-available/pos-production /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### 8. Penerbitan Sertifikat SSL Let's Encrypt Production
```bash
sudo certbot --nginx -d pos.domainanda.com
```
Uji perpanjangan otomatis (*renewal dry-run*):
```bash
sudo certbot renew --dry-run
```

#### 9. Konfigurasi Supervisor Queue Worker Production
Salin template konfigurasi Supervisor:
```bash
sudo cp /var/www/pos-production/scripts/supervisor-pos.conf /etc/supervisor/conf.d/pos-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pos-worker:*
```
Periksa status worker:
```bash
sudo supervisorctl status
```

#### 10. Konfigurasi Cron Job Laravel Scheduler
Jadwalkan pembersihan log, pembatalan order kadaluarsa, dan rekap otomatis:
```bash
sudo crontab -u www-data -e
```
Tambahkan baris berikut:
```cron
* * * * * cd /var/www/pos-production && php artisan schedule:run >> /dev/null 2>&1
```

#### 11. Skrip Deployment Zero-Downtime Produksi
Untuk deployment pembaruan versi rilis berikutnya, gunakan skrip `scripts/deploy-production.sh` yang otomatis mengaktifkan mode maintenance singkat, menarik kode, migrasi aman, membangun ulang aset, dan me-refresh cache:
```bash
bash /var/www/pos-production/scripts/deploy-production.sh
```

---

### E. Integrasi Hardware Thermal Printer di Lingkungan Cloud VPS

Karena server aplikasi berada di cloud DigitalOcean dan printer kasir berada di toko fisik:

1. **Jalur Utama: Web Serial API (Chrome / Edge)**
   - Kasir membuka aplikasi via HTTPS (`https://pos.domainanda.com/pos`).
   - Sambungkan printer thermal Putian POS via kabel USB ke laptop/PC kasir.
   - Pada modal cetak struk POS, pilih opsi **"Web Serial USB"**.
   - Browser kasir berkomunikasi langsung secara binary dengan printer thermal lokal tanpa memerlukan server lokal tambahan.
2. **Jalur Alternatif: Browser Native Print Driver (`window.print()`)**
   - Kasir memilih mode cetak driver sistem.
   - Styling cetak `@media print` telah diatur khusus untuk kertas 58mm (32 kolom) atau 80mm (48 kolom) tanpa header/footer URL browser.
3. **Jalur Advanced: Local Print Spooler Agent (Windows Kasir)**
   - Jika toko memiliki banyak kasir dan membutuhkan *silent auto-print* melalui Windows Print Spooler (`winspool.drv`), PC kasir lokal dapat menjalankan agent lokal ringan yang menerima payload dari cloud via Webhook atau Web Serial untuk memanggil skrip `scripts/raw_print.ps1`.

---

### F. Pemeliharaan, Backup Otomatis, & Monitoring

#### 1. Backup Basis Data Otomatis Harian ke DigitalOcean Spaces (S3)
Buat skrip backup `/home/deployer/backup-pos.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/home/deployer/backups"
DATE=$(date +'%Y-%m-%d_%H%M%S')
mkdir -p $BACKUP_DIR

# Backup SQLite database
sqlite3 /var/www/pos-production/database/database.sqlite ".backup '$BACKUP_DIR/pos_backup_$DATE.sqlite'"
gzip "$BACKUP_DIR/pos_backup_$DATE.sqlite"

# Hapus backup lokal lebih dari 7 hari
find $BACKUP_DIR -name "*.sqlite.gz" -mtime +7 -delete

# (Opsional) Sinkronkan ke DigitalOcean Spaces menggunakan s3cmd atau rclone
# s3cmd put "$BACKUP_DIR/pos_backup_$DATE.sqlite.gz" s3://qurais-backups/pos/
```
Jadwalkan cron backup setiap hari pukul 02:00 pagi:
```cron
0 2 * * * bash /home/deployer/backup-pos.sh >> /home/deployer/backup.log 2>&1
```

#### 2. Rotasi Log (`logrotate`)
Buat file `/etc/logrotate.d/laravel-pos`:
```text
/var/www/pos-production/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
}
```

#### 3. Monitoring Log & Health Check Endpoint
- **Health Check**: Akses `https://pos.domainanda.com/up` untuk verifikasi uptime droplet.
- **Log Viewer UI**: Superadmin dapat memantau error log secara langsung melalui `https://pos.domainanda.com/log-viewer`.


## 9. Mekanisme Pembaruan Dokumentasi Otomatis

Sistem ini memiliki perintah otomatis untuk memperbarui dokumentasi teknis setiap kali terjadi modifikasi pada skema database, rute, peran, atau kode sistem:
```bash
php artisan docs:update
```
Perintah ini membaca secara reflektif seluruh rute aktif, struktur tabel SQLite, permission Spatie, dan data test untuk menyusun ulang file ini secara konsisten.
