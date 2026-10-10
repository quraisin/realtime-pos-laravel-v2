# Dokumentasi Teknis Sistem Qurais Kitchen POS Realtime, Dashboard & Kitchen Display System (KDS)

> **Terakhir Diperbarui Otomatis:** 09 October 2026, 08:50:20 UTC  
> **Versi Sistem:** 1.1.0 (Dashboard & Multi-Portal Production Ready)  
> **Status Unit Test:** 54 Passed (280 Assertions) across 12 test files  

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

Aplikasi dilengkapi dengan 32 unit & feature test yang mencakup seluruh fungsi:
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

## 8. Mekanisme Pembaruan Dokumentasi Otomatis

Sistem ini memiliki perintah otomatis untuk memperbarui dokumentasi teknis setiap kali terjadi modifikasi pada skema database, rute, peran, atau kode sistem:
```bash
php artisan docs:update
```
Perintah ini membaca secara reflektif seluruh rute aktif, struktur tabel SQLite, permission Spatie, dan data test untuk menyusun ulang file ini secara konsisten.
