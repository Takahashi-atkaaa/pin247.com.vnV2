<?php

use App\Http\Controllers\Admin\BigSellerController as AdminBigSellerController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — PIN247
|--------------------------------------------------------------------------
*/

// ---------------------------------------------------------------------------
// Xác thực
// ---------------------------------------------------------------------------
Route::post('/auth/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

// ---------------------------------------------------------------------------
// Storefront (public)
// ---------------------------------------------------------------------------
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/stock', [ProductController::class, 'stock']);

Route::post('/cart/validate', [CartController::class, 'validate'])->middleware('throttle:30,1');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');
Route::post('/orders/track', [CheckoutController::class, 'track'])->middleware('throttle:20,1');

// ---------------------------------------------------------------------------
// Người dùng đã đăng nhập
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/me', fn (\Illuminate\Http\Request $request) => response()->json([
        'data' => [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'is_admin' => $request->user()->isAdmin(),
        ],
    ]));

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
});

// ---------------------------------------------------------------------------
// Admin
// ---------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function (): void {
    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // Sản phẩm
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{product}', [AdminProductController::class, 'show']);
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::post('/products/{product}/images', [AdminProductController::class, 'uploadImage']);
    Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'destroyImage']);

    // Danh mục
    Route::get('/categories', [AdminProductController::class, 'categories']);
    Route::post('/categories', [AdminProductController::class, 'storeCategory']);
    Route::put('/categories/{category}', [AdminProductController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [AdminProductController::class, 'destroyCategory']);

    // Tồn kho
    Route::get('/inventory', [AdminInventoryController::class, 'index']);
    Route::post('/inventory/sync', [AdminInventoryController::class, 'sync']);
    Route::post('/inventory/sync/dispatch', [AdminInventoryController::class, 'dispatchSync']);
    Route::get('/inventory/history/{sku}', [AdminInventoryController::class, 'history']);
    Route::get('/inventory/warehouses', [AdminInventoryController::class, 'warehouses']);

    // Đơn hàng
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::post('/orders/{order}/resync', [AdminOrderController::class, 'resync']);
    Route::post('/orders/{order}/push', [AdminOrderController::class, 'pushToBigSeller']);
    Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
    Route::post('/orders/{order}/refresh-status', [AdminOrderController::class, 'refreshStatus']);
    Route::put('/orders/{order}/note', [AdminOrderController::class, 'updateNote']);

    // Khách hàng
    Route::get('/customers', [AdminCustomerController::class, 'index']);
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show']);

    // BigSeller
    Route::get('/bigseller/status', [AdminBigSellerController::class, 'status']);
    Route::post('/bigseller/check-connection', [AdminBigSellerController::class, 'checkConnection']);
    Route::post('/bigseller/sync/inventory', [AdminBigSellerController::class, 'syncInventory']);
    Route::post('/bigseller/sync/inventory/dispatch', [AdminBigSellerController::class, 'dispatchInventorySync']);
    Route::post('/bigseller/sync/products', [AdminBigSellerController::class, 'syncProducts']);
    Route::post('/bigseller/sync/products/dispatch', [AdminBigSellerController::class, 'dispatchProductSync']);
    Route::post('/bigseller/sync/orders', [AdminBigSellerController::class, 'syncOrders']);
    Route::get('/bigseller/reconcile-skus', [AdminBigSellerController::class, 'reconcileSkus']);
    Route::get('/bigseller/logs', [AdminBigSellerController::class, 'logs']);
    Route::get('/bigseller/sku-mappings', [AdminBigSellerController::class, 'skuMappings']);
    Route::post('/bigseller/sku-mappings', [AdminBigSellerController::class, 'storeSkuMapping']);
    Route::get('/bigseller/schedule', [AdminBigSellerController::class, 'schedule']);
    Route::put('/bigseller/schedule', [AdminBigSellerController::class, 'updateSchedule']);
});
