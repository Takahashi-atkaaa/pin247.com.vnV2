<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderSyncStatus;
use App\Enums\ProductSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardController extends Controller
{
    /**
     * Các chỉ số tổng quan.
     *
     * LƯU Ý: Doanh thu website và doanh thu đa sàn KHÔNG được gộp làm một
     * chỉ số khi chưa có nguồn dữ liệu và định nghĩa thống nhất.
     */
    public function index(): JsonResponse
    {
        $totalOrders = Order::count();
        $newOrders = Order::whereDate('created_at', today())->count();
        $pendingSync = Order::where('sync_status', OrderSyncStatus::PendingSync->value)->count();
        $syncFailed = Order::where('sync_status', OrderSyncStatus::SyncFailed->value)->count();
        $synced = Order::where('sync_status', OrderSyncStatus::Synced->value)->count();

        $totalProducts = Product::count();
        $totalSkus = Sku::count();
        $totalVariants = \App\Models\ProductVariant::count();

        // Sản phẩm sắp hết hàng
        $lowStockThreshold = (int) \App\Models\Setting::get('inventory.low_stock_threshold', 5);
        $lowStockCount = Sku::where('available_stock', '<=', $lowStockThreshold)->count();
        $outOfStockCount = Sku::where('available_stock', '<=', 0)->count();

        // Giá trị tồn kho: CHỈ tính được khi có giá vốn hợp lệ.
        $inventoryValue = Sku::query()
            ->whereNotNull('cost_price')
            ->selectRaw('SUM(available_stock * cost_price) as total')
            ->value('total');

        $hasCostPrice = Sku::whereNotNull('cost_price')->count() > 0;

        // Doanh thu WEBSITE (chỉ đơn của website này)
        $websiteRevenue = (float) Order::whereNotIn('sync_status', [
            OrderSyncStatus::Cancelled->value,
        ])->sum('total_amount');

        $websiteRevenueMonth = (float) Order::where('created_at', '>=', now()->startOfMonth())
            ->whereNotIn('sync_status', [OrderSyncStatus::Cancelled->value])
            ->sum('total_amount');

        // Phân bố trạng thái đồng bộ
        $syncBreakdown = Order::query()
            ->selectRaw('sync_status, COUNT(*) as total')
            ->groupBy('sync_status')
            ->pluck('total', 'sync_status');

        // Doanh thu đa sàn: KHÔNG có nguồn dữ liệu từ BigSeller trong phạm vi
        // API hiện tại => trả về null (không tính giả).
        $multiChannelRevenue = null;

        return response()->json([
            'data' => [
                'orders' => [
                    'total' => $totalOrders,
                    'new_today' => $newOrders,
                    'pending_sync' => $pendingSync,
                    'sync_failed' => $syncFailed,
                    'synced' => $synced,
                ],
                'catalog' => [
                    'total_products' => $totalProducts,
                    'total_variants' => $totalVariants,
                    'total_skus' => $totalSkus,
                    'by_source' => Product::query()
                        ->selectRaw('source, COUNT(*) as total')
                        ->groupBy('source')
                        ->pluck('total', 'source'),
                ],
                'inventory' => [
                    'low_stock_threshold' => $lowStockThreshold,
                    'low_stock_count' => $lowStockCount,
                    'out_of_stock_count' => $outOfStockCount,
                    'total_value' => $hasCostPrice ? (float) $inventoryValue : null,
                    'has_cost_price' => $hasCostPrice,
                ],
                'revenue' => [
                    'website_total' => $websiteRevenue,
                    'website_this_month' => $websiteRevenueMonth,
                    'multi_channel' => $multiChannelRevenue,
                    'multi_channel_available' => false,
                    'note' => 'Doanh thu đa sàn (Shopee/Lazada/TikTok Shop) chưa có nguồn dữ liệu '
                        .'từ API BigSeller trong phạm vi quyền hiện có nên không được tính gộp.',
                ],
                'sync_breakdown' => $syncBreakdown,
                'customers' => [
                    'total' => Customer::count(),
                    'new_this_month' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
                ],
            ],
        ]);
    }
}
