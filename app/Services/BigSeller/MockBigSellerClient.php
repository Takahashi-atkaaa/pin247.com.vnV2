<?php

namespace App\Services\BigSeller;

use App\Exceptions\BigSellerApiException;
use App\Services\BigSeller\Contracts\BigSellerClient;
use App\Services\BigSeller\Data\InventoryItem;
use App\Services\BigSeller\Data\ManualOrderResult;
use App\Services\BigSeller\Data\OrderStatusResult;
use App\Services\BigSeller\Data\ProductData;
use App\Services\BigSeller\Data\WarehouseData;
use App\Models\BigSellerSyncLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BIGSELLER MOCK CLIENT — CHỈ DÀNH CHO DEVELOPMENT / TEST.
 *
 * TUYỆT ĐỐI KHÔNG dùng trên production.
 *
 * Mục đích:
 * - Cho phép phát triển & kiểm thử toàn bộ luồng (tồn kho, tạo đơn, retry,
 *   chống trùng) mà không cần quyền API thật.
 * - Mô phỏng các tình huống lỗi: timeout, lỗi nghiệp vụ, SKU không tồn tại.
 *
 * Mọi kết quả mock đều được đánh dấu `is_mock = true` và hiển thị rõ trong
 * Admin UI. Không bao giờ báo "đã tích hợp BigSeller thành công" khi chỉ
 * chạy mock.
 */
class MockBigSellerClient implements BigSellerClient
{
    /**
     * Kho mô phỏng: SKU => tồn kho khả dụng.
     *
     * @var array<string, int>
     */
    protected array $inventory = [];

    /**
     * Danh sách SKU không tồn tại (dùng để test cảnh báo).
     *
     * @var array<int, string>
     */
    protected array $missingSkus = [];

    /**
     * Đơn đã tạo trong mock: idempotency_key => bigseller_order_code.
     *
     * @var array<string, string>
     */
    protected array $createdOrders = [];

    /**
     * Cờ mô phỏng timeout cho lần gọi kế tiếp.
     */
    protected bool $simulateTimeout = false;

    /**
     * Cờ mô phỏng lỗi nghiệp vụ.
     */
    protected ?string $simulateError = null;

    /**
     * Tự động sinh mã đơn BigSeller mô phỏng.
     */
    protected int $orderSequence = 0;

    public function __construct()
    {
        $this->simulateTimeout = (bool) config('bigseller.mock.simulate_timeout', false);
    }

    /**
     * Thiết lập tồn kho mô phỏng cho một SKU.
     */
    public function setStock(string $sku, int $available, array $extra = []): void
    {
        $this->inventory[$sku] = $available;
        $this->extra[$sku] = $extra;
    }

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $extra = [];

    /**
     * Đánh dấu SKU không tồn tại trên BigSeller.
     */
    public function markMissingSku(string $sku): void
    {
        $this->missingSkus[] = $sku;
    }

    /**
     * Bật/tắt mô phỏng timeout cho lần gọi kế tiếp.
     */
    public function simulateTimeoutOnNextCall(): void
    {
        $this->simulateTimeout = true;
    }

    /**
     * Bật/tắt mô phỏng lỗi nghiệp vụ.
     */
    public function simulateErrorOnNextCall(?string $message): void
    {
        $this->simulateError = $message;
    }

    /**
     * Đặt lại trạng thái mock.
     */
    public function reset(): void
    {
        $this->inventory = [];
        $this->extra = [];
        $this->missingSkus = [];
        $this->createdOrders = [];
        $this->simulateTimeout = false;
        $this->simulateError = null;
        $this->orderSequence = 0;
    }

    /**
     * Ném lỗi mô phỏng nếu được cấu hình.
     */
    protected function guardSimulatedFailure(string $operation): void
    {
        if ($this->simulateTimeout) {
            $this->simulateTimeout = false;

            $this->log($operation, null, [], null, 'TIMEOUT', 'BigSeller mock: timeout mô phỏng', null);

            throw new BigSellerApiException(
                'BigSeller mock: timeout mô phỏng',
                errorCode: 'TIMEOUT',
                statusCode: null,
            );
        }

        if ($this->simulateError !== null) {
            $message = $this->simulateError;
            $this->simulateError = null;

            $this->log($operation, null, [], 400, 'MOCK_ERROR', $message, null);

            throw new BigSellerApiException(
                'BigSeller mock: '.$message,
                errorCode: 'MOCK_ERROR',
                statusCode: 400,
            );
        }
    }

    protected function log(
        string $operation,
        ?string $url,
        array $requestPayload,
        ?int $status,
        ?string $errorCode,
        ?string $errorMessage,
        ?int $durationMs,
    ): void {
        try {
            BigSellerSyncLog::create([
                'sync_run_id' => (string) Str::uuid(),
                'operation' => $operation,
                'entity_type' => null,
                'entity_id' => null,
                'request_url' => $url ?? 'mock://'.$operation,
                'request_payload' => $requestPayload ?: null,
                'response_status' => $status,
                'response_payload' => null,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'duration_ms' => $durationMs,
                'success' => $errorCode === null,
                'retry_count' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Không ghi được BigSeller mock log: '.$e->getMessage());
        }
    }

    public function checkConnection(): array
    {
        $this->guardSimulatedFailure('check_connection');

        return [
            'success' => true,
            'message' => 'BigSeller MOCK client đang hoạt động (KHÔNG phải kết nối thật).',
            'details' => [
                'is_mock' => true,
                'simulated_skus' => array_keys($this->inventory),
            ],
        ];
    }

    public function getInventoryListDetail(array $params): array
    {
        $this->guardSimulatedFailure('inventory');

        $skus = (array) ($params['skus'] ?? []);
        $defaultStock = (int) config('bigseller.mock.default_stock', 100);

        $items = [];

        foreach ($skus as $sku) {
            $sku = (string) $sku;

            if (in_array($sku, $this->missingSkus, true)) {
                continue;
            }

            $extra = $this->extra[$sku] ?? [];

            $items[] = new InventoryItem(
                sku: $sku,
                available: (int) ($this->inventory[$sku] ?? $defaultStock),
                reserved: $extra['reserved'] ?? null,
                onHand: $extra['on_hand'] ?? null,
                locked: $extra['locked'] ?? null,
                shipped: $extra['shipped'] ?? null,
                warehouseCode: $params['warehouse_code'] ?? null,
                warehouseId: isset($params['warehouse_id']) ? (string) $params['warehouse_id'] : null,
                raw: ['mock' => true],
            );
        }

        $this->log('inventory', 'mock://inventory', ['skus' => $skus], 200, null, null, null);

        return [
            'items' => $items,
            'raw' => ['success' => true, 'data' => array_map(fn ($i) => $i->toArray(), $items), 'mock' => true],
        ];
    }

    public function createManualOrder(array $payload): ManualOrderResult
    {
        $this->guardSimulatedFailure('create_order');

        $idempotencyKey = (string) ($payload['__idempotency_key'] ?? '');

        // Chống tạo đơn trùng: nếu key đã tồn tại, trả lại đơn cũ.
        if ($idempotencyKey !== '' && isset($this->createdOrders[$idempotencyKey])) {
            return new ManualOrderResult(
                success: true,
                bigSellerOrderCode: $this->createdOrders[$idempotencyKey],
                status: 'PENDING_CONFIRM',
                raw: ['success' => true, 'mock' => true, 'duplicate' => true],
            );
        }

        // Kiểm tra SKU có tồn tại không.
        $items = (array) ($payload['items'] ?? []);
        foreach ($items as $item) {
            $sku = (string) ($item['sku'] ?? '');
            if ($sku !== '' && in_array($sku, $this->missingSkus, true)) {
                $this->log('create_order', 'mock://create_order', $payload, 400, 'SKU_NOT_FOUND', "SKU không tồn tại: {$sku}", null);

                return ManualOrderResult::failure(
                    "SKU không tồn tại trên BigSeller: {$sku}",
                    'SKU_NOT_FOUND',
                    ['success' => false, 'mock' => true],
                );
            }
        }

        $code = 'BS-MOCK-'.str_pad((string) (++$this->orderSequence), 6, '0', STR_PAD_LEFT);

        if ($idempotencyKey !== '') {
            $this->createdOrders[$idempotencyKey] = $code;
        }

        $this->log('create_order', 'mock://create_order', $payload, 200, null, null, null);

        return new ManualOrderResult(
            success: true,
            bigSellerOrderCode: $code,
            status: 'PENDING_CONFIRM',
            raw: ['success' => true, 'mock' => true, 'data' => ['order_code' => $code]],
        );
    }

    public function cancelOrder(array $params): OrderStatusResult
    {
        $this->guardSimulatedFailure('cancel_order');

        $this->log('cancel_order', 'mock://cancel_order', $params, 200, null, null, null);

        return new OrderStatusResult(
            success: true,
            bigSellerOrderCode: $params['order_code'] ?? null,
            rawStatus: 'CANCELLED',
            raw: ['success' => true, 'mock' => true],
        );
    }

    public function getOrderStatus(array $params): OrderStatusResult
    {
        $this->guardSimulatedFailure('order_status');

        $this->log('order_status', 'mock://order_status', $params, 200, null, null, null);

        return new OrderStatusResult(
            success: true,
            bigSellerOrderCode: $params['order_code'] ?? null,
            rawStatus: 'PENDING_CONFIRM',
            raw: ['success' => true, 'mock' => true],
        );
    }

    public function getProducts(array $params): array
    {
        $this->guardSimulatedFailure('products');

        $skus = (array) ($params['skus'] ?? []);

        $items = [];

        foreach ($skus as $sku) {
            $sku = (string) $sku;

            if (in_array($sku, $this->missingSkus, true)) {
                continue;
            }

            $items[] = new ProductData(
                sku: $sku,
                name: 'Sản phẩm mock '.$sku,
                price: 0.0,
                raw: ['mock' => true],
            );
        }

        $this->log('products', 'mock://products', $params, 200, null, null, null);

        return ['items' => $items, 'raw' => ['success' => true, 'mock' => true]];
    }

    public function getWarehouses(array $params): array
    {
        $this->guardSimulatedFailure('warehouses');

        $this->log('warehouses', 'mock://warehouses', $params, 200, null, null, null);

        return [
            'items' => [
                new WarehouseData(code: 'WH-MOCK-01', name: 'Kho mô phỏng', id: '1', raw: ['mock' => true]),
            ],
            'raw' => ['success' => true, 'mock' => true],
        ];
    }
}
