<?php

use App\Http\Controllers\Accounts\AccountsController;
use App\Http\Controllers\Customers\CustomersController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Sales\SalesController;
use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\Parties\PartyController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Returns\ReturnController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // ── POS ──────────────────────────────────────────────────
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('cashier');
        Route::post('/sales', [PosController::class, 'store'])->name('sales.store');
        Route::get('/products/search', [PosController::class, 'searchProducts'])->name('products.search');
        Route::post('/check-stock', [PosController::class, 'checkStock'])->name('check-stock');
        Route::get('/customers/search', [PosController::class, 'searchCustomers'])->name('customers.search');
        Route::post('/customers', [PosController::class, 'storeCustomer'])->name('customers.store');
        Route::get('/stats', [PosController::class, 'stats'])->name('stats');
    });

    // ── Customers ────────────────────────────────────────────
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/',                   [CustomersController::class, 'index'])->name('index');
        Route::get('/create',             [CustomersController::class, 'create'])->name('create');
        Route::post('/',                  [CustomersController::class, 'store'])->name('store');
        Route::get('/{customer}',         [CustomersController::class, 'show'])->name('show');
        Route::get('/{customer}/edit',    [CustomersController::class, 'edit'])->name('edit');
        Route::put('/{customer}',         [CustomersController::class, 'update'])->name('update');
        Route::delete('/{customer}',      [CustomersController::class, 'destroy'])->name('destroy');
        Route::post('/{customer}/payment',         [CustomersController::class, 'recordPayment'])->name('payment');
        Route::post('/{customer}/enable-supplier',  [CustomersController::class, 'enableSupplier'])->name('enable-supplier');
        Route::post('/{customer}/disable-supplier', [CustomersController::class, 'disableSupplier'])->name('disable-supplier');
    });

    // ── Parties ───────────────────────────────────────────────
    Route::prefix('parties')->name('parties.')->group(function () {
        Route::get('/{party}', [PartyController::class, 'show'])->name('show');
    });

    // ── Sales ────────────────────────────────────────────────
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SalesController::class, 'index'])->name('index');
        Route::get('/{sale}', [SalesController::class, 'show'])->name('show');
        Route::get('/{sale}/receipt', [SalesController::class, 'receiptData'])->name('receipt');
        Route::post('/{sale}/void', [SalesController::class, 'void'])->name('void');
        Route::post('/{sale}/returns', [ReturnController::class, 'store'])->name('returns.store');
    });

    // ── Returns ───────────────────────────────────────────────
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/',          [ReturnController::class, 'index'])->name('index');
        Route::get('/{return}',  [ReturnController::class, 'show'])->name('show');
    });

    // ── Inventory ────────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->group(function () {

        Route::resource('products', ProductController::class);
        Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
            ->name('products.toggle-status');

        Route::get('stock',        [StockController::class, 'index'])->name('stock.index');
        Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    });

    // ── Accounts ─────────────────────────────────────────────
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/',                        [AccountsController::class, 'index'])->name('index');
        Route::post('/accounts',               [AccountsController::class, 'storeAccount'])->name('store-account');
        Route::patch('/accounts/{account}',    [AccountsController::class, 'updateAccount'])->name('update-account');
        Route::post('/journal',                [AccountsController::class, 'storeEntry'])->name('store-entry');
        Route::delete('/journal/{entry}',      [AccountsController::class, 'destroyEntry'])->name('delete-entry');
        Route::get('/reports',                 [AccountsController::class, 'reports'])->name('reports');
        Route::get('/ledger',                  [AccountsController::class, 'ledger'])->name('ledger');
        Route::get('/receivables',             [AccountsController::class, 'receivables'])->name('receivables');
    });

    // ── Purchasing ─────────────────────────────────────────
    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        Route::get('suppliers',               [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers',              [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}',    [SupplierController::class, 'show'])->name('suppliers.show');
        Route::patch('suppliers/{supplier}',  [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('orders',             [PurchaseOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create',      [PurchaseOrderController::class, 'create'])->name('orders.create');
        Route::post('orders',            [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}',     [PurchaseOrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('orders.receive');
        Route::post('orders/{order}/pay',     [PurchaseOrderController::class, 'pay'])->name('orders.pay');
        Route::post('orders/{order}/cancel',  [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
    });

    // ── Expenses ──────────────────────────────────────────
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/',             [ExpenseController::class, 'index'])->name('index');
        Route::post('/',            [ExpenseController::class, 'store'])->name('store');
        Route::patch('/{expense}',  [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    });

    // ── Business Settings ──────────────────────────────────
    Route::prefix('business-settings')->name('business-settings.')->group(function () {
        Route::get('/',        [BusinessSettingsController::class, 'index'])->name('index');
        Route::post('/',       [BusinessSettingsController::class, 'update'])->name('update');
        Route::post('/logo',   [BusinessSettingsController::class, 'uploadLogo'])->name('upload-logo');
    });

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
