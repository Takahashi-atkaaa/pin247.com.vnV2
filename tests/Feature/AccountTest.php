<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_account_profile_requires_authentication(): void
    {
        $this->getJson('/api/account/profile')->assertUnauthorized();
    }

    public function test_user_can_view_own_profile(): void
    {
        $user = $this->customerUser();

        Customer::create([
            'user_id' => $user->id,
            'name' => 'Nguyễn Văn A',
            'email' => 'khach@example.com',
            'phone' => '0901234567',
            'address' => '123 Nguyễn Huệ',
            'ward' => 'Phường Bến Thành',
            'district' => 'Quận 1',
            'province' => 'TP. Hồ Chí Minh',
            'total_orders' => 3,
            'total_spent' => 1500000,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/profile');

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'khach@example.com')
            ->assertJsonPath('data.user.is_admin', false)
            ->assertJsonPath('data.profile.address', '123 Nguyễn Huệ')
            ->assertJsonPath('data.profile.province', 'TP. Hồ Chí Minh')
            ->assertJsonPath('data.stats.total_orders', 3)
            ->assertJsonPath('data.stats.total_spent', 1500000);
    }

    public function test_profile_falls_back_to_user_data_when_no_customer_record(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.name', 'Nguyễn Văn A')
            ->assertJsonPath('data.profile.has_customer_record', false);
    }

    public function test_user_can_update_profile(): void
    {
        $user = $this->customerUser();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/account/profile', [
            'name' => 'Trần Văn B',
            'email' => 'tranb@example.com',
            'phone' => '0987654321',
            'address' => '456 Lê Lợi',
            'ward' => 'Phường Thạch Thang',
            'district' => 'Quận 5',
            'province' => 'TP. Hồ Chí Minh',
        ]);

        $response->assertOk()->assertJsonPath('data.profile.address', '456 Lê Lợi');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Trần Văn B',
            'email' => 'tranb@example.com',
        ]);

        // Hồ sơ khách hàng được tạo mới.
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'phone' => '0987654321',
            'address' => '456 Lê Lợi',
        ]);
    }

    public function test_user_cannot_use_email_of_another_account(): void
    {
        $this->customerUser(['email' => 'a@example.com']);
        $other = $this->customerUser(['email' => 'b@example.com']);

        $this->actingAs($other, 'sanctum')
            ->putJson('/api/account/profile', [
                'name' => 'B',
                'email' => 'a@example.com',
                'phone' => '0900000000',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/password', [
                'current_password' => 'secret1234',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/password', [
                'current_password' => 'wrong-password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_user_only_sees_own_orders(): void
    {
        $user = $this->customerUser();
        $other = $this->customerUser(['email' => 'other@example.com']);

        $mine = Order::create([
            'order_code' => 'PIN247-20260101-AAAAAA',
            'user_id' => $user->id,
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0901234567',
            'shipping_address' => '123 Nguyễn Huệ',
            'total_amount' => 100000,
        ]);

        Order::create([
            'order_code' => 'PIN247-20260101-BBBBBB',
            'user_id' => $other->id,
            'customer_name' => 'Người Khác',
            'customer_phone' => '0999999999',
            'shipping_address' => '456 Lê Lợi',
            'total_amount' => 200000,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/orders');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_summary_counts_orders_by_status(): void
    {
        $user = $this->customerUser();

        Order::create([
            'order_code' => 'PIN247-20260101-CCCCCC',
            'user_id' => $user->id,
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0901234567',
            'shipping_address' => '123 Nguyễn Huệ',
            'total_amount' => 100000,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/summary');

        $response->assertOk()->assertJsonPath('data.total_orders', 1);
    }

    /**
     * Hai tài khoản dùng chung Số điện thoại (và có thể chung Customer)
     * vẫn KHÔNG nhìn thấy đơn hàng của nhau — lịch sử đơn luôn theo
     * `orders.user_id`.
     */
    public function test_user_does_not_see_orders_of_account_sharing_customer_phone(): void
    {
        $user = $this->customerUser();
        $other = $this->customerUser(['email' => 'other@example.com']);

        $sharedCustomer = Customer::create([
            'user_id' => $user->id,
            'name' => 'Nguyễn Văn A',
            'email' => 'khach@example.com',
            'phone' => '0901234567',
        ]);

        $mine = Order::create([
            'order_code' => 'PIN247-20260101-EEEEEE',
            'user_id' => $user->id,
            'customer_id' => $sharedCustomer->id,
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0901234567',
            'shipping_address' => '123 Nguyễn Huệ',
            'total_amount' => 100000,
        ]);

        // Đơn của tài khoản khác nhưng dùng CHUNG Customer + CHUNG SĐT.
        $theirs = Order::create([
            'order_code' => 'PIN247-20260101-FFFFFF',
            'user_id' => $other->id,
            'customer_id' => $sharedCustomer->id,
            'customer_name' => 'Người Khác',
            'customer_phone' => '0901234567',
            'shipping_address' => '456 Lê Lợi',
            'total_amount' => 200000,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/orders');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mine->id);
        $response->assertJsonMissing(['id' => $theirs->id]);

        // Thống kê cũng phải tách bạch.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/summary')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 1);

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/account/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $theirs->id);
    }

    /**
     * Cập nhật hồ sơ KHÔNG được chiếm hồ sơ của tài khoản khác chỉ vì
     * trùng Số điện thoại — phải bị từ chối bằng lỗi validation.
     */
    public function test_update_profile_does_not_take_over_other_account_customer(): void
    {
        $other = $this->customerUser(['email' => 'other@example.com']);

        $otherCustomer = Customer::create([
            'user_id' => $other->id,
            'name' => 'Người Khác',
            'email' => 'other@example.com',
            'phone' => '0901234567',
        ]);

        $user = $this->customerUser();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/profile', [
                'name' => 'Trần Văn B',
                'email' => 'tranb@example.com',
                'phone' => '0901234567',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone')
            ->assertJsonFragment(['Số điện thoại đã được liên kết với tài khoản khác.']);

        $otherCustomer->refresh();

        $this->assertSame($other->id, $otherCustomer->user_id);
        $this->assertSame('Người Khác', $otherCustomer->name);
    }
}
