<?php

namespace App\Jobs;

use App\Enums\OrderSyncStatus;
use App\Models\Order;
use App\Services\BigSeller\BigSellerOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Đẩy đơn hàng website lên BigSeller.
 *
 * Job này AN TOÀN khi retry: trước khi gửi, BigSellerOrderService kiểm tra
 * mapping đã có mã đơn hay chưa => không tạo đơn trùng.
 */
class PushWebsiteOrderToBigSellerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public array $backoff = [15, 30, 60, 120, 300];

    public function __construct(
        public int $orderId,
    ) {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('push-order-'.$this->orderId))->dontRelease()];
    }

    public function handle(BigSellerOrderService $orderService): void
    {
        $order = Order::with('items')->find($this->orderId);

        if ($order === null) {
            Log::warning('PushWebsiteOrderToBigSellerJob: không tìm thấy đơn hàng', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        // Đã đồng bộ rồi thì không gửi lại.
        if ($order->isSyncedToBigSeller()) {
            Log::info('Đơn đã đồng bộ, bỏ qua: '.$order->order_code);

            return;
        }

        if (! $order->needsPush()) {
            Log::info('Đơn không ở trạng thái cần gửi, bỏ qua: '.$order->order_code);

            return;
        }

        $result = $orderService->pushOrder($order);

        Log::info('Push đơn lên BigSeller', [
            'order_code' => $order->order_code,
            'result' => $result,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Push đơn lên BigSeller thất bại: '.$exception->getMessage(), [
            'order_id' => $this->orderId,
        ]);

        Order::whereKey($this->orderId)->update([
            'sync_status' => OrderSyncStatus::SyncFailed->value,
            'sync_error' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}
