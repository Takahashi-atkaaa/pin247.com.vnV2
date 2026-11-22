<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kiểm thử TÍCH HỢP luồng quản lý sản phẩm clone Haravan.
 *
 * Mô phỏng đúng thao tác của admin trên UI:
 *   1. Tạo sản phẩm với thuộc tính → biến thể tự sinh.
 *   2. Sửa giá biến thể → lịch sử ghi nhận.
 *   3. Thêm đơn vị tính → giá tự tính.
 *   4. Nhân bản → SKU mới.
 *   5. Storefront hiển thị đầy đủ option.
 */
class ProductVariantWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-workflow@pin247.vn',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_full_workflow_from_admin_ui_to_storefront(): void
    {
        $token = $this->adminToken();

        // === BƯỚC 1: Tạo sản phẩm với 2 thuộc tính ===
        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin máy khoan không dây 21V',
                'brand' => 'OEM',
                'price' => 749000,
                'cost_price' => 500000,
                'description' => 'Pin chất lượng cao',
                'is_active' => true,
                'attributes' => [
                    ['name' => 'Kiểu đầu', 'values' => ['Makita', 'Bosch']],
                    ['name' => 'Dung lượng', 'values' => ['5.0Ah', '7.0Ah']],
                ],
            ]);

        $create->assertCreated();

        $product = $create->json('data');
        $productId = $product['id'];

        // 2 × 2 = 4 biến thể.
        $this->assertCount(4, $product['variants']);
        $this->assertCount(2, $product['attributes']);

        // Biến thể đầu tiên.
        $variant = $product['variants'][0];
        $variantId = $variant['id'];

        // Mỗi biến thể có SKU riêng + bản ghi tồn kho.
        $this->assertDatabaseCount('skus', 4);

        // === BƯỚC 2: Chỉnh giá biến thể ===
        $updateVariant = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/admin/products/{$productId}/variants/{$variantId}", [
                'price' => 799000,
                'sale_price' => 749000,
            ]);

        $updateVariant->assertOk();

        // Lịch sử được ghi.
        $this->assertDatabaseCount('product_variant_histories', 1);

        $history = $updateVariant->json('data.histories.0');
        $this->assertNotNull($history);
        $this->assertStringContainsString('Giá bán', $history['summary']);

        // Giá được đồng bộ xuống SKU (nguồn chuẩn tồn kho).
        // SKU lưu giá HIỆU LỰC = sale_price ?? price = 749.000đ.
        $this->assertDatabaseHas('skus', [
            'sku' => $variant['sku'],
            'price' => 749000,
        ]);

        // === BƯỚC 3: Thêm đơn vị tính ===
        $units = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/admin/products/{$productId}/variants/{$variantId}/units", [
                'units' => [
                    ['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true],
                    ['name' => 'hộp', 'conversion_rate' => 10],
                ],
            ]);

        $units->assertOk();

        $unitList = $units->json('data.units');
        $this->assertCount(2, $unitList);

        // Giá đơn vị quy đổi tự tính theo giá HIỆU LỰC của biến thể.
        // Biến thể có sale_price = 749.000 nên giá hiệu lực = 749.000.
        $this->assertSame(749000.0, (float) $unitList[0]['price']);
        $this->assertSame(7490000.0, (float) $unitList[1]['price']);

        // === BƯỚC 4: Nhân bản ===
        $duplicate = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/admin/products/{$productId}/duplicate");

        $duplicate->assertCreated();

        $copy = $duplicate->json('data');

        $this->assertNotSame($productId, $copy['id']);
        $this->assertCount(4, $copy['variants']);
        $this->assertCount(2, $copy['attributes']);

        // SKU bản sao khác bản gốc.
        $originalSkus = array_column($product['variants'], 'sku');
        $copySkus = array_column($copy['variants'], 'sku');

        $this->assertEmpty(array_intersect($originalSkus, $copySkus));

        // Bản sao có đơn vị tính.
        $this->assertCount(2, $copy['variants'][0]['units']);

        // === BƯỚC 5: Storefront hiển thị ===
        $storefront = $this->getJson("/api/products/pin-may-khoan-khong-day-21v");
        $storefront->assertOk();

        $data = $storefront->json('data');

        $this->assertCount(4, $data['variants']);

        // Mỗi biến thể có đủ option 1 & 2 (không bị bỏ qua như trước).
        foreach ($data['variants'] as $v) {
            $this->assertNotNull($v['option_1_name']);
            $this->assertNotNull($v['option_1_value']);
            $this->assertNotNull($v['option_2_name']);
            $this->assertNotNull($v['option_2_value']);
        }

        // Đơn vị tính xuất hiện ở storefront (đơn vị cơ bản + quy đổi).
        $this->assertNotEmpty($data['variants'][0]['units']);
    }

    public function test_admin_list_shows_variant_count_after_backfill(): void
    {
        $token = $this->adminToken();

        // Sản phẩm cũ (tạo trực tiếp, không qua API — mô phỏng dữ liệu có sẵn).
        $product = Product::create([
            'name' => 'Pin cũ có biến thể',
            'price' => 100000,
            'has_variants' => true,
            'is_active' => true,
        ]);

        foreach (['Makita', 'Bosch'] as $index => $brand) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'name' => "2.0Ah — Kiểu {$brand}",
                'sku' => "OLD-SKU-{$brand}",
                'option_1_name' => 'Kiểu đầu',
                'option_1_value' => $brand,
                'option_2_name' => 'Dung lượng',
                'option_2_value' => '2.0Ah',
                'price' => 100000,
                'is_active' => true,
            ]);

            Sku::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'sku' => "OLD-SKU-{$brand}",
                'name' => $variant->name,
                'available_stock' => 50,
                'price' => 100000,
            ]);
        }

        // Danh sách admin phải hiện đúng số biến thể (không phải 0).
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/products');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $product->id);

        $this->assertSame(2, $row['variants_count']);
        $this->assertCount(2, $row['variants']);

        // Tổng tồn = tổng của các SKU thuộc sản phẩm (2 biến thể × 50).
        $this->assertSame(100, $row['total_available_stock']);
    }

    public function test_deactivating_variant_hides_it_from_storefront(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin ẩn biến thể',
                'price' => 100000,
                'attributes' => [
                    ['name' => 'Kiểu', 'values' => ['A', 'B']],
                ],
            ])->json('data');

        // Tắt 1 biến thể.
        $variantId = $product['variants'][0]['id'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/admin/products/{$product['id']}/variants/{$variantId}", [
                'is_active' => false,
            ])->assertOk();

        // Biến thể bị ẩn không xuất hiện ở storefront (chỉ còn biến thể active).
        $storefront = $this->getJson('/api/products/pin-an-bien-the');
        $storefront->assertOk();

        $variants = $storefront->json('data.variants');

        $this->assertCount(1, $variants);
        $this->assertTrue($variants[0]['is_active']);

        // Biến thể bị ẩn vẫn tồn tại trong DB (chỉ ẩn, không xoá).
        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    public function test_units_are_visible_to_storefront_customers(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin có đơn vị bán',
                'price' => 79000,
                'attributes' => [
                    ['name' => 'Loại', 'values' => ['Panasonic']],
                ],
            ])->json('data');

        $variantId = $product['variants'][0]['id'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/admin/products/{$product['id']}/variants/{$variantId}/units", [
                'units' => [
                    ['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true, 'is_sellable' => true],
                    ['name' => 'lô 20', 'conversion_rate' => 20, 'is_sellable' => true],
                    ['name' => 'lô nội bộ', 'conversion_rate' => 20, 'is_sellable' => false],
                ],
            ])->assertOk();

        $storefront = $this->getJson('/api/products/pin-co-don-vi-ban');
        $storefront->assertOk();

        $units = $storefront->json('data.variants.0.units');

        $this->assertCount(3, $units);

        $base = collect($units)->firstWhere('is_base', true);
        $this->assertSame('viên', $base['name']);
        $this->assertSame(79000.0, (float) $base['price']);

        $lot = collect($units)->firstWhere('name', 'lô 20');
        $this->assertSame(1580000.0, (float) $lot['price']);
        $this->assertTrue($lot['is_sellable']);

        // Đơn vị không được bán vẫn xuất hiện (admin chỉ đánh dấu, không cho bán).
        $internal = collect($units)->firstWhere('name', 'lô nội bộ');
        $this->assertFalse($internal['is_sellable']);
    }

    public function test_soft_delete_product_keeps_variants_hidden(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin sắp xoá',
                'price' => 100000,
                'attributes' => [
                    ['name' => 'Kiểu', 'values' => ['A']],
                ],
            ])->json('data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/admin/products/{$product['id']}")
            ->assertOk();

        // Sản phẩm bị soft-delete → không còn trong danh sách admin.
        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/products');

        $this->assertNull(
            collect($list->json('data'))->firstWhere('id', $product['id'])
        );

        // Biến thể cũng bị soft-delete.
        $this->assertSoftDeleted('product_variants', ['product_id' => $product['id']]);
    }
}
