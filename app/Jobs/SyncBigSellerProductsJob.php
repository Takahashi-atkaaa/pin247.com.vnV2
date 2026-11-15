<?php

namespace App\Jobs;

use App\Enums\OrderSyncStatus;
use App\Models\Order;
use App\Services\BigSeller\BigSellerOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Đồng bộ danh sách sản phẩm/SKU từ BigSeller.
 *
 * CHỈ có tác dụng khi tài khoản được cấp quyền API sản phẩm.
 */
class SyncBigSellerProductsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public array $params = [],
    ) {
    }

    public function handle(\App\Services\BigSeller\BigSellerProductService $productService): void
    {
        $result = $productService->syncProducts($this->params);

        Log::info('BigSeller products sync', $result);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BigSeller products sync thất bại: '.$exception->getMessage());
    }
}
