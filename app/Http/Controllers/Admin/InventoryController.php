<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryResource;
use App\Jobs\SyncBigSellerInventoryJob;
use App\Models\InventoryLog;
use App\Models\Sku;
use App\Models\Warehouse;
use App\Services\BigSeller\BigSellerInventoryService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected BigSellerInventoryService $bigSellerInventory,
    ) {
    }

    /**
     * Danh sách SKU & tồn kho.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Sku::query()->with(['product', 'variant']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('sync_state')) {
            $query->where('sync_state', $request->query('sync_state'));
        }

        // Lọc sắp hết hàng
        if ($request->boolean('low_stock')) {
            $threshold = (int) \App\Models\Setting::get('inventory.low_stock_threshold', 5);
            $query->where('available_stock', '<=', $threshold);
        }

        if ($request->boolean('out_of_stock')) {
            $query->where('available_stock', '<=', 0);
        }

        $perPage = min(200, max(1, (int) $request->query('per_page', 30)));

        return InventoryResource::collection($query->orderBy('sku')->paginate($perPage)->withQueryString());
    }

    /**
     * Đồng bộ tồn kho thủ công (chạy đồng bộ, không dispatch queue để Admin
     * thấy kết quả ngay).
     */
    public function sync(Request $request): JsonResponse
    {
        $result = $this->inventoryService->syncFromBigSeller();

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
        ], $result['error'] === null ? 200 : 502);
    }

    /**
     * Đồng bộ tồn kho qua queue.
     */
    public function dispatchSync(): JsonResponse
    {
        SyncBigSellerInventoryJob::dispatch();

        return response()->json([
            'message' => 'Đã đưa đồng bộ tồn kho vào hàng đợi.',
        ]);
    }

    /**
     * Lịch sử thay đổi tồn kho.
     */
    public function history(Request $request, string $sku): JsonResponse
    {
        $logs = InventoryLog::query()
            ->where('sku', $sku)
            ->latest('id')
            ->limit(min(200, max(1, (int) $request->query('limit', 50))))
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'previous_available' => (int) $log->previous_available,
                'new_available' => (int) $log->new_available,
                'delta' => (int) $log->delta,
                'reason' => $log->reason,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * Đối chiếu kho BigSeller với website.
     */
    public function warehouses(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->with('bigSellerMapping')
            ->get()
            ->map(fn ($warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'is_active' => (bool) $warehouse->is_active,
                'bigseller' => $warehouse->bigSellerMapping ? [
                    'code' => $warehouse->bigSellerMapping->bigseller_warehouse_code,
                    'id' => $warehouse->bigSellerMapping->bigseller_warehouse_id,
                    'name' => $warehouse->bigSellerMapping->bigseller_warehouse_name,
                ] : null,
            ]);

        return response()->json([
            'data' => $warehouses,
            'meta' => [
                'default_warehouse' => $this->bigSellerInventory->defaultWarehouse()?->code,
                'inventory_requests_last_hour' => $this->bigSellerInventory->requestCount(60),
            ],
        ]);
    }
}
