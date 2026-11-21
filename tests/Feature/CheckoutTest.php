<?php

namespace Tests\Feature;

use App\Enums\OrderSyncStatus;
use App\Enums\PaymentMethod;
use App\Jobs\PushWebsiteOrderToBigSellerJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kịch bản 1-4: Luồng đặt hàng trên website.
 *
 * Lưu ý: từ 2026-11-21, `/api/checkout` BẮT BUỘC đăng nhập
 * (middleware `auth:sanctum`). Guest không được tạo đơn hàng.
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
     * Tạo tài khoản khách hàng cho test.
     */
    protected function customerUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Nguyễn Văn A',
            'email' => 'khach@example.com',
            'phone' => '0901234567',
            'password' => bcrypt('secret1234'),
            'is_admin' => false,
        ], $attributes));
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
            'phone' => '0900999888',
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
     * KỊCH BẢN 1: Đặt hàng thành công (đã đăng nhập).
     */
    public function test_place_order_successfully(): void
    {
        Queue::fake();

        $sku = $this->makeSku('SKU-OK', stock: 50, safetyStock: 5, price: 100000);
        $this->mockClient()->setStock('SKU-OK', 50);

        $user = $this->customerUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', $this->checkoutPayload([
            ['sku' => 'SKU-OK', 'quantity' => 3],
        ]));

        $response->assertCreated()
            ->assertJsonPath('message', 'Đặt hàng thành công.')
            ->assertJsonPath('data.sync_status', OrderSyncStatus::PendingSync->value)
            ->assertJsonPath('data.total_amount', 330000);

        $this->assertDatabaseHas('orders', [
            'customer_phone' => '0900999888',
            'user_id' => $user->id,
        ]);
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
     * Guest KHÔNG được đặt hàng.
     */
    public function test_guest_cannot_checkout(): void
    {
        $this->makeSku('SKU-GUEST', stock: 10);
        $this->mockClient()->setStock('SKU-GUEST', 10);

        $response = $this->postJson('/api/checkout', $this->checkoutPayload([
            ['sku' => 'SKU-GUEST', 'quantity' => 1],
        ]));

        $response->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    /**
     * `orders.user_id` phải lấy từ tài khoản đã xác thực.
     */
    public function test_order_is_attached_to_authenticated_user(): void
    {
        $this->makeSku('SKU-UID', stock: 10);
        $this->mockClient()->setStock('SKU-UID', 10);

        $user = $this->customerUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-UID', 'quantity' => 1],
            ]))
            ->assertCreated();

        $order = Order::first();

        $this->assertNotNull($order);
        $this->assertSame($user->id, $order->user_id);
    }

    /**
     * Không tin tưởng `user_id` / `customer_id` từ payload của frontend.
     */
    public function test_checkout_rejects_client_supplied_user_id(): void
    {
        $this->makeSku('SKU-FAKE', stock: 10);
        $this->mockClient()->setStock('SKU-FAKE', 10);

        $user = $this->customerUser();
        $other = $this->customerUser([
            'email' => 'other@example.com',
            'phone' => '0900000001',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', $this->checkoutPayload([
            ['sku' => 'SKU-FAKE', 'quantity' => 1],
        ], [
            'user_id' => $other->id,
            'customer_id' => $other->id,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'customer_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * `orders.customer_id` phải trỏ tới hồ sơ của đúng tài khoản đang đặt hàng.
     */
    public function test_order_customer_belongs_to_authenticated_user(): void
    {
        $this->makeSku('SKU-CUST', stock: 10);
        $this->mockClient()->setStock('SKU-CUST', 10);

        $user = $this->customerUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-CUST', 'quantity' => 1],
            ]))
            ->assertCreated();

        $order = Order::first();
        $customer = Customer::find($order->customer_id);

        $this->assertNotNull($customer);
        $this->assertSame($user->id, $customer->user_id);
        $this->assertSame($user->id, $order->user_id);
    }

    /**
     * Hai tài khoản khác nhau KHÔNG được dùng chung hồ sơ khách hàng,
     * kể cả khi có cùng Số điện thoại.
     */
    public function test_two_users_with_same_phone_do_not_share_customer(): void
    {
        $this->makeSku('SKU-SAME', stock: 20);
        $this->mockClient()->setStock('SKU-SAME', 20);

        $userA = $this->customerUser([
            'email' => 'a@example.com',
            'phone' => '0900777888',
        ]);

        $userB = $this->customerUser([
            'email' => 'b@example.com',
            'phone' => '0900777888',
        ]);

        // Tài khoản A đặt hàng trước -> tạo hồ sơ.
        $this->actingAs($userA, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-SAME', 'quantity' => 1],
            ], ['phone' => '0900777888']))
            ->assertCreated();

        // Tài khoản B đặt hàng với CÙNG SĐT => phải bị chặn vì SĐT đã
        // thuộc về hồ sơ của tài khoản A.
        $this->actingAs($userB, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-SAME', 'quantity' => 1],
            ], ['phone' => '0900777888']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Số điện thoại đã được liên kết với tài khoản khác. '
                .'Vui lòng dùng số điện thoại thuộc tài khoản của bạn hoặc liên hệ hỗ trợ.')
            ->assertJsonPath('errors.phone', '0900777888');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);

        // Hồ sơ của tài khoản A KHÔNG bị đổi `user_id`.
        $customer = Customer::first();
        $this->assertSame($userA->id, $customer->user_id);
    }

    /**
     * Hồ sơ vãng lai (`user_id IS NULL`) cùng SĐT được tái sử dụng và hợp
     * nhất vào tài khoản đang đăng nhập.
     */
    public function test_guest_customer_with_same_phone_is_reused_and_linked(): void
    {
        $this->makeSku('SKU-GUESTC', stock: 10);
        $this->mockClient()->setStock('SKU-GUESTC', 10);

        $guestCustomer = Customer::create([
            'user_id' => null,
            'name' => 'Khách Vãng Lai',
            'email' => 'guest@example.com',
            'phone' => '0900555444',
        ]);

        $user = $this->customerUser(['phone' => '0900555444']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-GUESTC', 'quantity' => 1],
            ], ['phone' => '0900555444']))
            ->assertCreated();

        // Không tạo hồ sơ mới — tái sử dụng hồ sơ cũ.
        $this->assertDatabaseCount('customers', 1);

        $customer = Customer::first();
        $this->assertSame($guestCustomer->id, $customer->id);
        $this->assertSame($user->id, $customer->user_id);
    }

    /**
     * Checkout KHÔNG được ghi đè hay cập nhật hồ sơ của tài khoản khác.
     */
    public function test_checkout_does_not_touch_customer_of_other_account(): void
    {
        $this->makeSku('SKU-OTHER', stock: 10);
        $this->mockClient()->setStock('SKU-OTHER', 10);

        $other = $this->customerUser([
            'email' => 'other@example.com',
            'phone' => '0900333222',
        ]);

        $otherCustomer = Customer::create([
            'user_id' => $other->id,
            'name' => 'Người Khác',
            'email' => 'other@example.com',
            'phone' => '0900333222',
            'address' => 'Địa chỉ gốc',
        ]);

        $user = $this->customerUser([
            'email' => 'me@example.com',
            'phone' => '0900111222',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-OTHER', 'quantity' => 1],
            ], ['phone' => '0900111222']))
            ->assertCreated();

        $otherCustomer->refresh();

        $this->assertSame($other->id, $otherCustomer->user_id);
        $this->assertSame('Người Khác', $otherCustomer->name);
        $this->assertSame('0900333222', $otherCustomer->phone);
        $this->assertSame('Địa chỉ gốc', $otherCustomer->address);
    }

    /**
     * Tài khoản chưa có SĐT trong `users` vẫn checkout được nếu cung cấp
     * SĐT hợp lệ ở bước thanh toán.
     */
    public function test_user_without_phone_can_checkout_with_valid_phone(): void
    {
        $this->makeSku('SKU-NOPHONE', stock: 10);
        $this->mockClient()->setStock('SKU-NOPHONE', 10);

        $user = $this->customerUser(['phone' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-NOPHONE', 'quantity' => 1],
            ], ['phone' => '0900444333']))
            ->assertCreated();

        $order = Order::first();

        $this->assertSame('0900444333', $order->customer_phone);
        $this->assertSame($user->id, $order->user_id);

        // `users.phone` KHÔNG bị tự động ghi đè.
        $this->assertNull($user->fresh()->phone);
    }

    /**
     * Validation SĐT được xử lý ở backend.
     */
    public function test_checkout_validates_phone(): void
    {
        $this->makeSku('SKU-VALID', stock: 10);
        $this->mockClient()->setStock('SKU-VALID', 10);

        $user = $this->customerUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-VALID', 'quantity' => 1],
            ], ['phone' => 'số điện thoại không hợp lệ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkout', $this->checkoutPayload([
                ['sku' => 'SKU-VALID', 'quantity' => 1],
            ], ['phone' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * KỊCH BẢN 2: Đơn hàng chứa SKU không tồn tại bị từ chối.
     */
    public function test_order_with_non_existent_sku_is_rejected(): void
    {
        $this->makeSku('SKU-EXIST', stock: 10);

        $user = $this->customerUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', $this->checkoutPayload([
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

        $user = $this->customerUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', $this->checkoutPayload([
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

        $user = $this->customerUser();

        $created = 0;
        $rejected = 0;

        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', $this->checkoutPayload([
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
