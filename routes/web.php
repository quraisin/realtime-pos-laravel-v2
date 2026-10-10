<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CmsAuthController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\KdsController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ThermalPrinterController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root entry point
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->hasRole('superadmin') || $user->hasPermissionTo('cms.access')) {
            return redirect()->route('cms.index');
        }
        if ($user->hasRole('kitchen')) {
            return redirect()->route('kds.index');
        }
        return redirect()->route('pos.index');
    }
    return redirect()->route('login');
});

// POS & KDS Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login/cashier', [AuthController::class, 'showCashierLogin'])->name('login.cashier');
Route::get('/login/kds', [AuthController::class, 'showKdsLogin'])->name('login.kds');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/api/auth/me', [AuthController::class, 'me'])->name('auth.me');

// Dedicated Superadmin CMS Authentication Routes (Separate from POS & KDS)
Route::get('/cms/login', [CmsAuthController::class, 'showLogin'])->name('cms.login');
Route::post('/cms/login', [CmsAuthController::class, 'login'])->name('cms.login.submit');
Route::post('/cms/logout', [CmsAuthController::class, 'logout'])->name('cms.logout');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {

    // Superadmin CMS Protected Routes (Category, Menu & Transaction Analytics)
    Route::middleware(['permission:cms.access'])->group(function () {
        Route::get('/cms', [CmsController::class, 'index'])->name('cms.index');
        Route::get('/cms/products', [CmsController::class, 'products'])->name('cms.products');
        Route::get('/cms/categories', [CmsController::class, 'categories'])->name('cms.categories');

        // CMS APIs
        Route::prefix('api/cms')->group(function () {
            // Category Management APIs
            Route::get('/categories', [CmsController::class, 'getCategories'])->name('api.cms.categories');
            Route::post('/categories', [CmsController::class, 'storeCategory'])->name('api.cms.categories.store');
            Route::put('/categories/{id}', [CmsController::class, 'updateCategory'])->name('api.cms.categories.update');
            Route::delete('/categories/{id}', [CmsController::class, 'deleteCategory'])->name('api.cms.categories.delete');

            // Menu / Product Management APIs
            Route::get('/products', [CmsController::class, 'getProducts'])->name('api.cms.products');
            Route::post('/products', [CmsController::class, 'storeProduct'])->name('api.cms.products.store');
            Route::get('/products/{id}', [CmsController::class, 'showProduct'])->name('api.cms.products.show');
            Route::put('/products/{id}', [CmsController::class, 'updateProduct'])->name('api.cms.products.update');
            Route::patch('/products/{id}/toggle-status', [CmsController::class, 'toggleProductStatus'])->name('api.cms.products.toggle_status');
            Route::patch('/products/{id}/stock', [CmsController::class, 'updateProductStock'])->name('api.cms.products.stock');
            Route::delete('/products/{id}', [CmsController::class, 'deleteProduct'])->name('api.cms.products.delete');

            // Deep Transaction Analytics for CMS Dashboard
            Route::get('/analytics', [CmsController::class, 'getAnalytics'])->name('api.cms.analytics');
        });
    });

    // Common Printer & Receipt APIs (accessible by authenticated cashier, kitchen and superadmin)
    Route::prefix('api')->group(function () {
        Route::get('/orders/{order}/receipt', [ThermalPrinterController::class, 'getReceipt'])->name('api.orders.receipt');
        Route::get('/orders/{order}/kitchen-ticket', [ThermalPrinterController::class, 'getKitchenTicket'])->name('api.orders.kitchen_ticket');
        Route::get('/items/{item}/kitchen-ticket', [ThermalPrinterController::class, 'getKitchenItemTicket'])->name('api.items.kitchen_ticket');
        Route::get('/orders/{order}/escpos-download', [ThermalPrinterController::class, 'downloadEscPos'])->name('api.orders.escpos_download');
        Route::get('/printer/settings', [ThermalPrinterController::class, 'getSettings'])->name('api.printer.settings');
        Route::post('/printer/settings', [ThermalPrinterController::class, 'updateSettings'])->name('api.printer.settings.update');
        Route::get('/printer/installed-printers', [ThermalPrinterController::class, 'getInstalledPrinters'])->name('api.printer.installed');
        Route::post('/printer/print-order/{order}', [ThermalPrinterController::class, 'printOrderDirect'])->name('api.printer.print_order');
        Route::post('/printer/print-item/{item}', [ThermalPrinterController::class, 'printItemDirect'])->name('api.printer.print_item');
        Route::post('/printer/record-print-latency/{order}', [ThermalPrinterController::class, 'recordClientPrintLatency'])->name('api.printer.record_latency');
        Route::post('/printer/test', [ThermalPrinterController::class, 'testPrint'])->name('api.printer.test');
    });

    // Cashier, Transactions, Analytics & Log Viewer Protected Routes (Cashier Role / Permissions)
    Route::middleware(['permission:pos.access'])->group(function () {
        // POS Cashier Interface
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/api/pos/categories', [PosController::class, 'getCategories'])->name('api.pos.categories');
        Route::get('/api/pos/products', [PosController::class, 'getProducts'])->name('api.pos.products');
        Route::post('/api/pos/orders', [PosController::class, 'createOrder'])->name('api.pos.orders.create');

        // Transactions Dashboard
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/api/transactions', [TransactionController::class, 'getTransactions'])->name('api.transactions.list');
        Route::get('/api/transactions/{id}', [TransactionController::class, 'show'])->name('api.transactions.show');
        Route::post('/api/transactions/items/{itemId}/note', [TransactionController::class, 'updateItemNote'])->name('api.transactions.items.note');
        Route::post('/api/transactions/{id}/notes', [TransactionController::class, 'updateOrderNote'])->name('api.transactions.notes');
        Route::post('/api/transactions/{id}/void', [TransactionController::class, 'voidOrder'])
            ->middleware('permission:transactions.void')
            ->name('api.transactions.void');
        Route::get('/api/transactions-export', [TransactionController::class, 'export'])->name('api.transactions.export');

        // Analytics Dashboard
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/api/analytics/summary', [AnalyticsController::class, 'getSummary'])->name('api.analytics.summary');

        // Laravel Log Viewer (Opcodes Log Viewer package)
        Route::get('/logs', fn() => redirect()->route('log-viewer.index'))->name('logs.index');
    });

    // Kitchen Display System (KDS) Protected Routes (Kitchen Role / Permissions)
    Route::middleware(['permission:kds.access'])->group(function () {
        Route::get('/kds', [KdsController::class, 'index'])->name('kds.index');
        Route::get('/api/kds/orders', [KdsController::class, 'getOrders'])->name('api.kds.orders');
        Route::post('/api/kds/orders/{id}/status', [KdsController::class, 'updateStatus'])->name('api.kds.orders.status');
        Route::post('/api/kds/items/{itemId}/toggle', [KdsController::class, 'toggleItemPrepared'])->name('api.kds.items.toggle');
        Route::get('/api/kds/stream', [KdsController::class, 'stream'])->name('api.kds.stream');
    });
});
