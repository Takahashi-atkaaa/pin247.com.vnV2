<?php

namespace App\Services;

use App\Enums\SyncState;
use App\Models\InventoryLog;
use App\Models\InventorySnapshot;
use App\Models\Sku;
use App\Services\BigSeller\BigSellerInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Quản lý tồn kho trên website.
 *
 * NGUYÊN TẮC:
 * - BigSeller là nguồn tồn kho chính. Website KHÔNG tự ghi đè tồn kho BigSeller.
 * - Số lượng bán được = available (từ BigSeller) - safety_stock.
 */
class InventoryService
{
    public function __construct(
        protected BigSellerInventoryService $bigSeller,
    ) {
    }

    /**
     * Đồng bộ tồn kho từ BigSeller.
     *
     * @return array{
     *     run_id: string, updated: int, unchanged: int, missing_skus: array<int, string>,
     *     error: ?string, message: string
     * }
     */
    public function syncFromBigSeller(?string $syncRunId = null): array
    {
        $syncRunId ??= (string) Str::uuid();
        $warehouse = $this->bigSeller->defaultWarehouse();

        $skus = Sku::query()->get([
            'id',
            'sku',
            'available_stock',
            'reserved_stock',
            'on_hand_stock',
            'locked_stock',
            'shipped_stock',
            'safety_stock',
        ]);

        if ($skus->isEmpty()) {
            return [
                'run_id' => $syncRunId,
                'updated' => 0,
                'unchanged' => 0,
                'missing_skus' => [],
                'error' => null,
                'message' => 'Chưa có SKU nào trong hệ thống để đồng bộ.',
            ];
        }

        try {
            $response = $this->bigSeller->fetchInventory(
                $skus->pluck('sku')->all(),
                $warehouse,
                $syncRunId,
            );
        } catch (\Throwable $e) {
            Log::error('Đồng bộ tồn kho BigSeller thất bại: '.$e->getMessage(), [
                'run_id' => $syncRunId,
            ]);

            return [
                'run_id' => $syncRunId,
                'updated' => 0,
                'unchanged' => 0,
                'missing_skus' => [],
                'error' => $e->getMessage(),
                'message' => 'Không lấy được dữ liệu tồn kho từ BigSeller: '.$e->getMessage(),
            ];
        }

        $items = collect($response['items'] ?? []);
        $itemsBySku = $items->keyBy(fn ($i) => $i->sku);

        $updated = 0;
        $unchanged = 0;
        $missingSkus = [];
        $now = now();

        foreach ($skus as $sku) {
            $item = $itemsBySku->get($sku->sku);

            if ($item === null) {
                // SKU không tồn tại trên BigSeller => cảnh báo, KHÔNG tự đặt tồn kho = 0
                // một cách âm thầm. Giữ nguyên tồn kho hiện tại và ghi cảnh báo.
                $missingSkus[] = $sku->sku;

                $sku->forceFill([
                    'sync_state' => SyncState::Failed,
                    'sync_error' => 'SKU không tồn tại trên BigSeller.',
                ])->save();

                continue;
            }

            $stockChanged = (int) $sku->available_stock !== $item->available;

            if (! $stockChanged) {
                $unchanged++;

                $sku->forceFill([
                    'sync_state' => SyncState::Synced,
                    'last_synced_at' => $now,
                    'sync_error' => null,
                ])->save();

                continue;
            }

            $previous = (int) $sku->available_stock;

            DB::transaction(function () use ($sku, $item, $previous, $now, $syncRunId, $warehouse): void {
                $sku->forceFill([
                    'available_stock' => $item->available,
                    'reserved_stock' => $item->reserved ?? $sku->reserved_stock,
                    'on_hand_stock' => $item->onHand ?? $sku->on_hand_stock,
                    'locked_stock' => $item->locked ?? $sku->locked_stock,
                    'shipped_stock' => $item->shipped ?? $sku->shipped_stock,
                    'sync_state' => SyncState::Synced,
                    'last_synced_at' => $now,
                    'sync_error' => null,
                ])->save();

                InventorySnapshot::create([
                    'sku_id' => $sku->id,
                    'warehouse_id' => $warehouse?->id,
                    'sku' => $sku->sku,
                    'available' => $item->available,
                    'reserved' => $item->reserved,
                    'on_hand' => $item->onHand,
                    'locked' => $item->locked,
                    'shipped' => $item->shipped,
                    'sync_run_id' => $syncRunId,
                    'captured_at' => $now,
                ]);

                InventoryLog::create([
                    'sku_id' => $sku->id,
                    'warehouse_id' => $warehouse?->id,
                    'sku' => $sku->sku,
                    'previous_available' => $previous,
                    'new_available' => $item->available,
                    'delta' => $item->available - $previous,
                    'reason' => 'sync_bigseller',
                    'sync_run_id' => $syncRunId,
                ]);
            });

            $updated++;
        }

        $message = "Đã cập nhật {$updated} SKU, không đổi {$unchanged} SKU.";

        if ($missingSkus !== []) {
            $message .= ' Cảnh báo: '.count($missingSkus).' SKU không tồn tại trên BigSeller.';
        }

        return [
            'run_id' => $syncRunId,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'missing_skus' => $missingSkus,
            'error' => null,
            'message' => $message,
        ];
    }

    /**
     * Kiểm tra tồn kho khả dụng cho một danh sách SKU.
     *
     * Dùng lúc thêm vào giỏ và lúc thanh toán (kiểm tra gần thời điểm đặt hàng).
     *
     * @param  array<string, int>  $quantities  sku => số lượng yêu cầu
     * @return array{valid: bool, errors: array<int, string>, available: array<string, int|null>}
     */
    public function checkAvailability(array $quantities): array
    {
        $errors = [];
        $available = [];

        foreach ($quantities as $sku => $quantity) {
            $skuRecord = Sku::where('sku', $sku)->first();

            if (! $skuRecord) {
                $errors[] = "SKU không tồn tại: {$sku}";
                $available[$sku] = null;

                continue;
            }

            $sellable = $skuRecord->sellableQuantity();
            $available[$sku] = $sellable;

            if ($quantity > $sellable) {
                $errors[] = "Sản phẩm {$sku} chỉ còn {$sellable} (yêu cầu {$quantity}).";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'available' => $available,
        ];
    }

    /**
     * Lấy tồn kho khả dụng của một SKU (từ cache DB hiện tại).
     */
    public function availableStock(string $sku): ?int
    {
        $skuRecord = Sku::where('sku', $sku)->first();

        return $skuRecord?->sellableQuantity();
    }

    /**
     * SKU sắp hết hàng (theo ngưỡng cấu hình).
     *
     * @return \Illuminate\Support\Collection<int, Sku>
     */
    public function lowStock(int $threshold = 5)
    {
        return Sku::query()
            ->where('available_stock', '<=', $threshold)
            ->orderBy('available_stock')
            ->get();
    }

    /**
     * Lịch sử thay đổi tồn kho của một SKU.
     */
    public function history(string $sku, int $limit = 50)
    {
        return InventoryLog::query()
            ->where('sku', $sku)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
