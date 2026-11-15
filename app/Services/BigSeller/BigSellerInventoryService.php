<?php

namespace App\Services\BigSeller;

use App\Models\BigSellerConnection;
use App\Models\BigSellerSyncLog;
use App\Models\Warehouse;
use App\Services\BigSeller\Contracts\BigSellerClient;
use Illuminate\Support\Str;

/**
 * Dịch vụ tồn kho từ BigSeller.
 *
 * Đây là lớp trung gian giữa "gọi API" và "cập nhật DB".
 */
class BigSellerInventoryService
{
    public function __construct(
        protected BigSellerClient $client,
        protected BigSellerAuthService $auth,
    ) {
    }

    /**
     * Lấy danh sách kho đang dùng để đồng bộ.
     *
     * @return \Illuminate\Support\Collection<int, Warehouse>
     */
    public function activeWarehouses()
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * Kho mặc định dùng cho việc đồng bộ tồn kho.
     */
    public function defaultWarehouse(): ?Warehouse
    {
        $connection = $this->auth->activeConnection();

        if ($connection?->default_warehouse_id) {
            $warehouse = Warehouse::find($connection->default_warehouse_id);

            if ($warehouse) {
                return $warehouse;
            }
        }

        return $this->activeWarehouses()->first();
    }

    /**
     * Truy vấn tồn kho BigSeller cho một danh sách SKU tại một kho.
     *
     * @param  array<int, string>  $skus
     * @return array{items: array, raw: array}
     */
    public function fetchInventory(array $skus, ?Warehouse $warehouse = null, ?string $syncRunId = null): array
    {
        $warehouse ??= $this->defaultWarehouse();

        $params = [
            'skus' => array_values($skus),
            'sync_run_id' => $syncRunId ?? (string) Str::uuid(),
        ];

        if ($warehouse) {
            $params['warehouse_code'] = $warehouse->code;

            if ($warehouse->bigseller_warehouse_id) {
                $params['warehouse_id'] = (string) $warehouse->bigseller_warehouse_id;
            }
        }

        return $this->client->getInventoryListDetail($params);
    }

    /**
     * Kiểm tra tồn kho khả dụng của một SKU tại BigSeller (kiểm tra gần thời điểm đặt hàng).
     *
     * Trả về null nếu không lấy được dữ liệu (mạng lỗi) — KHÔNG giả định là còn hàng.
     */
    public function checkAvailable(string $sku, ?Warehouse $warehouse = null): ?int
    {
        try {
            $result = $this->fetchInventory([$sku], $warehouse);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        foreach ($result['items'] as $item) {
            if ($item->sku === $sku) {
                return $item->available;
            }
        }

        // SKU không có trong response => không tồn tại / không có tồn kho.
        return 0;
    }

    /**
     * Danh sách SKU trên BigSeller đã có trong hệ thống (để đối chiếu).
     *
     * @return array<int, string>
     */
    public function mappedSkus(): array
    {
        return \App\Models\BigSellerProductMapping::query()
            ->pluck('bigseller_sku')
            ->all();
    }

    /**
     * Lấy connection đang hoạt động.
     */
    public function connection(): ?BigSellerConnection
    {
        return $this->auth->activeConnection();
    }

    /**
     * Số lần request inventory đã thực hiện (để thống kê).
     */
    public function requestCount(int $minutes = 60): int
    {
        return BigSellerSyncLog::query()
            ->where('operation', 'inventory')
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }
}
