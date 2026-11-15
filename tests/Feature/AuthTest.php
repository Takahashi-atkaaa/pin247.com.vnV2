<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Nguyễn Văn C',
            'email' => 'nguyenvanc@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'nguyenvanc@example.com')
            ->assertJsonPath('data.is_admin', false);

        $this->assertDatabaseHas('users', ['email' => 'nguyenvanc@example.com']);
    }

    public function test_user_can_login(): void
    {
        User::create([
            'name' => 'User Test',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'is_admin', 'token']]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'name' => 'User Test',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_route_requires_admin_role(): void
    {
        $user = User::create([
            'name' => 'User Test',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@pin247.vn',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'orders' => ['total', 'new_today', 'pending_sync', 'sync_failed', 'synced'],
                    'catalog' => ['total_products', 'total_variants', 'total_skus'],
                    'inventory' => ['low_stock_threshold', 'low_stock_count'],
                    'revenue' => ['website_total', 'website_this_month'],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_route(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }
}
