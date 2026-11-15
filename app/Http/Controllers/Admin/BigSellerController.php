<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SyncState;
use App\Http\Controllers\Controller;
use App\Jobs\PushWebsiteOrderToBigSellerJob;
use App\Jobs\RetryBigSellerSyncJob;
use App\Jobs\SyncBigSellerInventoryJob;
use App\Jobs\SyncBigSellerProductsJob;
use App\Models\BigSellerConnection;
use App\Models\BigSellerProductMapping;
use App\Models\BigSellerSyncLog;
use App\Models\Order;
use App\Models\Setting;
use App\Services\BigSeller\BigSellerInventoryService;
use App\Services\BigSeller\BigSellerOrderService;
use App\Services\BigSeller\BigSellerProductService;
use App\Services\BigSellerService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BigSellerController extends Controller
{
    public function __construct(
        protected BigSellerService $bigSeller,
        protected BigSellerInventoryService $bigSellerInventory,
        protected BigSellerOrderService $bigSellerOrders,
        protected BigSellerProductService $bigSellerProducts,
        protected InventoryService $inventoryService,
    ) {
    }

    /**
     * Trạng thái kết nối & quyền API.
     *
     * KHÔNG bao giờ trả về app_key / access_token / refresh_token.
     */
    public function status(): JsonResponse
    {
        $connection = $this->bigSeller->auth()->activeConnection();

        $status = $this->bigSeller->status();

        // Kiểm tra kết nối thực tế (live mode).
        $liveCheck = null;
        if (! $this->bigSeller->isMockMode()) {
            $liveCheck = $this->bigSeller->client()->checkConnection();
        }

        return response()->json([
            'data' => [
                'mode' => $status['mode'],
                'is_mock' => $status['is_mock'],
                'message' => $status['message'],
                'success' => $status['success'],
                'capabilities' => $status['capabilities'],
                'configured_endpoints' => $status['configured_endpoints'],
                'live_check' => $liveCheck,
                'connection' => $connection ? [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'app_id' => $connection->app_id,
                    'shop_code' => $connection->shop_code,
                    'shop_name' => $connection->shop_name,
                    'capabilities' => $connection->capabilities ?? [],
                    'token_expires_at' => $connection->token_expires_at?->toIso8601String(),
                    'is_token_expired' => $connection->isTokenExpired(),
                    'last_checked_at' => $connection->last_checked_at?->toIso8601String(),
                    'last_success_at' => $connection->last_success_at?->toIso8601String(),
                    'last_error' => $connection->last_error,
                    'is_active' => (bool) $connection->is_active,
                    'is_mock' => (bool) $connection->is_mock,
                ] : null,
                'missing_capabilities' => $this->missingCapabilities(),
            ],
        ]);
    }

    /**
     * Kiểm tra kết nối (nút "Kiểm tra kết nối").
     */
    public function checkConnection(): JsonResponse
    {
        $result = $this->bigSeller->client()->checkConnection();

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'success' => $result['success'],
                'details' => $result['details'],
                'is_mock' => $this->bigSeller->isMockMode(),
            ],
        ], $result['success'] ? 200 : 502);
    }

    /**
     * Đồng bộ tồn kho.
     */
    public function syncInventory(): JsonResponse
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
    public function dispatchInventorySync(): JsonResponse
    {
        SyncBigSellerInventoryJob::dispatch();

        return response()->json(['message' => 'Đã đưa đồng bộ tồn kho vào hàng đợi.']);
    }

    /**
     * Đồng bộ sản phẩm (chỉ khi có quyền API sản phẩm).
     */
    public function syncProducts(): JsonResponse
    {
        $result = $this->bigSellerProducts->syncProducts();

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Đồng bộ sản phẩm qua queue.
     */
    public function dispatchProductSync(): JsonResponse
    {
        SyncBigSellerProductsJob::dispatch();

        return response()->json(['message' => 'Đã đưa đồng bộ sản phẩm vào hàng đợi.']);
    }

    /**
     * Đồng bộ đơn hàng (đẩy các đơn đang chờ/lỗi).
     */
    public function syncOrders(Request $request): JsonResponse
    {
        $query = Order::query()
            ->whereIn('sync_status', ['PENDING_SYNC', 'SYNC_FAILED'])
            ->whereNull('bigseller_order_code');

        $count = (clone $query)->count();

        if ($request->boolean('as_job', true)) {
            RetryBigSellerSyncJob::dispatch();

            return response()->json([
                'message' => "Đã đưa {$count} đơn hàng vào hàng đợi đồng bộ.",
                'data' => ['queued' => $count],
            ]);
        }

        $results = [];

        foreach ($query->orderBy('id')->limit(50)->get() as $order) {
            $results[] = $this->bigSellerOrders->pushOrder($order);
        }

        return response()->json([
            'message' => 'Đã xử lý '.count($results).' đơn hàng.',
            'data' => $results,
        ]);
    }

    /**
     * Đối chiếu SKU.
     */
    public function reconcileSkus(): JsonResponse
    {
        $result = $this->bigSellerProducts->reconcileSkus();

        return response()->json([
            'data' => [
                'matched_count' => count($result['matched']),
                'website_only_count' => count($result['website_only']),
                'bigseller_only_count' => count($result['bigseller_only']),
                'matched' => $result['matched'],
                'website_only' => $result['website_only'],
                'bigseller_only' => $result['bigseller_only'],
            ],
        ]);
    }

    /**
     * Lịch sử request/response tới BigSeller.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = BigSellerSyncLog::query();

        if ($request->filled('operation')) {
            $query->where('operation', $request->query('operation'));
        }

        if ($request->filled('success')) {
            $query->where('success', $request->boolean('success'));
        }

        if ($request->filled('sync_run_id')) {
            $query->where('sync_run_id', $request->query('sync_run_id'));
        }

        $logs = $query->latest('id')
            ->limit(min(200, max(1, (int) $request->query('limit', 50))))
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'sync_run_id' => $log->sync_run_id,
                'operation' => $log->operation,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'request_url' => $log->request_url,
                'request_payload' => $log->request_payload,
                'response_status' => $log->response_status,
                'response_payload' => $log->response_payload,
                'error_code' => $log->error_code,
                'error_message' => $log->error_message,
                'duration_ms' => $log->duration_ms,
                'success' => (bool) $log->success,
                'retry_count' => (int) $log->retry_count,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * Ánh xạ SKU thủ công.
     */
    public function storeSkuMapping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bigseller_sku' => ['required', 'string', 'max:100'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'sku_id' => ['nullable', 'integer', 'exists:skus,id'],
            'bigseller_product_id' => ['nullable', 'integer'],
            'bigseller_variant_id' => ['nullable', 'integer'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Chống tạo mapping trùng SKU.
        $exists = BigSellerProductMapping::where('bigseller_sku', $validated['bigseller_sku'])->first();

        if ($exists) {
            return response()->json([
                'message' => "SKU {$validated['bigseller_sku']} đã có ánh xạ. Vui lòng cập nhật bản ghi hiện có.",
            ], 422);
        }

        $mapping = $this->bigSellerProducts->createMapping($validated + ['sync_state' => SyncState::Pending]);

        return response()->json([
            'message' => 'Tạo ánh xạ SKU thành công.',
            'data' => $mapping,
        ], 201);
    }

    /**
     * Danh sách ánh xạ SKU.
     */
    public function skuMappings(Request $request): JsonResponse
    {
        $mappings = BigSellerProductMapping::query()
            ->with(['product', 'variant'])
            ->latest('id')
            ->limit(min(200, max(1, (int) $request->query('limit', 50))))
            ->get()
            ->map(fn ($mapping) => [
                'id' => $mapping->id,
                'bigseller_sku' => $mapping->bigseller_sku,
                'bigseller_product_id' => $mapping->bigseller_product_id,
                'bigseller_variant_id' => $mapping->bigseller_variant_id,
                'product_name' => $mapping->product_name,
                'product' => $mapping->product ? ['id' => $mapping->product->id, 'name' => $mapping->product->name] : null,
                'variant' => $mapping->variant ? ['id' => $mapping->variant->id, 'name' => $mapping->variant->name] : null,
                'sync_state' => $mapping->sync_state->value,
                'sync_state_label' => $mapping->sync_state->label(),
                'last_synced_at' => $mapping->last_synced_at?->toIso8601String(),
                'sync_error' => $mapping->sync_error,
            ]);

        return response()->json(['data' => $mappings]);
    }

    /**
     * Cấu hình lịch đồng bộ.
     */
    public function schedule(): JsonResponse
    {
        return response()->json([
            'data' => [
                'inventory_sync_every_minutes' => (int) config('bigseller.inventory_sync_every_minutes', 15),
                'safety_stock' => (int) config('bigseller.safety_stock', 0),
                'jobs' => [
                    'inventory' => 'SyncBigSellerInventoryJob',
                    'products' => 'SyncBigSellerProductsJob',
                    'orders' => 'PushWebsiteOrderToBigSellerJob',
                    'retry' => 'RetryBigSellerSyncJob',
                    'order_status' => 'SyncBigSellerOrderStatusJob',
                ],
                'scheduler_hint' => 'Cần chạy: php artisan schedule:work (dev) hoặc cron * * * * * php artisan schedule:run (production).',
            ],
        ]);
    }

    /**
     * Cập nhật cấu hình lịch đồng bộ (lưu vào settings).
     */
    public function updateSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inventory_sync_every_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'safety_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated as $key => $value) {
                if ($value === null) {
                    continue;
                }

                Setting::updateOrCreate(
                    ['key' => 'bigseller.'.$key],
                    [
                        'value' => (string) $value,
                        'type' => 'int',
                        'group' => 'bigseller',
                        'description' => 'Cấu hình đồng bộ BigSeller',
                    ],
                );
            }
        });

        return response()->json(['message' => 'Đã lưu cấu hình đồng bộ.']);
    }

    /**
     * Danh sách capability còn thiếu.
     *
     * @return array<int, string>
     */
    protected function missingCapabilities(): array
    {
        $granted = $this->bigSeller->capabilities();

        $needed = ['inventory', 'create_order'];

        return array_values(array_diff($needed, $granted));
    }
}
