<?php

namespace Tests;

use App\Enums\ProductSource;
use App\Enums\SyncState;
use App\Models\BigSellerConnection;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sku;
use App\Models\Warehouse;
use App\Services\BigSeller\MockBigSellerClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tạo một SKU kèm product + variant + category.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeSku(
        string $skuCode,
        int $stock = 100,
        int $safetyStock = 0,
        float $price = 100000,
        array $attributes = [],
    ): Sku {
        $category = Category::firstOrCreate(
            ['slug' => 'test-cat'],
            ['name' => 'Danh mục test', 'is_active' => true],
        );

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Sản phẩm test '.$skuCode,
            'slug' => 'san-pham-test-'.strtolower($skuCode),
            'price' => $price,
            'cost_price' => $price * 0.7,
            'source' => ProductSource::Manual,
            'has_variants' => true,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Biến thể '.$skuCode,
            'sku' => $skuCode,
            'price' => $price,
            'cost_price' => $price * 0.7,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        return Sku::create(array_merge([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'sku' => $skuCode,
            'name' => 'Biến thể '.$skuCode,
            'source' => ProductSource::Manual,
            'available_stock' => $stock,
            'reserved_stock' => 0,
            'on_hand_stock' => $stock,
            'locked_stock' => 0,
            'shipped_stock' => 0,
            'safety_stock' => $safetyStock,
            'price' => $price,
            'cost_price' => $price * 0.7,
            'sync_state' => SyncState::Synced,
        ], $attributes));
    }

    /**
     * Tạo user admin (đã xác thực) để gọi API admin.
     */
    protected function adminUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create(array_merge([
            'is_admin' => true,
        ], $attributes));
    }

    /**
     * Tạo connection BigSeller ở chế độ mock với đầy đủ capability.
     */
    protected function makeBigSellerConnection(array $capabilities = [
        'inventory',
        'create_order',
        'cancel_order',
        'order_status',
        'products',
        'warehouses',
    ], bool $isActive = true): BigSellerConnection
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'KHO-TEST'],
            ['name' => 'Kho test', 'is_active' => true],
        );

        return BigSellerConnection::updateOrCreate(
            ['name' => 'Test connection'],
            [
                'app_id' => 'TEST-APP',
                'app_key' => 'test-app-key',
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'token_expires_at' => now()->addDay(),
                'capabilities' => $capabilities,
                'shop_code' => 'PIN247',
                'shop_name' => 'PIN247 Test',
                'default_warehouse_id' => $warehouse->id,
                'is_active' => $isActive,
                'is_mock' => true,
            ],
        );
    }

    /**
     * Lấy mock client hiện tại đang được bind trong container.
     */
    protected function mockClient(): MockBigSellerClient
    {
        return app(MockBigSellerClient::class);
    }
}
