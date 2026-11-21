<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@pin247.vn',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ]);
    }

    protected function makeCustomer(): User
    {
        return User::create([
            'name' => 'Khách Hàng',
            'email' => 'khach@example.com',
            'phone' => '0912345678',
            'password' => Hash::make('oldpassword1'),
            'is_admin' => false,
        ]);
    }

    public function test_customer_can_create_support_request(): void
    {
        $response = $this->postJson('/api/support/requests', [
            'email' => 'khach@example.com',
            'phone' => '0912345678',
            'message' => 'Quên mật khẩu, không nhớ email.',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['public_token']]);

        $this->assertDatabaseCount('support_requests', 1);
        $this->assertDatabaseCount('support_messages', 1);

        $this->assertDatabaseHas('support_requests', [
            'email' => 'khach@example.com',
            'status' => 'open',
        ]);
    }

    public function test_support_request_requires_email_or_phone(): void
    {
        $this->postJson('/api/support/requests', [
            'message' => 'Quên mật khẩu',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('support_requests', 0);
    }

    public function test_customer_can_read_and_reply_own_request(): void
    {
        $created = $this->postJson('/api/support/requests', [
            'email' => 'khach@example.com',
            'message' => 'Quên mật khẩu',
        ]);

        $token = $created->json('data.public_token');

        $this->getJson('/api/support/messages/'.$token)
            ->assertOk()
            ->assertJsonStructure(['data' => ['request' => ['status'], 'messages']]);

        $this->postJson('/api/support/messages/'.$token, [
            'body' => 'Bạn ơi check giúp mình với',
        ])->assertCreated();

        $this->assertDatabaseCount('support_messages', 2);
    }

    public function test_customer_cannot_read_unknown_request(): void
    {
        $this->getJson('/api/support/messages/token-khong-ton-tai')
            ->assertNotFound();
    }

    public function test_public_token_is_not_exposed_in_admin_list(): void
    {
        $admin = $this->makeAdmin();

        $this->postJson('/api/support/requests', [
            'email' => 'khach@example.com',
            'message' => 'Quên mật khẩu',
        ])->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/support/requests');

        $response->assertOk();

        $this->assertArrayNotHasKey('public_token', $response->json('data.0'));
    }

    public function test_admin_can_reset_password_for_customer(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();

        $supportRequest = SupportRequest::create([
            'public_token' => 'tok-123',
            'email' => $customer->email,
            'message' => 'Quên mật khẩu',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support/requests/{$supportRequest->id}/reset-password", [
                'user_id' => $customer->id,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'password']]);

        $plain = $response->json('data.password');

        $this->assertNotEmpty($plain);
        $this->assertTrue(Hash::check($plain, $customer->fresh()->password));

        // Mật khẩu KHÔNG được ghi vào chat.
        $this->assertDatabaseMissing('support_messages', ['body' => $plain]);
    }

    public function test_admin_can_reset_password_with_custom_password(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();

        $supportRequest = SupportRequest::create([
            'public_token' => 'tok-456',
            'email' => $customer->email,
            'message' => 'Quên mật khẩu',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support/requests/{$supportRequest->id}/reset-password", [
                'user_id' => $customer->id,
                'password' => 'matkhau-moi-123',
            ])->assertOk();

        $this->assertTrue(Hash::check('matkhau-moi-123', $customer->fresh()->password));
    }

    public function test_reset_password_revokes_existing_login_tokens(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();

        $customer->createToken('api');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $supportRequest = SupportRequest::create([
            'public_token' => 'tok-789',
            'email' => $customer->email,
            'message' => 'Quên mật khẩu',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support/requests/{$supportRequest->id}/reset-password", [
                'user_id' => $customer->id,
            ])->assertOk();

        // Token của khách bị thu hồi; token của admin (actingAs) không
        // tạo bản ghi Sanctum nên vẫn là 0.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_non_admin_cannot_reset_password(): void
    {
        $customer = $this->makeCustomer();

        $supportRequest = SupportRequest::create([
            'public_token' => 'tok-abc',
            'email' => $customer->email,
            'message' => 'Quên mật khẩu',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/admin/support/requests/{$supportRequest->id}/reset-password", [
                'user_id' => $customer->id,
            ])->assertForbidden();
    }

    public function test_admin_cannot_reset_other_admin_password(): void
    {
        $admin = $this->makeAdmin();

        $otherAdmin = User::create([
            'name' => 'Admin 2',
            'email' => 'admin2@pin247.vn',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        $supportRequest = SupportRequest::create([
            'public_token' => 'tok-def',
            'email' => $otherAdmin->email,
            'message' => 'Quên mật khẩu',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support/requests/{$supportRequest->id}/reset-password", [
                'user_id' => $otherAdmin->id,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_admin_can_lookup_customer_and_reply(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();

        $supportRequest = SupportRequest::create([
            'public_token' => 'tok-ghi',
            'email' => $customer->email,
            'message' => 'Quên mật khẩu',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/support/lookup?email=khach@example.com')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email']]]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support/requests/{$supportRequest->id}/reply", [
                'body' => 'Chào bạn, mình đang xử lý nhé.',
            ])->assertCreated();

        $this->assertDatabaseHas('support_messages', [
            'support_request_id' => $supportRequest->id,
            'sender_type' => 'admin',
        ]);
    }

    public function test_admin_can_resolve_request(): void
    {
        $admin = $this->makeAdmin();

        $supportRequest = SupportRequest::create([
            'public_token' => 'tok-resolve',
            'email' => 'khach@example.com',
            'message' => 'Quên mật khẩu',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support/requests/{$supportRequest->id}/resolve")
            ->assertOk();

        $supportRequest->refresh();

        $this->assertSame('resolved', $supportRequest->status);
        $this->assertNotNull($supportRequest->resolved_at);
    }
}
