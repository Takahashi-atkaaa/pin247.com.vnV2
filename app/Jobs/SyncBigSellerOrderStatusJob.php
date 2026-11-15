<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\BigSeller\BigSellerOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Đồng bộ trạng thái đơn hàng từ BigSeller về website.
 */
class SyncBigSellerOrderStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function handle(BigSellerOrderService $orderService): void
    {
        $orders = Order::query()
            ->whereNotNull('bigseller_order_code')
            ->whereIn('sync_status', ['SYNCED', 'PROCESSING', 'SHIPPED', 'CANCEL_PENDING'])
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            $result = $orderService->refreshStatus($order);

            if (! $result['success']) {
                Log::info('Không cập nhật được trạng thái đơn: '.$result['message'], [
                    'order_code' => $order->order_code,
                ]);
            }
        }
    }
}
