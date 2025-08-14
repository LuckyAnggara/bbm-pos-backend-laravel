<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\CustomerPaymentController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\FinancialReportController;
use App\Http\Controllers\Api\StockMutationReportController;
use App\Http\Controllers\Api\StockMovementReportController;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [UserController::class, 'logout']);
    // ... (resource controller lainnya)

    // TAMBAHKAN BARIS INI
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('bank-accounts', BankAccountController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);

    // --- Rute untuk Transaksi POS ---
    Route::post('/pos/transactions', [PosController::class, 'store']);

    // --- Rute untuk Riwayat Penjualan ---
    Route::apiResource('sales', SaleController::class)->only(['index', 'show']);
    Route::apiResource('customer-payments', CustomerPaymentController::class)->only(['index', 'store', 'update', 'destroy']);

    // --- Rute Khusus untuk Shift ---
    Route::prefix('shifts')->group(function () {
        Route::get('/', [ShiftController::class, 'index']); // Riwayat shift
        Route::get('/active', [ShiftController::class, 'getActiveShift']); // Cek shift aktif
        Route::post('/start', [ShiftController::class, 'startShift']); // Mulai shift
        Route::post('/end', [ShiftController::class, 'endShift']); // Akhiri shift
    });

    Route::post('/sales/{sale}/request-action', [SaleController::class, 'requestAction']);
    Route::post('/sales/{sale}/approve-action', [SaleController::class, 'approveAction']);
    Route::post('/sales/{sale}/reject-action', [SaleController::class, 'rejectAction']);
    Route::get('/sales-list-request', [SaleController::class, 'listRequest']);

    Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receiveOrder']);
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::apiResource('supplier-payments', SupplierPaymentController::class);
    // Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receiveOrder']);
    Route::put('/purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'updateStatus']); // <-- TAMBAHKAN INI
    Route::apiResource('purchase-orders', PurchaseOrderController::class);

    Route::apiResource('expenses', ExpenseController::class);

    // Reports generate & fetch
    Route::post('/reports/generate', [FinancialReportController::class, 'generate']);
    Route::get('/reports', [FinancialReportController::class, 'index']);

    // Stock Mutation Reports (generate via internal flow, fetch, and live compute)
    Route::post('/stock-mutation-reports/generate', [StockMutationReportController::class, 'generate']);
    Route::get('/stock-mutation-reports', [StockMutationReportController::class, 'index']);
    Route::get('/stock-mutation-reports/live', [StockMutationReportController::class, 'live']);

    // Stock Movement Reports per-product
    Route::post('/stock-movement-reports/generate', [StockMovementReportController::class, 'generate']);
    Route::get('/stock-movement-reports', [StockMovementReportController::class, 'index']);
    Route::get('/stock-movement-reports/live', [StockMovementReportController::class, 'live']);

    // Dashboard summary
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    // Inventory year snapshots
    Route::get('/inventory/year-status', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'yearStatus']);
    Route::get('/inventory/branch-status', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'branchStatus']);
    Route::post('/inventory/close-year', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'closeYear']);
    Route::post('/inventory/close-year-branch', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'closeYearBranch']);
    Route::post('/inventory/open-year', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'openYear']);
    Route::post('/inventory/open-year-branch', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'openYearBranch']);
    Route::get('/inventory/closing-detail/{year}', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'closingDetail']);
    Route::get('/inventory/closing-export/{year}', [\App\Http\Controllers\Api\InventorySnapshotController::class, 'exportClosingCsv']);
    // Notifications
    Route::post('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'store']);
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/dismiss', [\App\Http\Controllers\Api\NotificationController::class, 'dismiss']);
});
