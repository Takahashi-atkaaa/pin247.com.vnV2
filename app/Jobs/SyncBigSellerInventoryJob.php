<?php

namespace App\Jobs;

use App\Services\InventoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Đồng bộ tồn kho từ BigSeller.
 */
class SyncBigSellerInventoryJob implements ShouldQueue
{
    use Queueable;

    /**
     * Số lần thử tối đa.
     */
    public int $tries = 3;

    /**
     * Timeout (giây).
     */
    public int $timeout = 120;

    /**
     * Backoff giữa các lần thử (giây).
     */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public ?string $syncRunId = null,
    ) {
    }

    /**
     * Không cho chạy chồng nhau (tránh ghi đè snapshot).
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('bigseller-inventory-sync'))->dontRelease()];
    }

    public function handle(InventoryService $inventoryService): void
    {
        $result = $inventoryService->syncFromBigSeller($this->syncRunId);

        Log::info('BigSeller inventory sync', $result);

        if ($result['error'] !== null) {
            // Ném lỗi để Laravel retry theo backoff.
            throw new \RuntimeException($result['error']);
        }
    }

    /**
     * Khi job thất bại hoàn toàn.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BigSeller inventory sync thất bại: '.$exception->getMessage(), [
            'sync_run_id' => $this->syncRunId,
        ]);
    }
}
