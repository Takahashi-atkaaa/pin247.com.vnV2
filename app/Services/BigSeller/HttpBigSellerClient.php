<?php

namespace App\Services\BigSeller;

use App\Exceptions\BigSellerApiException;
use App\Exceptions\BigSellerConfigurationException;
use App\Models\BigSellerSyncLog;
use App\Services\BigSeller\Contracts\BigSellerClient;
use App\Services\BigSeller\Data\InventoryItem;
use App\Services\BigSeller\Data\ManualOrderResult;
use App\Services\BigSeller\Data\OrderStatusResult;
use App\Services\BigSeller\Data\ProductData;
use App\Services\BigSeller\Data\WarehouseData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP client thật gọi BigSeller Open API.
 *
 * NGUYÊN TẮC TUYỆT ĐỐI:
 * - Base URL và đường dẫn endpoint đến TỪ CẤU HÌNH, được lấy từ tài liệu
 *   API chính thức của BigSeller. Nếu chưa cấu hình => ném
 *   BigSellerConfigurationException, KHÔNG tự bịa URL.
 * - Toàn bộ request/response được ghi vào bigseller_sync_logs để đối chiếu.
 * - Không bao giờ ghi access_token / app_key vào log.
 */
class HttpBigSellerClient implements BigSellerClient
{
    public function __construct(
        protected BigSellerAuthService $auth,
    ) {
    }

    /**
     * Tạo HTTP client đã cấu hình timeout + header xác thực.
     */
    protected function client(): PendingRequest
    {
        $connection = $this->auth->activeConnection();

        return Http::baseUrl((string) config('bigseller.base_url'))
            ->timeout((int) config('bigseller.timeout'))
            ->connectTimeout((int) config('bigseller.connect_timeout'))
            ->withHeaders($this->auth->authHeaders($connection))
            ->acceptJson();
    }

    /**
     * Lấy đường dẫn endpoint đã cấu hình, nếu chưa có thì ném lỗi cấu hình.
     */
    protected function endpoint(string $key): string
    {
        $path = config("bigseller.endpoints.{$key}");

        if (blank($path)) {
            throw BigSellerConfigurationException::missingConfig(
                "đường dẫn endpoint '{$key}' (cần lấy từ tài liệu API chính thức của BigSeller)"
            );
        }

        if (blank(config('bigseller.base_url'))) {
            throw BigSellerConfigurationException::missingConfig('BIGSELLER_BASE_URL');
        }

        return (string) $path;
    }

    /**
     * Thực hiện request có log đầy đủ.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array}
     */
    protected function request(
        string $operation,
        string $method,
        string $path,
        array $query = [],
        array $payload = [],
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $syncRunId = null,
        int $retryCount = 0,
    ): array {
        $startedAt = microtime(true);
        $url = (string) config('bigseller.base_url').'/'.ltrim($path, '/');

        try {
            $response = match (strtoupper($method)) {
                'GET' => $this->client()->get($path, $query),
                'POST' => $this->client()->post($path, $payload),
                'PUT' => $this->client()->put($path, $payload),
                'DELETE' => $this->client()->delete($path, $query),
                default => throw new BigSellerApiException("HTTP method không hỗ trợ: {$method}"),
            };

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $body = $response->json() ?? [];

            $this->log($operation, $url, $payload ?: $query, $response->status(), $body, null, null, $durationMs, $entityType, $entityId, $syncRunId, $retryCount);

            if ($response->failed()) {
                throw new BigSellerApiException(
                    "BigSeller API trả về lỗi HTTP {$response->status()}",
                    errorCode: is_array($body) ? ($body['error_code'] ?? null) : null,
                    statusCode: $response->status(),
                    context: ['body' => $body],
                );
            }

            // BigSeller có thể trả HTTP 200 nhưng business code = lỗi.
            if (is_array($body) && array_key_exists('success', $body) && ! $body['success']) {
                throw new BigSellerApiException(
                    (string) ($body['error_msg'] ?? 'BigSeller trả về lỗi nghiệp vụ'),
                    errorCode: isset($body['error_code']) ? (string) $body['error_code'] : null,
                    statusCode: $response->status(),
                    context: ['body' => $body],
                );
            }

            return ['status' => $response->status(), 'body' => $body];
        } catch (ConnectionException $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->log($operation, $url, $payload ?: $query, null, null, 'CONNECTION_ERROR', $e->getMessage(), $durationMs, $entityType, $entityId, $syncRunId, $retryCount);

            throw new BigSellerApiException(
                'Không thể kết nối tới BigSeller: '.$e->getMessage(),
                errorCode: 'CONNECTION_ERROR',
                statusCode: null,
                context: ['url' => $url],
            );
        } catch (BigSellerApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->log($operation, $url, $payload ?: $query, null, null, 'UNEXPECTED_ERROR', $e->getMessage(), $durationMs, $entityType, $entityId, $syncRunId, $retryCount);

            throw new BigSellerApiException(
                'Lỗi không mong đợi khi gọi BigSeller: '.$e->getMessage(),
                errorCode: 'UNEXPECTED_ERROR',
                statusCode: null,
            );
        }
    }

    /**
     * Ghi log request/response vào DB (không chứa credentials).
     */
    protected function log(
        string $operation,
        string $url,
        array $requestPayload,
        ?int $status,
        ?array $responseBody,
        ?string $errorCode,
        ?string $errorMessage,
        int $durationMs,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $syncRunId = null,
        int $retryCount = 0,
    ): void {
        try {
            BigSellerSyncLog::create([
                'sync_run_id' => $syncRunId ?? (string) Str::uuid(),
                'operation' => $operation,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'request_url' => $url,
                'request_payload' => $requestPayload ?: null,
                'response_status' => $status,
                'response_payload' => $responseBody,
                'error_code' => $errorCode,
                'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 1000) : null,
                'duration_ms' => $durationMs,
                'success' => $errorCode === null && $status !== null && $status < 400,
                'retry_count' => $retryCount,
            ]);
        } catch (\Throwable $e) {
            // Không để lỗi ghi log làm hỏng luồng nghiệp vụ chính.
            Log::error('Không ghi được BigSeller sync log: '.$e->getMessage());
        }
    }

    public function checkConnection(): array
    {
        $connection = $this->auth->activeConnection();

        if ($connection === null) {
            $this->auth->recordCheck(null, false, 'Chưa có kết nối BigSeller');

            return [
                'success' => false,
                'message' => 'Chưa cấu hình kết nối BigSeller.',
                'details' => [],
            ];
        }

        if (blank(config('bigseller.base_url'))) {
            $this->auth->recordCheck($connection, false, 'Thiếu BIGSELLER_BASE_URL');

            return [
                'success' => false,
                'message' => 'Thiếu cấu hình BIGSELLER_BASE_URL. Cần lấy từ tài liệu API chính thức của BigSeller.',
                'details' => [],
            ];
        }

        try {
            // Kiểm tra tối thiểu: gọi endpoint tồn kho với danh sách SKU rỗng.
            $result = $this->getInventoryListDetail(['skus' => [], 'warehouse_code' => null]);

            $this->auth->recordCheck($connection, true);

            return [
                'success' => true,
                'message' => 'Kết nối BigSeller thành công.',
                'details' => [
                    'shop_code' => $connection->shop_code,
                    'capabilities' => $connection->capabilities ?? [],
                    'token_expires_at' => $connection->token_expires_at?->toIso8601String(),
                ],
            ];
        } catch (\Throwable $e) {
            $this->auth->recordCheck($connection, false, $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => ['exception' => $e::class],
            ];
        }
    }

    public function getInventoryListDetail(array $params): array
    {
        $this->auth->requireCapability('inventory');

        $result = $this->request(
            operation: 'inventory',
            method: 'GET',
            path: $this->endpoint('inventory'),
            query: $params,
            syncRunId: $params['sync_run_id'] ?? null,
        );

        $body = $result['body'];
        $rows = $body['data'] ?? $body['items'] ?? $body['list'] ?? [];

        $items = [];

        foreach ((array) $rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $item = InventoryItem::fromArray(
                $row,
                $params['warehouse_code'] ?? null,
                isset($params['warehouse_id']) ? (string) $params['warehouse_id'] : null,
            );

            if ($item->sku === '') {
                continue;
            }

            $items[] = $item;
        }

        return ['items' => $items, 'raw' => $body];
    }

    public function createManualOrder(array $payload): ManualOrderResult
    {
        $this->auth->requireCapability('create_order');

        $result = $this->request(
            operation: 'create_order',
            method: 'POST',
            path: $this->endpoint('create_order'),
            payload: $payload,
            entityType: 'order',
            entityId: $payload['__local_order_id'] ?? null,
            syncRunId: $payload['__sync_run_id'] ?? null,
        );

        return ManualOrderResult::fromResponse($result['body']);
    }

    public function cancelOrder(array $params): OrderStatusResult
    {
        $this->auth->requireCapability('cancel_order');

        $result = $this->request(
            operation: 'cancel_order',
            method: 'POST',
            path: $this->endpoint('cancel_order'),
            payload: $params,
            entityType: 'order',
            entityId: $params['__local_order_id'] ?? null,
        );

        return OrderStatusResult::fromResponse($result['body']);
    }

    public function getOrderStatus(array $params): OrderStatusResult
    {
        $this->auth->requireCapability('order_status');

        $result = $this->request(
            operation: 'order_status',
            method: 'GET',
            path: $this->endpoint('order_status'),
            query: $params,
            entityType: 'order',
            entityId: $params['__local_order_id'] ?? null,
        );

        return OrderStatusResult::fromResponse($result['body']);
    }

    public function getProducts(array $params): array
    {
        $this->auth->requireCapability('products');

        $result = $this->request(
            operation: 'products',
            method: 'GET',
            path: $this->endpoint('products'),
            query: $params,
        );

        $body = $result['body'];
        $rows = $body['data'] ?? $body['items'] ?? $body['list'] ?? [];

        $products = [];

        foreach ((array) $rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $product = ProductData::fromArray($row);

            if ($product->sku === '') {
                continue;
            }

            $products[] = $product;
        }

        return ['items' => $products, 'raw' => $body];
    }

    public function getWarehouses(array $params): array
    {
        $this->auth->requireCapability('warehouses');

        $result = $this->request(
            operation: 'warehouses',
            method: 'GET',
            path: $this->endpoint('warehouses'),
            query: $params,
        );

        $body = $result['body'];
        $rows = $body['data'] ?? $body['items'] ?? $body['list'] ?? [];

        $warehouses = [];

        foreach ((array) $rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $warehouses[] = WarehouseData::fromArray($row);
        }

        return ['items' => $warehouses, 'raw' => $body];
    }
}
