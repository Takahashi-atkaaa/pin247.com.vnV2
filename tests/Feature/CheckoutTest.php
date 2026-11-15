<?php

namespace Tests\Feature;

use App\Enums\OrderSyncStatus;
use App\Enums\PaymentMethod;
use App\Jobs\PushWebsiteOrderToBigSellerJob;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kịch bản 1-4: Luồng đặt hàng trên website.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bigseller.mode' => 'mock']);
        $this->makeBigSellerConnection();
    }

    /**
     * Payload thanh toán hợp lệ.
     *
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @return array<string, mixed>
     */
    protected function checkoutPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'items' => $items,
            'name' => 'Nguyễn Văn A',
            'phone' => '0912345678',
            'email' => 'a@example.com',
            'address' => 'Số 1 Đường Láng',
            'ward' => 'Phường Thanh Xuân Trung',
            'district' => 'Đống Đa',
            'province' => 'Hà Nội',
            'payment_method' => PaymentMethod::Cod->value,
            'shipping_fee' => 30000,
        ], $overrides);
    }

    /**
     * KỊCH BẢN 1: Đặt hàng thành công.
     */
    public function test_place_order_successfully(): void
    {
        Queue::fake();

        $sku = $this->makeSku('SKU-OK', stock: 50, safetyStock: 5, price: 100000);
        $this->mockClient()->setStock('SKU-OK', 50);

        $response = $this->postJson('/api/checkout', $this->checkoutPayload([
            ['sku' => 'SKU-OK', 'quantity' => 3],
        ]));

        $response->assertCreated()
            ->assertJsonPath('message', 'Đặt hàng thành công.')
            ->assertJsonPath('data.sync_status', OrderSyncStatus::PendingSync->value)
            ->assertJsonPath('data.total_amount', 330000);

        $this->assertDatabaseHas('orders', ['customer_phone' => '0912345678']);
        $this->assertDatabaseHas('order_items', [
            'sku' => 'SKU-OK',
            'quantity' => 3,
            'price' => 100000,
            'subtotal' => 300000,
        ]);

        // Job đẩy đơn lên BigSeller phải được dispatch.
        Queue::assertPushed(PushWebsiteOrderToBigSellerJob::class);

        $order = Order::first();
        $this->assertNotEmpty($order->idempotency_key);
    }

    /**
     * KỊCH BẢN 2: Đơn hàng chứa SKU không tồn tại bị từ chối.
     */
    public function test_order_with_non_existent_sku_is_rejected(): void
    {
        $this->makeSku('SKU-EXIST', stock: 10);

        $response = $this->postJson('/api/checkout', $this->checkoutPayload([
            ['sku' => 'SKU-KHONG-CO', 'quantity' => 1],
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['SKU không tồn tại: SKU-KHONG-CO']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    /**
     * KỊCH BẢN 3: Đơn hàng vượt quá tồn kho khả dụng bị từ chối.
     */
    public function test_order_exceeding_available_stock_is_rejected(): void
    {
        // Tồn khả dụng = available - safety = 10 - 3 = 7
        $this->makeSku('SKU-IT', stock: 10, safetyStock: 3);

        $response = $this->postJson('/api/checkout', $this->checkoutPayload([
            ['sku' => 'SKU-IT', 'quantity' => 8],
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['Sản phẩm SKU-IT chỉ còn 7 (yêu cầu 8).']);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * KỊCH BẢN 4: Nhiều request đặt hàng cùng lúc không làm vượt tồn kho.
     *
     * Mô phỏng race condition: gọi service 3 lần với cùng SKU, mỗi lần
     * đều vượt ngưỡng còn lại -> chỉ những lần thực sự còn hàng mới tạo
     * được đơn, và tổng số lượng bán không vượt quá tồn kho.
     */
    public function test_concurrent_order_placements_do_not_oversell(): void
    {
        // Tồn khả dụng = 10 - 0 = 10
        $this->makeSku('SKU-RACE', stock: 10, safetyStock: 0);
        $this->mockClient()->setStock('SKU-RACE', 10);

        $created = 0;
        $rejected = 0;

        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-RACE', 'quantity' => 4],
            ]));

            if ($response->status() === 201) {
                $created++;
            } else {
                $rejected++;
                $response->assertStatus(422);
            }
        }

        // Tồn khả dụng 10, mỗi đơn 4 -> tối đa 2 đơn được tạo.
        $this->assertSame(2, $created, 'Chỉ được tạo 2 đơn (4+4 <= 10).');
        $this->assertSame(1, $rejected, 'Đơn thứ 3 phải bị từ chối vì hết hàng.');

        $totalSold = OrderItem::sum('quantity');
        $this->assertLessThanOrEqual(10, $totalSold, 'Tổng số lượng bán không được vượt tồn kho.');
    }
}
