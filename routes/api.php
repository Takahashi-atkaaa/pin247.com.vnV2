<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Admin\BigSellerController as AdminBigSellerController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PriceListController as AdminPriceListController;
use App\Http\Controllers\Admin\ProductBulkController as AdminProductBulkController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupportController;
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

// Tra cứu đơn hàng bằng mã đơn + SĐT — PUBLIC (khách không có tài khoản
// vẫn cần tra cứu đơn COD).
Route::post('/orders/track', [CheckoutController::class, 'track'])->middleware('throttle:20,1');

Route::post('/support/requests', [SupportController::class, 'store'])->middleware('throttle:5,5');
Route::get('/support/messages/{token}', [SupportController::class, 'messages'])->middleware('throttle:60,1');
Route::post('/support/messages/{token}', [SupportController::class, 'reply'])->middleware('throttle:30,1');

// ---------------------------------------------------------------------------
// Bảng giá — PUBLIC, khách xem không cần đăng nhập
// ---------------------------------------------------------------------------
Route::get('/price-list', [PriceListController::class, 'index']);
Route::get('/price-list/documents', [PriceListController::class, 'documents']);
Route::get('/price-list/documents/{document}/download', [PriceListController::class, 'download']);

// ---------------------------------------------------------------------------
// Người dùng đã đăng nhập
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Đặt hàng — BẮT BUỘC đăng nhập.
    // Guest KHÔNG được tạo đơn hàng. `user_id` của đơn luôn lấy từ
    // tài khoản đã xác thực (không tin tưởng payload của frontend).
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');

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

    // Trang thông tin người dùng (Tài khoản của tôi)
    Route::get('/account/profile', [AccountController::class, 'profile']);
    Route::put('/account/profile', [AccountController::class, 'updateProfile']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::get('/account/orders', [AccountController::class, 'orders']);
    Route::get('/account/summary', [AccountController::class, 'summary']);
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

    // GĐ3 — Nhân bản sản phẩm (clone cơ chế Haravan).
    Route::post('/products/{product}/duplicate', [AdminProductController::class, 'duplicate']);

    // GĐ1/GĐ2/GĐ3 — Màn "Chi tiết biến thể".
    Route::get('/products/{product}/variants/{variant}', [AdminProductController::class, 'showVariant']);
    Route::put('/products/{product}/variants/{variant}', [AdminProductController::class, 'updateVariant']);
    Route::put('/products/{product}/variants/{variant}/units', [AdminProductController::class, 'updateVariantUnits']);
    Route::get('/products/{product}/variants/{variant}/history', [AdminProductController::class, 'variantHistory']);

    // Thao tác hàng loạt (clone cơ chế Haravan).
    Route::post('/products-bulk', [AdminProductBulkController::class, 'update']);
    Route::post('/variants-bulk', [AdminProductBulkController::class, 'updateVariants']);

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

    // Bảng giá
    Route::get('/price-list', [AdminPriceListController::class, 'index']);
    Route::get('/price-list/tiers/{tier}', [AdminPriceListController::class, 'showTier']);
    Route::post('/price-list/tiers', [AdminPriceListController::class, 'storeTier']);
    Route::put('/price-list/tiers/{tier}', [AdminPriceListController::class, 'updateTier']);
    Route::delete('/price-list/tiers/{tier}', [AdminPriceListController::class, 'destroyTier']);
    Route::post('/price-list/import', [AdminPriceListController::class, 'import']);
    Route::get('/price-list/import-template', [AdminPriceListController::class, 'importTemplate']);
    Route::get('/price-list/export', [AdminPriceListController::class, 'export']);
    Route::get('/price-list/documents', [AdminPriceListController::class, 'documents']);
    Route::post('/price-list/documents', [AdminPriceListController::class, 'uploadDocument']);
    Route::put('/price-list/documents/{document}', [AdminPriceListController::class, 'updateDocument']);
    Route::delete('/price-list/documents/{document}', [AdminPriceListController::class, 'destroyDocument']);
    Route::get('/price-list/documents/{document}/download', [AdminPriceListController::class, 'downloadDocument']);

    // Chat hỗ trợ — admin xem yêu cầu, tìm user, đặt lại mật khẩu.
    Route::get('/support/lookup', [AdminSupportController::class, 'lookup']);
    Route::get('/support/requests', [AdminSupportController::class, 'index']);
    Route::get('/support/requests/{supportRequest}', [AdminSupportController::class, 'show']);
    Route::post('/support/requests/{supportRequest}/reply', [AdminSupportController::class, 'reply']);
    Route::post('/support/requests/{supportRequest}/reset-password', [AdminSupportController::class, 'resetPassword']);
    Route::post('/support/requests/{supportRequest}/resolve', [AdminSupportController::class, 'resolve']);

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
