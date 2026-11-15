<?php

namespace Tests\Feature;

use App\Enums\OrderSyncStatus;
use App\Enums\PaymentMethod;
use App\Enums\SyncState;
use App\Exceptions\BigSellerApiException;
use App\Jobs\PushWebsiteOrderToBigSellerJob;
use App\Jobs\RetryBigSellerSyncJob;
use App\Jobs\SyncBigSellerInventoryJob;
use App\Models\BigSellerOrderMapping;
use App\Models\BigSellerProductMapping;
use App\Models\BigSellerSyncLog;
use App\Models\InventoryLog;
use App\Models\InventorySnapshot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Services\BigSeller\BigSellerInventoryService;
use App\Services\BigSeller\BigSellerOrderService;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Kịch bản 5-15: Tích hợp BigSeller — timeout, lỗi, retry, chống trùng,
 * hủy đơn, đối chiếu SKU, mất kết nối, SKU không tồn tại.
 */
class BigSellerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bigseller.mode' => 'mock']);
        $this->makeBigSellerConnection();
    }

    /**
     * Tạo một đơn hàng đã có item trong DB.
     */
    protected function makeOrder(
        string $skuCode = 'SKU-A',
        int $quantity = 2,
        float $price = 100000,
        OrderSyncStatus $status = OrderSyncStatus::PendingSync,
    ): Order {
        $sku = $this->makeSku($skuCode, stock: 100, safetyStock: 0, price: $price);

        $order = Order::create([
            'order_code' => Order::generateOrderCode(),
            'customer_name' => 'Nguyễn Văn B',
            'customer_phone' => '0987654321',
            'shipping_address' => 'Số 2 Đường Láng',
            'payment_method' => PaymentMethod::Cod,
            'sync_status' => $status,
            'subtotal' => $price * $quantity,
            'total_amount' => $price * $quantity,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'sku_id' => $sku->id,
            'product_id' => $sku->product_id,
            'product_variant_id' => $sku->product_variant_id,
            'sku' => $skuCode,
            'product_name' => 'SP '.$skuCode,
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $price * $quantity,
        ]);

        return $order->fresh();
    }

    /**
     * KỊCH BẢN 5: Timeout API BigSeller được xử lý và ghi log.
     */
    public function test_bigseller_api_timeout_is_handled_and_logged(): void
    {
        $sku = $this->makeSku('SKU-TIMEOUT', stock: 50);
        $this->mockClient()->setStock('SKU-TIMEOUT', 50);
        $this->mockClient()->simulateTimeoutOnNextCall();

        $result = app(InventoryService::class)->syncFromBigSeller();

        // Đồng bộ phải thất bại một cách an toàn, không làm hỏng dữ liệu.
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('timeout', mb_strtolower($result['error']));
        $this->assertSame(0, $result['updated']);

        // Tồn kho KHÔNG bị thay đổi khi API lỗi.
        $this->assertSame(50, (int) $sku->fresh()->available_stock);

        // Log lỗi phải được ghi lại.
        $this->assertDatabaseHas('bigseller_sync_logs', [
            'error_code' => 'TIMEOUT',
            'success' => false,
        ]);
    }

    /**
     * KỊCH BẢN 6: Lỗi nghiệp vụ từ BigSeller được xử lý.
     */
    public function test_bigseller_api_error_response_is_handled(): void
    {
        $this->makeSku('SKU-ERR', stock: 50);
        $this->mockClient()->simulateErrorOnNextCall('SKU không tồn tại trên BigSeller');

        $result = app(InventoryService::class)->syncFromBigSeller();

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('SKU không tồn tại', $result['error']);

        $this->assertDatabaseHas('bigseller_sync_logs', [
            'error_code' => 'MOCK_ERROR',
            'success' => false,
        ]);
    }

    /**
     * KỊCH BẢN 7: Phát hiện token hết hạn.
     */
    public function test_expired_token_is_detected(): void
    {
        $connection = $this->makeBigSellerConnection();
        $connection->forceFill(['token_expires_at' => now()->subMinute()])->save();

        $auth = app(\App\Services\BigSeller\BigSellerAuthService::class);

        $this->assertTrue($auth->isTokenExpired());
        $this->assertTrue($connection->fresh()->isTokenExpired());

        // Khi token hết hạn thì hệ thống KHÔNG được coi là đã kết nối thành công.
        $this->assertFalse($auth->hasCapability('inventory') && ! $auth->isTokenExpired());
    }

    /**
     * KỊCH BẢN 8: Đồng bộ trùng SKU không tạo bản ghi trùng lặp.
     */
    public function test_duplicate_sku_sync_does_not_create_duplicates(): void
    {
        $sku = $this->makeSku('SKU-DUP', stock: 10);
        $this->mockClient()->setStock('SKU-DUP', 25);

        $inventory = app(InventoryService::class);

        $first = $inventory->syncFromBigSeller('run-1');
        $second = $inventory->syncFromBigSeller('run-2');

        $this->assertSame(1, $first['updated']);
        $this->assertSame(0, $second['updated'], 'Lần đồng bộ thứ hai không có gì thay đổi.');
        $this->assertSame(1, $second['unchanged']);

        // Chỉ có 1 SKU, tồn kho đúng 1 lần cập nhật.
        $this->assertSame(1, Sku::where('sku', 'SKU-DUP')->count());
        $this->assertSame(25, (int) $sku->fresh()->available_stock);

        // Mỗi lần đồng bộ có 1 snapshot (lần 2 không đổi -> không tạo snapshot mới).
        $this->assertSame(1, InventorySnapshot::where('sku', 'SKU-DUP')->count());
        $this->assertSame(1, InventoryLog::where('sku', 'SKU-DUP')->count());
    }

    /**
     * KỊCH BẢN 9: Đơn đồng bộ lỗi được đánh dấu SYNC_FAILED.
     */
    public function test_failed_order_sync_is_marked_sync_failed(): void
    {
        $order = $this->makeOrder('SKU-FAIL');

        $this->mockClient()->simulateErrorOnNextCall('BigSeller từ chối đơn hàng');

        $result = app(OrderService::class)->pushToBigSeller($order);

        $this->assertFalse($result['success']);

        $order->refresh();
        $this->assertSame(OrderSyncStatus::SyncFailed, $order->sync_status);
        $this->assertNotEmpty($order->sync_error);
        $this->assertNull($order->bigseller_order_code);

        $this->assertDatabaseHas('bigseller_order_mappings', [
            'order_id' => $order->id,
            'bigseller_order_code' => null,
        ]);
    }

    /**
     * KỊCH BẢN 10: Retry đơn đồng bộ lỗi thành công.
     */
    public function test_failed_order_sync_can_be_retried_successfully(): void
    {
        $order = $this->makeOrder('SKU-RETRY');

        // Lần đầu lỗi.
        $this->mockClient()->simulateErrorOnNextCall('Lỗi mạng tạm thời');

        $first = app(OrderService::class)->pushToBigSeller($order);
        $this->assertFalse($first['success']);
        $this->assertSame(OrderSyncStatus::SyncFailed, $order->fresh()->sync_status);

        // Lần retry thành công.
        $second = app(OrderService::class)->pushToBigSeller($order->fresh());

        $this->assertTrue($second['success']);
        $this->assertNotEmpty($second['bigseller_order_code']);

        $order->refresh();
        $this->assertNotSame(OrderSyncStatus::SyncFailed, $order->sync_status);
        $this->assertNotEmpty($order->bigseller_order_code);
        $this->assertNull($order->sync_error);
    }

    /**
     * KỊCH BẢN 11: Retry không tạo đơn trùng trên BigSeller.
     */
    public function test_retry_does_not_create_duplicate_orders(): void
    {
        $order = $this->makeOrder('SKU-IDEMPOTENT');

        $first = app(BigSellerOrderService::class)->pushOrder($order->fresh(['items']));
        $this->assertFalse($first['duplicate']);
        $bigSellerCode = $first['bigseller_order_code'];
        $this->assertNotEmpty($bigSellerCode);

        // Retry lại nhiều lần.
        for ($i = 0; $i < 3; $i++) {
            $retry = app(BigSellerOrderService::class)->pushOrder($order->fresh(['items']));
            $this->assertTrue($retry['duplicate'], 'Lần retry phải được nhận diện là trùng.');
            $this->assertSame($bigSellerCode, $retry['bigseller_order_code']);
        }

        // Chỉ có DUY NHẤT một mapping với một mã đơn BigSeller.
        $this->assertSame(1, BigSellerOrderMapping::where('order_id', $order->id)->count());
        $this->assertSame(
            1,
            BigSellerOrderMapping::where('bigseller_order_code', $bigSellerCode)->count()
        );

        // Idempotency key không đổi giữa các lần gọi.
        $this->assertSame(
            $order->idempotency_key,
            BigSellerOrderMapping::where('order_id', $order->id)->first()->idempotency_key
        );
    }

    /**
     * KỊCH BẢN 11b: Job retry tự động cũng không tạo đơn trùng.
     */
    public function test_retry_job_does_not_duplicate_synced_orders(): void
    {
        $order = $this->makeOrder('SKU-JOB-IDEMPOTENT');

        app(BigSellerOrderService::class)->pushOrder($order->fresh(['items']));

        $code = $order->fresh()->bigseller_order_code;

        // Chạy job retry (đơn đã SYNCED nên không được gửi lại).
        $job = new RetryBigSellerSyncJob($order->id);
        $job->handle(app(BigSellerOrderService::class));

        $this->assertSame($code, $order->fresh()->bigseller_order_code);
        $this->assertSame(1, BigSellerOrderMapping::where('order_id', $order->id)->count());
    }

    /**
     * KỊCH BẢN 12: Luồng hủy đơn hàng.
     */
    public function test_order_cancellation_flow(): void
    {
        $order = $this->makeOrder('SKU-CANCEL');

        // Chưa đồng bộ -> hủy nội bộ.
        $result = app(OrderService::class)->cancelOrder($order);

        $this->assertTrue($result['success']);
        $order->refresh();
        $this->assertSame(OrderSyncStatus::Cancelled, $order->sync_status);
        $this->assertStringContainsString('chưa gửi lên BigSeller', $result['message']);
    }

    /**
     * KỊCH BẢN 12b: Hủy đơn đã đồng bộ trên BigSeller.
     */
    public function test_cancelling_a_synced_order_calls_bigseller(): void
    {
        $order = $this->makeOrder('SKU-CANCEL-SYNCED');

        app(BigSellerOrderService::class)->pushOrder($order->fresh(['items']));
        $order->refresh();

        $this->assertSame(OrderSyncStatus::Processing, $order->sync_status);

        $result = app(OrderService::class)->cancelOrder($order);

        $this->assertTrue($result['success']);
        $order->refresh();
        $this->assertSame(OrderSyncStatus::Cancelled, $order->sync_status);
    }

    /**
     * KỊCH BẢN 12c: Hủy đơn giải phóng lượng tồn kho website đang giữ.
     */
    public function test_cancelling_order_releases_local_reservation(): void
    {
        $sku = $this->makeSku('SKU-RELEASE', stock: 10, safetyStock: 0);

        $order = Order::create([
            'order_code' => Order::generateOrderCode(),
            'customer_name' => 'Test',
            'customer_phone' => '0911222333',
            'shipping_address' => 'Địa chỉ',
            'payment_method' => PaymentMethod::Cod,
            'sync_status' => OrderSyncStatus::PendingSync,
            'subtotal' => 100000,
            'total_amount' => 100000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'sku_id' => $sku->id,
            'sku' => 'SKU-RELEASE',
            'product_name' => 'SP',
            'quantity' => 3,
            'price' => 100000,
            'subtotal' => 300000,
        ]);

        // Giữ tồn kho cho đơn.
        $sku->forceFill(['local_reserved_stock' => 3])->save();

        app(OrderService::class)->cancelOrder($order->fresh());

        $this->assertSame(0, (int) $sku->fresh()->local_reserved_stock);
    }

    /**
     * KỊCH BẢN 13: Đối chiếu SKU — khớp / chỉ ở website / chỉ ở BigSeller.
     */
    public function test_sku_reconciliation(): void
    {
        $this->makeSku('SKU-MATCHED');
        $this->makeSku('SKU-WEBSITE-ONLY');

        BigSellerProductMapping::create([
            'bigseller_sku' => 'SKU-MATCHED',
            'product_name' => 'SP khớp',
            'sync_state' => SyncState::Synced,
        ]);

        BigSellerProductMapping::create([
            'bigseller_sku' => 'SKU-BIGSELLER-ONLY',
            'product_name' => 'SP chỉ có ở BigSeller',
            'sync_state' => SyncState::Synced,
        ]);

        $result = app(\App\Services\BigSeller\BigSellerProductService::class)->reconcileSkus();

        $this->assertContains('SKU-MATCHED', $result['matched']);
        $this->assertNotContains('SKU-WEBSITE-ONLY', $result['matched']);

        $this->assertContains('SKU-WEBSITE-ONLY', $result['website_only']);
        $this->assertNotContains('SKU-MATCHED', $result['website_only']);

        $this->assertContains('SKU-BIGSELLER-ONLY', $result['bigseller_only']);
        $this->assertNotContains('SKU-MATCHED', $result['bigseller_only']);
    }

    /**
     * KỊCH BẢN 14: Xử lý khi mất kết nối BigSeller.
     */
    public function test_disconnected_bigseller_connection_is_handled(): void
    {
        // Không có connection active.
        $this->makeBigSellerConnection(isActive: false);

        $status = app(\App\Services\BigSellerService::class)->status();

        // Chế độ mock vẫn báo mock, nhưng không có connection => không có capability.
        $this->assertTrue($status['is_mock']);
        $this->assertSame([], $status['capabilities']);

        // Service phải ném lỗi cấu hình rõ ràng, KHÔNG giả lập thành công.
        $this->expectException(\App\Exceptions\BigSellerConfigurationException::class);

        app(\App\Services\BigSeller\BigSellerAuthService::class)->authHeaders();
    }

    /**
     * KỊCH BẢN 15: SKU không tồn tại trên BigSeller được cảnh báo, không tự đặt 0.
     */
    public function test_missing_sku_in_inventory_sync_is_flagged_not_zeroed(): void
    {
        $existing = $this->makeSku('SKU-EXISTS', stock: 42);
        $missing = $this->makeSku('SKU-MISSING-ON-BS', stock: 77);

        $this->mockClient()->setStock('SKU-EXISTS', 42);
        $this->mockClient()->markMissingSku('SKU-MISSING-ON-BS');

        $result = app(InventoryService::class)->syncFromBigSeller();

        $this->assertNull($result['error']);
        $this->assertContains('SKU-MISSING-ON-BS', $result['missing_skus']);

        // SKU tồn tại được cập nhật bình thường.
        $this->assertSame(42, (int) $existing->fresh()->available_stock);
        $this->assertSame(SyncState::Synced, $existing->fresh()->sync_state);

        // SKU không tồn tại: KHÔNG bị đặt về 0, bị đánh dấu lỗi.
        $missingRecord = $missing->fresh();
        $this->assertSame(77, (int) $missingRecord->available_stock);
        $this->assertSame(SyncState::Failed, $missingRecord->sync_state);
        $this->assertStringContainsString('không tồn tại', (string) $missingRecord->sync_error);
    }

    /**
     * Bổ sung: job đồng bộ tồn kho chạy được và cập nhật snapshot.
     */
    public function test_inventory_sync_job_updates_snapshots(): void
    {
        $sku = $this->makeSku('SKU-JOB', stock: 10);
        $this->mockClient()->setStock('SKU-JOB', 77);

        app(SyncBigSellerInventoryJob::class)->handle(app(InventoryService::class));

        $this->assertSame(77, (int) $sku->fresh()->available_stock);
        $this->assertDatabaseHas('inventory_snapshots', ['sku' => 'SKU-JOB', 'available' => 77]);
    }

    /**
     * Bổ sung: đẩy đơn qua job tạo mã BigSeller và mapping.
     */
    public function test_push_order_job_creates_bigseller_mapping(): void
    {
        $order = $this->makeOrder('SKU-PUSH-JOB');

        $job = new PushWebsiteOrderToBigSellerJob($order->id);
        $job->handle(app(BigSellerOrderService::class));

        $order->refresh();

        $this->assertNotEmpty($order->bigseller_order_code);
        $this->assertDatabaseHas('bigseller_order_mappings', [
            'order_id' => $order->id,
            'bigseller_order_code' => $order->bigseller_order_code,
        ]);
        $this->assertDatabaseHas('bigseller_sync_logs', ['operation' => 'create_order']);
    }
}
