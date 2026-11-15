<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kiểm thử cho toàn bộ API trong khu vực quản trị.
 *
 * Mục đích: bảo đảm các endpoint admin thực sự trả về JSON hợp lệ
 * (không phải trang HTML của SPA) và không sinh lỗi SQL khi dùng
 * filter theo ngưỡng tồn kho lấy từ cấu hình.
 */
class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-test@pin247.vn',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_admin_endpoints_return_json(): void
    {
        $token = $this->adminToken();

        $endpoints = [
            '/api/admin/dashboard',
            '/api/admin/inventory',
            '/api/admin/orders',
            '/api/admin/products',
            '/api/admin/customers',
            '/api/admin/categories',
            '/api/admin/bigseller/status',
            '/api/admin/bigseller/logs',
            '/api/admin/bigseller/sku-mappings',
            '/api/admin/bigseller/schedule',
            '/api/admin/bigseller/reconcile-skus',
            '/api/admin/inventory/warehouses',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson($endpoint);

            $response->assertOk();
            $response->assertJsonStructure(['data']);
        }
    }

    public function test_dashboard_low_stock_filter_uses_configured_threshold(): void
    {
        $token = $this->adminToken();

        // Ngưỡng cấu hình không phải 5 (tránh rơi vào case mặc định).
        \App\Models\Setting::set('inventory.low_stock_threshold', 3);

        $product = Product::create([
            'name' => 'Pin test',
            'slug' => 'pin-test-threshold',
            'price' => 10000,
            'source' => 'MANUAL',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Mặc định',
            'sku' => 'TEST-THRESHOLD',
            'price' => 10000,
        ]);

        // available_stock = 4 <= ngưỡng 3? -> không. available_stock = 2 -> có.
        Sku::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'sku' => 'TEST-THRESHOLD',
            'name' => 'Mặc định',
            'price' => 10000,
            'available_stock' => 2,
            'reserved_stock' => 0,
            'on_hand_stock' => 2,
            'locked_stock' => 0,
            'shipped_stock' => 0,
            'local_reserved_stock' => 0,
            'safety_stock' => 0,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.inventory.low_stock_threshold', 3)
            ->assertJsonPath('data.inventory.low_stock_count', 1)
            ->assertJsonPath('data.inventory.out_of_stock_count', 0);
    }

    public function test_inventory_low_stock_filter_accepts_threshold(): void
    {
        $token = $this->adminToken();

        \App\Models\Setting::set('inventory.low_stock_threshold', 3);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/inventory?low_stock=1');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_customers_endpoint_returns_pagination_payload(): void
    {
        $token = $this->adminToken();

        Customer::create([
            'name' => 'Khách Test',
            'email' => 'khachtest@example.com',
            'phone' => '0900000000',
            'total_orders' => 0,
            'total_spent' => 0,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/customers');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(1, 'data');
    }
}
