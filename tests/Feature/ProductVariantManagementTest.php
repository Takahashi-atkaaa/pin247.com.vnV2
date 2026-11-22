<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kiểm thử cơ chế quản lý sản phẩm clone từ Haravan.
 *
 * GĐ1 — Thuộc tính động & tự sinh biến thể (cartesian product).
 * GĐ2 — Đơn vị tính (UoM) với tỷ lệ quy đổi & giá tự tính.
 * GĐ3 — Lịch sử cập nhật biến thể, nhân bản sản phẩm, thao tác hàng loạt.
 */
class ProductVariantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-variant@pin247.vn',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    // -----------------------------------------------------------------
    // GĐ1 — Thuộc tính động & tự sinh biến thể
    // -----------------------------------------------------------------

    public function test_attributes_generate_variants_via_cartesian_product(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin máy khoan 12V',
                'price' => 249000,
                'attributes' => [
                    ['name' => 'Kiểu đầu', 'values' => ['Makita', 'Bosch', 'Hitachi']],
                    ['name' => 'Dung lượng', 'values' => ['2.0Ah', '5.0Ah']],
                ],
            ]);

        $response->assertCreated();

        $variants = $response->json('data.variants');

        // 3 giá trị × 2 giá trị = 6 biến thể.
        $this->assertCount(6, $variants);

        // Mỗi biến thể có đủ option 1 & 2.
        $this->assertNotNull($variants[0]['option_1_name']);
        $this->assertNotNull($variants[0]['option_1_value']);
        $this->assertNotNull($variants[0]['option_2_name']);
        $this->assertNotNull($variants[0]['option_2_value']);

        // Mỗi biến thể có SKU riêng và không trùng lặp.
        $skus = array_column($variants, 'sku');
        $this->assertCount(6, array_unique($skus));

        // Mỗi biến thể có bản ghi SKU (tồn kho).
        foreach ($variants as $variant) {
            $this->assertDatabaseHas('skus', [
                'sku' => $variant['sku'],
                'product_variant_id' => $variant['id'],
            ]);
        }

        // Thuộc tính được lưu đúng 2 bản ghi.
        $this->assertDatabaseCount('product_attributes', 2);
        $this->assertDatabaseCount('product_attribute_values', 5);
    }

    public function test_attributes_support_third_option(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin 3 thuộc tính',
                'price' => 100000,
                'attributes' => [
                    ['name' => 'Màu', 'values' => ['Đỏ', 'Đen']],
                    ['name' => 'Size', 'values' => ['S', 'M']],
                    ['name' => 'Chất liệu', 'values' => ['Vải', 'Cotton']],
                ],
            ]);

        $response->assertCreated();

        // 2 × 2 × 2 = 8 biến thể.
        $this->assertCount(8, $response->json('data.variants'));
        $this->assertNotNull($response->json('data.variants.0.option_3_name'));
        $this->assertNotNull($response->json('data.variants.0.option_3_value'));
    }

    public function test_attributes_are_limited_to_three(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin 4 thuộc tính',
                'price' => 100000,
                'attributes' => [
                    ['name' => 'A', 'values' => ['1']],
                    ['name' => 'B', 'values' => ['1']],
                    ['name' => 'C', 'values' => ['1']],
                    ['name' => 'D', 'values' => ['1']],
                ],
            ]);

        $response->assertCreated();

        // Chỉ 3 thuộc tính được giữ (giống giới hạn Haravan).
        $this->assertCount(3, $response->json('data.attributes'));
        $this->assertDatabaseCount('product_attributes', 3);
    }

    public function test_regenerating_variants_keeps_existing_and_deactivates_stale(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin 21V',
                'price' => 749000,
                'attributes' => [
                    ['name' => 'Kiểu đầu', 'values' => ['Makita', 'Bosch']],
                    ['name' => 'Dung lượng', 'values' => ['5.0Ah']],
                ],
            ])->json('data');

        $this->assertCount(2, $product['variants']);

        $variantIds = array_column($product['variants'], 'id');

        // Bỏ bớt 1 giá trị → 1 biến thể bị ẩn, 1 biến thể giữ nguyên.
        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'], [
                'attributes' => [
                    ['name' => 'Kiểu đầu', 'values' => ['Makita']],
                    ['name' => 'Dung lượng', 'values' => ['5.0Ah']],
                ],
            ]);

        $update->assertOk();

        $variants = $update->json('data.variants');

        $this->assertCount(2, $variants);

        $active = array_values(array_filter($variants, fn ($v) => $v['is_active']));
        $inactive = array_values(array_filter($variants, fn ($v) => ! $v['is_active']));

        $this->assertCount(1, $active);
        $this->assertCount(1, $inactive);

        // Biến thể giữ nguyên vẫn tồn tại (không bị xoá cứng).
        $this->assertDatabaseHas('product_variants', [
            'id' => $variantIds[0],
            'deleted_at' => null,
        ]);
    }

    public function test_regenerating_variants_without_payload_preserves_manual_price(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin giữ giá',
                'price' => 100000,
                'attributes' => [
                    ['name' => 'Kiểu', 'values' => ['A', 'B']],
                ],
            ])->json('data');

        // Admin chỉnh giá biến thể qua API chi tiết.
        $variantId = $product['variants'][0]['id'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/admin/products/{$product['id']}/variants/{$variantId}", [
                'price' => 555000,
            ])->assertOk();

        // Regenerate thuộc tính mà KHÔNG gửi payload `variants`:
        // giá đã chỉnh phải được giữ nguyên (không reset về giá sản phẩm).
        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/admin/products/{$product['id']}", [
                'attributes' => [
                    ['name' => 'Kiểu', 'values' => ['A', 'B']],
                ],
            ]);

        $update->assertOk();

        $variants = $update->json('data.variants');

        $edited = collect($variants)->firstWhere('id', $variantId);

        $this->assertNotNull($edited);
        $this->assertSame(555000.0, (float) $edited['price']);
    }

    public function test_manual_variant_data_overrides_generated_values(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin 18V',
                'price' => 599000,
                'attributes' => [
                    ['name' => 'Kiểu đầu', 'values' => ['Makita']],
                ],
                'variants' => [
                    [
                        // Dữ liệu nhập tay phải khớp với giá trị thuộc tính
                        // để hệ thống ghép đúng biến thể được sinh ra.
                        'option_1_name' => 'Kiểu đầu',
                        'option_1_value' => 'Makita',
                        'sku' => 'CUSTOM-SKU-18V',
                        'name' => 'Tên riêng của tôi',
                        'price' => 555000,
                    ],
                ],
            ]);

        $response->assertCreated();

        $variants = $response->json('data.variants');

        $this->assertCount(1, $variants);
        $this->assertSame('CUSTOM-SKU-18V', $variants[0]['sku']);
        $this->assertSame('Tên riêng của tôi', $variants[0]['name']);
        $this->assertSame(555000.0, (float) $variants[0]['price']);
    }

    // -----------------------------------------------------------------
    // GĐ2 — Đơn vị tính
    // -----------------------------------------------------------------

    public function test_units_are_created_with_conversion_rate(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Cell 18650',
                'price' => 79000,
                'attributes' => [
                    ['name' => 'Loại', 'values' => ['Panasonic']],
                ],
            ])->json('data');

        $sku = $product['variants'][0]['sku'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$product['variants'][0]['id'].'/units', [
                'units' => [
                    ['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true],
                    ['name' => 'lô', 'conversion_rate' => 20],
                    ['name' => 'hộp', 'conversion_rate' => 100],
                ],
            ]);

        $response->assertOk();

        $units = $response->json('data.units');

        $this->assertCount(3, $units);

        // Đơn vị cơ bản có giá bằng giá biến thể.
        $this->assertTrue($units[0]['is_base']);
        $this->assertSame(79000.0, (float) $units[0]['price']);

        // Đơn vị quy đổi: giá TỰ ĐỘNG = giá cơ bản × tỷ lệ.
        $this->assertSame(1580000.0, (float) $units[1]['price']);
        $this->assertSame(7900000.0, (float) $units[2]['price']);

        $this->assertDatabaseHas('product_units', [
            'product_variant_id' => $product['variants'][0]['id'],
            'name' => 'viên',
            'is_base' => true,
            'conversion_rate' => 1,
        ]);
    }

    public function test_unit_custom_price_overrides_computed_price(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin có giá lô riêng',
                'price' => 79000,
                'attributes' => [['name' => 'Loại', 'values' => ['A']]],
            ])->json('data');

        $variantId = $product['variants'][0]['id'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId.'/units', [
                'units' => [
                    ['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true],
                    ['name' => 'lô', 'conversion_rate' => 20, 'price' => 1500000],
                ],
            ]);

        $response->assertOk();

        $units = $response->json('data.units');

        $this->assertSame(1500000.0, (float) $units[1]['price']);
        $this->assertTrue($units[1]['has_custom_price']);
    }

    public function test_unit_conversion_rate_cannot_be_changed_after_creation(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin tỷ lệ cố định',
                'price' => 100000,
                'attributes' => [['name' => 'Loại', 'values' => ['A']]],
            ])->json('data');

        $variantId = $product['variants'][0]['id'];

        // Tạo đơn vị với rate = 20.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId.'/units', [
                'units' => [
                    ['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true],
                    ['name' => 'lô', 'conversion_rate' => 20],
                ],
            ])->assertOk();

        $lôId = ProductUnit::where('product_variant_id', $variantId)
            ->where('name', 'lô')
            ->value('id');

        // Gửi lại rate = 99 → phải giữ nguyên 20 (quy tắc Haravan).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId.'/units', [
                'units' => [
                    ['id' => $lôId, 'name' => 'lô', 'conversion_rate' => 99],
                ],
            ])->assertOk();

        $this->assertDatabaseHas('product_units', [
            'id' => $lôId,
            'conversion_rate' => 20,
        ]);
    }

    public function test_units_removed_when_absent_from_payload(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin xoá đơn vị',
                'price' => 100000,
                'attributes' => [['name' => 'Loại', 'values' => ['A']]],
            ])->json('data');

        $variantId = $product['variants'][0]['id'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId.'/units', [
                'units' => [
                    ['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true],
                    ['name' => 'lô', 'conversion_rate' => 20],
                ],
            ])->assertOk();

        $this->assertDatabaseCount('product_units', 2);

        // Chỉ giữ lại "viên".
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId.'/units', [
                'units' => [['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true]],
            ])->assertOk();

        $this->assertDatabaseCount('product_units', 1);
        $this->assertDatabaseHas('product_units', ['name' => 'viên']);
    }

    // -----------------------------------------------------------------
    // GĐ3 — Lịch sử, nhân bản, thao tác hàng loạt
    // -----------------------------------------------------------------

    public function test_variant_update_writes_history(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin có lịch sử',
                'price' => 100000,
                'attributes' => [['name' => 'Loại', 'values' => ['A']]],
            ])->json('data');

        $variantId = $product['variants'][0]['id'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId, [
                'price' => 123456,
                'sale_price' => 99999,
            ])->assertOk();

        $this->assertDatabaseCount('product_variant_histories', 1);

        $history = \App\Models\ProductVariantHistory::first();

        $this->assertContains('price', $history->changed_fields);
        $this->assertContains('sale_price', $history->changed_fields);
        $this->assertStringContainsString('Giá bán', $history->summary);

        // Endpoint lịch sử trả về dữ liệu.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId.'/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertNotNull($response->json('data.0.changed_by'));
    }

    public function test_variant_update_syncs_price_to_sku(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin đồng bộ giá SKU',
                'price' => 100000,
                'attributes' => [['name' => 'Loại', 'values' => ['A']]],
            ])->json('data');

        $variant = $product['variants'][0];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variant['id'], [
                'price' => 250000,
            ])->assertOk();

        $this->assertDatabaseHas('skus', [
            'sku' => $variant['sku'],
            'price' => 250000,
        ]);
    }

    public function test_duplicate_product_copies_structure_with_new_skus(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin gốc',
                'price' => 249000,
                'description' => 'Mô tả gốc',
                'is_active' => true,
                'attributes' => [
                    ['name' => 'Kiểu đầu', 'values' => ['Makita', 'Bosch']],
                ],
            ])->json('data');

        $variantId = $product['variants'][0]['id'];

        // Thêm đơn vị tính cho biến thể đầu.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/products/'.$product['id'].'/variants/'.$variantId.'/units', [
                'units' => [
                    ['name' => 'viên', 'conversion_rate' => 1, 'is_base' => true],
                    ['name' => 'lô', 'conversion_rate' => 20],
                ],
            ])->assertOk();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products/'.$product['id'].'/duplicate');

        $response->assertCreated();

        $copy = $response->json('data');

        // Thông tin cơ bản được copy.
        $this->assertStringContainsString('bản sao', $copy['name']);
        $this->assertSame('Mô tả gốc', $copy['description']);
        $this->assertFalse($copy['is_active']);

        // Cấu trúc được copy.
        $this->assertCount(1, $copy['attributes']);
        $this->assertCount(2, $copy['variants']);
        $this->assertCount(2, $copy['variants'][0]['units']);

        // SKU phải khác bản gốc.
        $originalSkus = array_column($product['variants'], 'sku');
        $copySkus = array_column($copy['variants'], 'sku');

        $this->assertEmpty(array_intersect($originalSkus, $copySkus));

        foreach ($copySkus as $sku) {
            $this->assertDatabaseHas('skus', ['sku' => $sku, 'available_stock' => 0]);
        }
    }

    public function test_bulk_update_products(): void
    {
        $token = $this->adminToken();

        $ids = [];

        foreach (['A', 'B', 'C'] as $index => $name) {
            $ids[] = $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/admin/products', [
                    'name' => 'Sản phẩm '.$name,
                    'price' => 10000 * ($index + 1),
                ])->json('data.id');
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products-bulk', [
                'ids' => $ids,
                'action' => 'deactivate',
            ]);

        $response->assertOk();
        $this->assertSame(3, $response->json('data.affected'));

        foreach ($ids as $id) {
            $this->assertDatabaseHas('products', ['id' => $id, 'is_active' => false]);
        }
    }

    public function test_bulk_adjust_variant_price(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin hàng loạt',
                'price' => 100000,
                'attributes' => [
                    ['name' => 'Kiểu', 'values' => ['A', 'B']],
                ],
            ])->json('data');

        $variantIds = array_column($product['variants'], 'id');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/variants-bulk', [
                'variant_ids' => $variantIds,
                'action' => 'adjust_price',
                'percent' => 10,
            ]);

        $response->assertOk();
        $this->assertSame(2, $response->json('data.affected'));

        foreach ($variantIds as $id) {
            $this->assertDatabaseHas('product_variants', ['id' => $id, 'price' => 110000]);
        }

        // Lịch sử được ghi cho cả 2 biến thể.
        $this->assertDatabaseCount('product_variant_histories', 2);
    }

    // -----------------------------------------------------------------
    // Regression — lỗi "0 biến thể" ở danh sách admin
    // -----------------------------------------------------------------

    public function test_admin_product_list_includes_variants_and_count(): void
    {
        $token = $this->adminToken();

        $product = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/products', [
                'name' => 'Pin hiển thị danh sách',
                'price' => 100000,
                'attributes' => [
                    ['name' => 'Kiểu', 'values' => ['A', 'B', 'C']],
                ],
            ])->json('data');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/products');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $product['id']);

        // Không còn hiển thị "0 biến thể".
        $this->assertSame(3, $row['variants_count']);
        $this->assertCount(3, $row['variants']);
        $this->assertTrue($row['has_variants']);
    }

    public function test_guest_cannot_access_admin_product_endpoints(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $this->postJson('/api/admin/products-bulk', ['ids' => [1], 'action' => 'delete'])->assertUnauthorized();
    }
}
