<?php

namespace App\Jobs;

use App\Enums\OrderSyncStatus;
use App\Models\Order;
use App\Services\BigSeller\BigSellerOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Thử đồng bộ lại các đơn hàng đang lỗi.
 *
 * AN TOÀN: retry dùng lại idempotency_key cũ, và service sẽ bỏ qua đơn đã
 * có mã BigSeller => không tạo đơn trùng.
 */
class RetryBigSellerSyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];

    /**
     * Số lần retry tối đa cho mỗi đơn (tránh retry vô hạn).
     */
    public int $maxAttempts = 5;

    public function __construct(
        public ?int $orderId = null,
    ) {
    }

    public function handle(BigSellerOrderService $orderService): void
    {
        $query = Order::query()
            ->whereIn('sync_status', [
                OrderSyncStatus::PendingSync->value,
                OrderSyncStatus::SyncFailed->value,
            ])
            ->where('sync_attempts', '<', $this->maxAttempts);

        if ($this->orderId !== null) {
            $query->whereKey($this->orderId);
        }

        $orders = $query->orderBy('id')->limit(50)->get();

        if ($orders->isEmpty()) {
            Log::info('RetryBigSellerSyncJob: không có đơn nào cần retry');

            return;
        }

        foreach ($orders as $order) {
            try {
                $result = $orderService->pushOrder($order);

                Log::info('Retry đơn BigSeller', [
                    'order_code' => $order->order_code,
                    'result' => $result,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Retry đơn BigSeller lỗi: '.$e->getMessage(), [
                    'order_code' => $order->order_code,
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RetryBigSellerSyncJob thất bại: '.$exception->getMessage());
    }
}
