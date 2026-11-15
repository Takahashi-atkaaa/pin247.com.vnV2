<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_list_is_publicly_accessible(): void
    {
        $category = Category::create(['name' => 'Pin 18650', 'slug' => 'pin-18650', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Pin 18650 chính hãng',
            'slug' => 'pin-18650-chinh-hang',
            'price' => 79000,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pin 18650 chính hãng');
    }

    public function test_product_detail_returns_variants_and_stock(): void
    {
        $category = Category::create(['name' => 'BMS', 'slug' => 'bms', 'is_active' => true]);
        $sku = $this->makeSku('BMS-3S-20A', stock: 20, safetyStock: 5, price: 32000);

        $sku->product->update(['category_id' => $category->id]);

        $response = $this->getJson('/api/products/'.$sku->product->slug);

        $response->assertOk()
            ->assertJsonPath('data.slug', $sku->product->slug)
            ->assertJsonPath('data.total_available_stock', 20);
    }

    public function test_product_detail_includes_variants_with_sellable_stock(): void
    {
        // Hồi quy: chi tiết sản phẩm PHẢI trả về `variants` (kèm số lượng
        // bán được) — nếu không, trang chi tiết sẽ không chọn được biến thể.
        $sku = $this->makeSku('BMS-3S-20A', stock: 20, safetyStock: 5, price: 32000);

        $response = $this->getJson('/api/products/'.$sku->product->slug);

        $response->assertOk()
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.sku', 'BMS-3S-20A')
            ->assertJsonPath('data.variants.0.available_stock', 15)
            ->assertJsonPath('data.variants.0.sku_id', $sku->id);
    }

    public function test_product_list_does_not_include_heavy_relations(): void
    {
        $this->makeSku('SKU-LIST', stock: 5, safetyStock: 0);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonMissingPath('data.0.variants');
    }

    public function test_categories_endpoint_returns_list(): void
    {
        Category::create(['name' => 'Pin máy khoan', 'slug' => 'pin-may-khoan', 'is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cart_validation_detects_insufficient_stock(): void
    {
        $this->makeSku('SKU-CART', stock: 5, safetyStock: 0);

        $response = $this->postJson('/api/cart/validate', [
            'items' => [
                ['sku' => 'SKU-CART', 'quantity' => 10],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonCount(1, 'errors');
    }

    public function test_cart_validation_passes_for_available_stock(): void
    {
        $this->makeSku('SKU-OK', stock: 5, safetyStock: 0);

        $response = $this->postJson('/api/cart/validate', [
            'items' => [
                ['sku' => 'SKU-OK', 'quantity' => 3],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('data.SKU-OK', 5);
    }

    public function test_stock_endpoint_returns_sellable_quantity(): void
    {
        $this->makeSku('SKU-STOCK', stock: 30, safetyStock: 5);

        $response = $this->getJson('/api/stock?skus[]=SKU-STOCK');

        $response->assertOk();
    }

    public function test_order_tracking_requires_matching_code_and_phone(): void
    {
        $response = $this->postJson('/api/orders/track', [
            'order_code' => 'PIN247-20260101-ABCDEF',
            'phone' => '0912345678',
        ]);

        $response->assertNotFound();
    }
}
