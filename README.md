# 🍽️ Qurais Kitchen - Realtime POS, Dashboard & Kitchen Display System (KDS)

[![Laravel](https://img.shields.io/badge/Laravel-v12.69.3-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4.27_(Herd)_%7C_%5E8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Composer](https://img.shields.io/badge/Composer-2.10.2-885630?style=for-the-badge&logo=composer&logoColor=white)](https://getcomposer.org)
[![React](https://img.shields.io/badge/React-19.3.0-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://react.dev)
[![Vite](https://img.shields.io/badge/Vite-7.3.7-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4.3.3-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Tests](https://img.shields.io/badge/Tests-64%20Passed%20(340%20Assertions)-success?style=for-the-badge&logo=phpunit&logoColor=white)](https://phpunit.de)

**Qurais Kitchen POS** adalah sistem aplikasi kasir (*Point of Sale*) modern, **Qurais Kitchen Dashboard** terpadu untuk Superadmin, *Kitchen Display System* (KDS) *real-time*, dan mesin cetak printer thermal (*ESC/POS engine*) yang dirancang khusus untuk restoran, kafe, dan bisnis kuliner berkecepatan tinggi.

Aplikasi ini menyinkronkan alur operasional bisnis secara utuh: mulai dari pengelolaan katalog menu & kategori oleh Superadmin, pemesanan kasir, pengiriman tiket pesanan ke dapur secara *real-time* via **Server-Sent Events (SSE)**, hingga pencetakan struk transaksi langsung ke mesin printer thermal fisik.

---

## 🌟 Fitur Unggulan Sistem

### 1. 🎛️ Superadmin Dashboard & Manajemen Menu / Kategori (`/cms`)
* **Portal Login Terpisah**: Gerbang login khusus Superadmin (`/cms/login`) yang terisolasi dari portal kasir transaksi dan dapur (KDS).
* **Dashboard Analitik Transaksi Mendalam**:
  * **Metrik Finansial Realtime**: Total Penjualan Kotor (*Gross Revenue*), Estimasi Laba Bersih (*Net Profit = Gross Revenue - COGS*), Persentase Margin Keuntungan, Total Transaksi, Rata-rata Nilai Pesanan (*AOV*), dan Total Porsi Terjual.
  * **Trending & Best-Selling Menus**: Tabel menu terlaris dengan kalkulasi profit margin, ranking penjualan, estimasi laba per menu, dan indikator sisa stok.
  * **Visualisasi Tren Pendapatan & Peak Hours**: Grafik bar interaktif harian dengan tooltip omset per hari serta jam ramai transaksi (*peak hours* 08:00 - 22:00).
  * **Distribusi Penjualan Kategori & Metode Bayar**: Komposisi kontribusi omset makanan vs minuman vs camilan, serta persentase pembayaran tunai, QRIS, dan kartu EDC.
  * **Peringatan Stok Menipis (*Low Stock Alert*)**: Banner deteksi otomatis untuk menu yang tersisa $\le 15$ porsi dengan tombol pintas penyesuaian stok.
  * **Live Recent Transactions Feed**: Pantauan 10 transaksi terakhir kasir lengkap dengan nomor order, pelanggan, meja, total bayar, dan status dapur.
* **Kelola Menu (Katalog Produk)**:
  * Tambah, ubah, dan hapus menu dengan formulir interaktif.
  * Auto-generate SKU berbasis kategori.
  * Pemilih foto makanan cepat (*Preset food photo selector*) dari Unsplash beresolusi tinggi.
  * Kalkulasi margin keuntungan real-time saat memasukkan harga modal (HPP) dan harga jual.
  * Fitur cepat: Toggle status aktif/nonaktif dan sesuaikan jumlah stok langsung dari kartu menu.
* **Kelola Kategori Menu**:
  * Pengaturan nama, slug URL, ikon (*utensils, coffee, cookie, ice-cream, pizza, flame*), palet warna aksen kustom, dan urutan tampil (*sort order*).
  * Penghitung otomatis total menu terdaftar per kategori dan proteksi penghapusan jika kategori masih memiliki menu aktif.

---

### 2. 🛒 Point of Sale (POS) Kasir Qurais Kitchen (`/pos`)
* **Katalog Produk Dinamis**: Filter kategori makanan, minuman, snack, dan dessert dengan fitur pencarian instan (*live search*).
* **Kalkulasi Checkout Lengkap**: Subtotal, diskon fleksibel, pajak restoran (PB1 10%), total pembayaran, uang tunai, dan perhitungan kembalian otomatis.
* **Manajemen Stok Realtime**: Validasi stok real-time saat penambahan item dan pengurangan stok otomatis saat pesanan dibuat.
* **Audio & Visual Feedback**: Efek suara kasir (*audio chime*) dan animasi selebrasi *confetti* saat transaksi sukses.
* **Modal Struk Transaksi Autentik**: Simulasi tampilan kertas struk thermal bergaya robekan autentik (*jagged paper tear*) bertuliskan *"Qurais Kitchen"*, responsif terhadap gulungan kertas panjang.
* **Pelacakan Latency Cetak**: Menampilkan waktu dan latency cetak struk dari saat pesanan dibuat hingga struk berhasil dicetak (dalam milidetik).

---

### 3. 🍳 Kitchen Display System (KDS) (`/kds`)
* **Sinkronisasi Realtime & Resilient Polling**: Memanfaatkan jalur **Server-Sent Events (SSE)** dan *resilient auto-polling* 3 detik sehingga pesanan baru dari kasir langsung muncul di layar dapur secara instan tanpa lag atau blocking.
* **Ribbon "NEW ORDER" Interaktif**: Penanda pesanan baru dengan animasi *ribbon pulsing* dan penghitung mundur (*countdown timer*) 5 detik sebelum memudar otomatis.
* **Checklist Persiapan Menu & Auto-Ready**: Koki dapat mencentang setiap item pesanan yang sudah selesai dimasak dengan animasi responsif dan auto-transisi status ke "Siap Saji" saat seluruh menu dicentang.
* **Cetak Tiket Per-Menu & Tiket Pesanan Lengkap ke Thermal Printer**: Setiap menu makanan/minuman pada antrean KDS dilengkapi tombol cetak khusus per-item (`Cetak Menu`), serta tombol cetak tiket seluruh pesanan (`Cetak Tiket`) langsung ke mesin thermal printer.
* **Status Pipeline Responsif**: Transisi status pesanan dengan tombol cepat: `Menunggu` ➔ `Mulai Memasak` ➔ `Pesanan Siap` ➔ `Selesai / Diambil`.

---

### 4. 🖨️ Mesin Struk ESC/POS & Hardware Thermal Printer Multi-OS
* **Generasi Binary ESC/POS Presisi**: Didukung oleh `EscPosReceiptService` yang menghasilkan *byte command* standar ESC/POS dengan *software word-wrapping* (`wrapLine()` & `centerText()`), perataan 2 kolom otomatis, pemotongan kertas (*auto-cutter*), *paper feed* margin bawah, dan *pulse* laci kasir.
* **Multi-OS Direct Hardware Spooler Printing (Windows, macOS Ventura, Linux)**:
  * **Windows**: Cetak otomatis langsung ke Windows Print Spooler (`winspool.Drv` RAW via `scripts/raw_print.ps1`).
  * **macOS & Linux**: Mendukung **CUPS Raw Queue** (`lp -d <printer> -o raw` & `lpr -o raw`), auto-registrasi printer USB via `lpinfo -v` / `lpadmin`, serta penulisan langsung ke *device node* USB (`/dev/cu.usb*`, `/dev/tty.usb*`, `/dev/usb/lp*`).
* **Universal Browser USB Printing (WebUSB & Web Serial API)**: Dukungan pengiriman stream binary ESC/POS langsung lewat kabel USB dari browser (Google Chrome / Edge / Brave) menggunakan **WebUSB API** (`navigator.usb` pada interface USB Printer Class `0x07` dengan *64-byte packet pacing*) dan fallback **Web Serial API** (`navigator.serial`) tanpa memerlukan driver OS.
* **Sistem Pencatatan Latency Cetak**: Mengukur dan mencatat secara presisi waktu (*latency*) dari saat order dibuat (`created_at`) hingga struk tercetak ke mesin printer, disimpan ke database (`receipt_print_logs`) dan dicatat ke log Laravel.
* **Dukungan Ukuran Kertas Fleksibel**: Kompatibel dengan ukuran standar **58mm (32 kolom)** dan **80mm (48 kolom)** tanpa pemotongan kata di tengah baris.
* **Ekspor & Download Binary**: Opsi salin teks plain dan download file mentah `.bin` untuk keperluan arsip atau debugging socket TCP/USB.

---

### 5. 📋 Manajemen Transaksi & Void (`/transactions`)
* **Riwayat Transaksi Komprehensif**: Daftar lengkap seluruh transaksi kasir dengan filter rentang tanggal, status pembayaran, dan metode bayar.
* **Fitur Void Order & Restorasi Stok**: Pembatalan transaksi resmi dengan proteksi izin kasir (*permission-guarded*) yang secara otomatis mengembalikan stok produk ke gudang.
* **Ekspor CSV**: Kemampuan ekspor seluruh data transaksi ke file spreadsheet CSV dengan satu klik.

---

### 6. 📑 Laravel Log Viewer & Categorized Feature Logging (`/log-viewer` & `/logs`)
* **Integrasi `opcodesio/log-viewer`**: UI modern untuk monitoring log aplikasi Laravel dengan tema gelap (*dark mode*) dan navigasi langsung ke POS.
* **Logging Terkelompok Per-Fitur (*Feature-Grouped Channels*)**: Setiap fitur aplikasi memiliki kanal log terdedikasi yang tercatat ke dalam file terpisah (`storage/logs/*.log`) serta terkonsolidasi di `laravel.log`:
  * `auth.log`: Aktivitas login berhasil/gagal, logout, dan pembatasan hak akses (RBAC).
  * `pos.log`: Transaksi checkout kasir, validasi kecukupan stok produk, dan pembayaran tunai.
  * `transaction.log`: Riwayat transaksi, pembatalan (*void order*), perubahan catatan menu/transaksi, dan ekspor CSV.
  * `kds.log`: Siklus status pesanan dapur (`pending` ➔ `preparing` ➔ `ready` ➔ `completed`) dan checklist koki.
  * `cms.log`: Pengelolaan katalog produk/menu, penyesuaian stok, status aktif/nonaktif, dan kategori Superadmin.
  * `printer.log`: Riwayat perintah spooler printer Windows, cetak struk kasir & dapur, latensi cetak, dan konfigurasi printer.
  * `analytics.log`: Pemanggilan data analitik dan laporan penjualan.
* **Format Terstruktur & Metadata Lengkap**: Format log standar `[FITUR] [ACTION] Pesan`, dilengkapi metadata ID pengguna, alamat IP, dan nomor referensi transaksi.
* **Filter Severity & Latency Tracking**: Memantau event `[ORDER CREATED]`, latensi cetak struk `[RECEIPT PRINTED]`, hingga peringatan stok secara instan.

---

## 🔐 Kredensial & Hak Akses (Role-Based Access Control)

Sistem menyediakan **3 portal login terpisah** dengan isolasi peran menggunakan **Spatie Laravel Permission**:

| Peran (Role) | Username / Email | Password | Landing Route | Hak Akses Modul |
| :--- | :--- | :--- | :--- | :--- |
| **Superadmin (Dashboard)** | `adnan@superadmin.com` | `Blessings777!` | `/cms` | **Akses Penuh**: Qurais Kitchen Dashboard, Kelola Menu & Kategori, Analitik Penjualan & Keuntungan, Kasir POS, KDS Dapur, Pengaturan Printer |
| **Kasir (Qurais Kitchen)** | `cashier@admin` | `behemoth` | `/pos` | **Kasir & Operasional**: POS Kasir, Riwayat Transaksi, Void Order, Ekspor CSV, Analisis Penjualan, Pengaturan Printer |
| **Chef / Koki (KDS)** | `chef@admin` | `behemoth` | `/kds` | **Dapur**: Kitchen Display System (KDS), Checklist Item, Status Masak, Tiket Dapur |

---

## 🛠️ Tech Stack & Arsitektur (Versi Aktif)

### 1. Runtime & Environment Server Aktif
| Komponen | Versi Aktif (Terinstal) | Spesifikasi / Keterangan |
| :--- | :--- | :--- |
| **PHP Runtime** | **`8.4.27`** (`^8.2` di `composer.json`) | CLI NTS (`clang 17.0.0`), Built by **Laravel Herd**, Zend Engine `v4.4.27`, Zend OPcache `v8.4.27` |
| **Composer** | **`2.10.2`** | Dependency Manager PHP (`/Users/macventura/Library/Application Support/Herd/bin/composer`) |
| **Node.js (Herd NVM)** | **`v22.23.4`** (`v20.20.2` tersedia) | JavaScript Runtime untuk kompilasi aset Vite & React |
| **Local Web Server** | **Laravel Herd** / Artisan Serve | Mendukung domain `.test` otomatis di macOS Ventura & Windows |
| **Database Engine** | **SQLite 3** (`database.sqlite`) | Kompatibel penuh dengan MySQL 8.0 / MariaDB |

### 2. Backend Stack & Paket Composer (`composer.json` & `composer.lock`)
| Paket Composer | Constraint `composer.json` | Versi Aktif (`composer.lock`) | Fungsi Utama |
| :--- | :--- | :--- | :--- |
| [`laravel/framework`](https://laravel.com) | `^12.0` | **`v12.69.3`** | Core Framework Backend, Routing, Eloquent ORM, SSE Stream, Events |
| [`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission) | `^6.25` | **`6.25.0`** | Role-Based Access Control (RBAC: `superadmin`, `cashier`, `kitchen`) |
| [`opcodesio/log-viewer`](https://github.com/opcodesio/log-viewer) | `^3.24` | **`v3.24.2`** | Antarmuka Web Monitoring Log Terkategori (`/log-viewer`) |
| [`laravel/tinker`](https://github.com/laravel/tinker) | `^2.10.1` | **`v2.11.1`** | REPL Interaktif untuk inspeksi model & database Laravel |
| `phpunit/phpunit` *(dev)* | `^11.5.50` | **`11.5.57`** | Automated Unit & Feature Testing Suite (64 Tests / 340 Assertions) |
| `laravel/pint` *(dev)* | `^1.24` | **`v1.30.4`** | Opinionated PHP Code Style Fixer |
| `laravel/pail` *(dev)* | `^1.2.2` | **`v1.2.7`** | Real-time CLI Log Tailer |
| `laravel/sail` *(dev)* | `^1.41` | **`v1.68.0`** | Docker Development Environment |
| `nunomaduro/collision` *(dev)* | `^8.6` | **`v8.9.5`** | CLI Error Handler & Reporting untuk PHPUnit / Artisan |
| `mockery/mockery` *(dev)* | `^1.6` | **`1.6.15`** | Mocking Framework untuk pengujian unit |
| `fakerphp/faker` *(dev)* | `^1.23` | **`v1.24.1`** | Generator data sampel pada Database Seeder & Factory |

### 3. Frontend Stack & Paket NPM (`package.json` & `package-lock.json`)
| Paket NPM | Constraint `package.json` | Versi Aktif (`package-lock.json`) | Fungsi Utama |
| :--- | :--- | :--- | :--- |
| [`react`](https://react.dev) & `react-dom` | `^19.3.0` | **`19.3.0`** | UI Library Interaktif (POS, KDS Realtime, CMS, Analytics, Modals) |
| [`vite`](https://vitejs.dev) | `^7.0.7` | **`7.3.7`** | Next-Generation Frontend Bundler & HMR |
| [`tailwindcss`](https://tailwindcss.com) & `@tailwindcss/vite` | `^4.0.0` | **`4.3.3`** | Utility-first CSS Framework v4 dengan tema Dark Glassmorphism |
| `laravel-vite-plugin` | `^2.0.0` | **`2.1.0`** | Integrasi pipeline aset Vite dengan Blade Laravel |
| `@vitejs/plugin-react` | `^5.2.0` | **`5.2.0`** | Fast Refresh & JSX Compiler untuk React 19 |
| `axios` | `^1.11.0` | **`1.20.0`** | HTTP Client komunikasi asinkron ke endpoint API Laravel |
| `lucide-react` | `^1.52.0` | **`1.52.0`** | Koleksi ikon vektor modern |
| `canvas-confetti` | `^1.9.4` | **`1.9.4`** | Animasi selebrasi visual saat transaksi kasir berhasil |
| `concurrently` | `^9.0.1` | **`9.2.4`** | Menjalankan server PHP, Queue Worker, dan Vite secara paralel |

### 4. Arsitektur Layanan Printer Thermal Multi-OS
* **`EscPosReceiptService`**: Engine generator byte biner ESC/POS & teks plain 58mm (32 kolom) dan 80mm (48 kolom) dengan pembungkusan kata otomatis (*word-wrap* `wrapLine()` & `centerText()`), *double-height header*, pemotong kertas (`GS V`), *paper feed* margin sobekan, serta *cash drawer kick* (`ESC p`).
* **Multi-OS Backend Spooler**:
  * **Windows**: PowerShell Raw Spooler (`winspool.Drv` via `scripts/raw_print.ps1`).
  * **macOS Ventura & Linux**: CUPS Raw Queue (`lp -d <printer> -o raw` & `lpr -o raw`), auto-registrasi printer USB via `lpinfo -v` / `lpadmin`, serta penulisan langsung ke port device (`/dev/cu.usb*`, `/dev/tty.usb*`, `/dev/usb/lp*`).
* **Universal Browser USB Printing (`thermalPrinter.js`)**:
  * **WebUSB API (`navigator.usb`)**: Koneksi langsung ke endpoint Bulk OUT USB Printer Class (`0x07`) dengan *64-byte chunk pacing* (mencegah *buffer overflow* pada printer thermal 58mm/80mm di macOS, Windows, Linux, ChromeOS, dan Android).
  * **Web Serial API (`navigator.serial`)**: Fallback otomatis untuk printer thermal berbasis chip USB-to-Serial (CH340/PL2303).

---

## 🚀 Panduan Instalasi & Menjalankan Proyek

### 1. Kebutuhan Sistem
* PHP `>= 8.2` (ekstensi: `pdo_sqlite` / `pdo_mysql`, `mbstring`, `bcmath`, `curl`)
* [Composer](https://getcomposer.org/) 2.x
* [Node.js](https://nodejs.org/) `>= 18.x` & npm

### 2. Instalasi Dependensi

```bash
# 1. Buka direktori proyek
cd realtime-pos-system

# 2. Salin environment file (jika belum)
copy .env.example .env

# 3. Install dependensi PHP (Composer)
composer install

# 4. Generate application key
php artisan key:generate

# 5. Jalankan migrasi database dan seeder data awal
php artisan migrate --seed

# 6. Install dependensi JavaScript (NPM)
npm install

# 7. Build aset frontend
npm run build
```

### 3. Menjalankan Aplikasi

#### Opsi A: Menjalankan Server & Vite Secara Bersamaan (Disarankan)
```bash
composer dev
```

#### Opsi B: Menjalankan di Terminal Terpisah
* **Terminal 1 (Laravel Server):**
  ```bash
  php artisan serve --port=8000
  ```
* **Terminal 2 (Vite HMR):**
  ```bash
  npm run dev
  ```

---

## 🌐 Navigasi Alamat URL Aplikasi

* **Portal Superadmin CMS (Login)**: [http://127.0.0.1:8000/cms/login](http://127.0.0.1:8000/cms/login)
* **Dashboard Superadmin CMS**: [http://127.0.0.1:8000/cms](http://127.0.0.1:8000/cms)
  * **Kelola Menu Langsung**: [http://127.0.0.1:8000/cms/products](http://127.0.0.1:8000/cms/products)
  * **Kelola Kategori Langsung**: [http://127.0.0.1:8000/cms/categories](http://127.0.0.1:8000/cms/categories)
* **Portal Kasir & Dapur (Login)**: [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login)
* **Kasir POS Qurais Kitchen**: [http://127.0.0.1:8000/pos](http://127.0.0.1:8000/pos)
* **Dapur KDS Realtime**: [http://127.0.0.1:8000/kds](http://127.0.0.1:8000/kds)
* **Riwayat Transaksi**: [http://127.0.0.1:8000/transactions](http://127.0.0.1:8000/transactions)
* **Analisis & Laporan**: [http://127.0.0.1:8000/analytics](http://127.0.0.1:8000/analytics)
* **Laravel Log Viewer**: [http://127.0.0.1:8000/log-viewer](http://127.0.0.1:8000/log-viewer) (atau `/logs`)

---

## 🧪 Pengujian Otomatis (Testing Suite)

Proyek ini dilengkapi dengan 64 test otomatis yang mencakup seluruh alur bisnis sistem:

```bash
php artisan test
```

### Hasil Test Suite Terbaru:
```text
   PASS  Tests\Unit\EscPosReceiptServiceTest
  ✓ format two columns aligns correctly
  ✓ center text within width
  ✓ generate receipt plain text
  ✓ generate receipt bytes contains esc pos commands
  ✓ generate kitchen ticket bytes
  ✓ generate kitchen ticket plain text
  ✓ generate kitchen item ticket bytes and plain text

   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\AnalyticsTest
  ✓ cashier can retrieve analytics summary kpis and charts
  ✓ analytics responds to different periods

   PASS  Tests\Feature\AuthenticationTest
  ✓ cashier can login with provided credentials
  ✓ kitchen chef can login with provided credentials
  ✓ login fails with invalid password
  ✓ cashier rejected when accessing kitchen portal with expected role
  ✓ authenticated user can retrieve profile and roles
  ✓ user can logout

   PASS  Tests\Feature\CmsSystemTest
  ✓ superadmin can login via cms login portal
  ✓ non superadmin rejected from cms login
  ✓ unauthenticated user redirected when accessing cms
  ✓ cashier cannot access cms routes
  ✓ superadmin can access cms dashboard
  ✓ superadmin can manage categories
  ✓ superadmin can manage products menu
  ✓ superadmin can retrieve deep cms analytics
  ✓ qurais kitchen brand and cashier name
  ✓ superadmin can access all features including pos and kds
  ✓ cashier and kitchen strictly forbidden from dashboard

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response

   PASS  Tests\Feature\FeatureLoggingTest
  ✓ feature logger writes to dedicated channel files
  ✓ auth actions are logged to auth channel
  ✓ order creation is logged to pos channel
  ✓ kds status update is logged to kds channel
  ✓ void order is logged to transaction channel
  ✓ cms operations are logged to cms channel
  ✓ log viewer discovers all feature log files

   PASS  Tests\Feature\KitchenDisplaySystemTest
  ✓ chef can retrieve active kitchen orders
  ✓ chef can advance order status to preparing and ready
  ✓ chef can toggle item checklist in kds
  ✓ kds sse stream returns event stream headers
  ✓ chef can get individual menu item kitchen ticket
  ✓ chef can print individual menu item ticket direct

   PASS  Tests\Feature\PosSystemTest
  ✓ unauthenticated user cannot access pos
  ✓ cashier can retrieve categories and products
  ✓ cashier can create order with realtime event and stock reduction
  ✓ cashier cannot checkout cash with insufficient payment
  ✓ cashier cannot order item when stock insufficient

   PASS  Tests\Feature\ReceiptPrintLatencyAndLogViewerTest
  ✓ order creation with auto print records latency and logs
  ✓ manual print records latency and returns in response
  ✓ client web serial print latency endpoint
  ✓ logs route redirects to opcodes log viewer
  ✓ opcodes log viewer dashboard accessible by cashier
  ✓ opcodes log viewer api files and logs endpoints
  ✓ receipt print latency is logged to file

   PASS  Tests\Feature\ThermalPrinterTest
  ✓ can get and update printer settings
  ✓ can generate test print payload
  ✓ can download binary escpos file
  ✓ can get installed printers
  ✓ can print order direct to windows printer

   PASS  Tests\Feature\TransactionManagementTest
  ✓ cashier can retrieve paginated transactions and summary
  ✓ cashier can view order detail with receipt
  ✓ cashier can void order and restore inventory stock
  ✓ cashier can export transactions as csv
  ✓ cashier can update item note
  ✓ cashier can update order notes

  Tests:    64 passed (340 assertions)
  Duration: 10.85s
```

---

## 📁 Struktur Direktori Utama

```text
realtime-pos-system/
├── app/
│   ├── Events/                     # Broadcast events (NewOrderCreated, OrderStatusUpdated)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CmsController.php        # CMS Dashboard, Menu CRUD, Kategori CRUD & Analitik
│   │   │   ├── CmsAuthController.php    # Dedicated Superadmin Login & Logout
│   │   │   ├── PosController.php        # Kasir POS & Pembuatan Order
│   │   │   ├── KdsController.php        # Antrean Dapur & Stream SSE
│   │   │   ├── ThermalPrinterController # ESC/POS & Hardware Windows Printer
│   │   │   ├── TransactionController    # Riwayat Transaksi & Void
│   │   │   └── AnalyticsController      # Ringkasan KPI Finansial Kasir
│   │   └── Middleware/                  # Authenticate & Spatie Permission middlewares
│   ├── Models/                     # Order, OrderItem, Product, Category, PrinterSetting, ReceiptPrintLog, User
│   └── Services/
│       ├── EscPosReceiptService.php # ESC/POS binary & plain text thermal printer builder
│       └── FeatureLog.php           # Multi-Channel Feature Logging Service (Dual-Write)
├── database/
│   ├── migrations/                 # Skema tabel orders, items, products, categories, permissions
│   └── seeders/DatabaseSeeder.php  # Superadmin (adnan@superadmin.com), Kasir, Chef, Menu, Kategori
├── resources/
│   ├── css/app.css                 # Custom thermal paper roll styling & Tailwind v4 config
│   ├── js/
│   │   ├── components/
│   │   │   ├── cms/CmsApp.jsx               # Superadmin CMS (Menu, Kategori & Analitik)
│   │   │   ├── pos/PosApp.jsx               # Antarmuka kasir POS Qurais Kitchen
│   │   │   ├── kds/KdsApp.jsx               # Antarmuka dapur KDS realtime
│   │   │   ├── transactions/TransactionsApp # Riwayat transaksi & void order
│   │   │   ├── analytics/AnalyticsApp.jsx   # Grafik laporan & analitik bisnis
│   │   │   ├── ThermalReceiptModal.jsx      # Modal Struk Transaksi thermal autentik
│   │   │   └── PrinterSettingsModal.jsx     # Konfigurasi hardware thermal printer
│   │   └── utils/thermalPrinter.js          # Web Serial API & utilitas printer
│   └── views/
│       ├── cms/
│       │   ├── login.blade.php              # Dedicated Superadmin Dashboard Login
│       │   └── index.blade.php              # Qurais Kitchen Dashboard container
│       ├── auth/login.blade.php             # Portal Login Kasir & Dapur
│       └── layouts/app.blade.php            # Master Layout Qurais Kitchen
├── routes/
│   └── web.php                     # Route web & API internal sistem
└── tests/
    ├── Feature/                    # Feature tests (Auth, Dashboard/CMS, POS, KDS, Printer, Transaksi, Analisis)
    └── Unit/                       # Unit tests (EscPosReceiptService formatting & commands)
```

---

## 📄 Lisensi

Aplikasi ini dikembangkan dan didistribusikan untuk keperluan manajemen kasir, dapur, dan restoran profesional Qurais Kitchen.
Lisensi kode dilindungi di bawah **[MIT License](LICENSE)**.
