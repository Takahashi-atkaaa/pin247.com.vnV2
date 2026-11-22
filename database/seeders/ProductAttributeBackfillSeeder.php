<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Backfill thuộc tính cho các biến thể đã tồn tại.
 *
 * Trước khi có cơ chế thuộc tính động, biến thể chỉ lưu option_1/option_2
 * dạng text. Migration GĐ1 yêu cầu `attribute_signature` để đối chiếu nhanh.
 *
 * Seeder này:
 *   1. Gom các option_1/option_2 đang có thành thuộc tính theo tên.
 *   2. Tạo `product_attributes` + `product_attribute_values`.
 *   3. Gán `attribute_signature` cho từng biến thể.
 *
 * Chạy an toàn nhiều lần (idempotent).
 */
class ProductAttributeBackfillSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()
            ->where('has_variants', true)
            ->with('variants')
            ->chunkById(50, function ($products): void {
                foreach ($products as $product) {
                    $this->backfillProduct($product);
                }
            });
    }

    protected function backfillProduct(Product $product): void
    {
        $variants = $product->variants;

        if ($variants->isEmpty()) {
            return;
        }

        // Gom tên thuộc tính theo vị trí option.
        $names = [];

        foreach ([1, 2, 3] as $position) {
            $name = $variants
                ->map(fn (ProductVariant $v) => $v->{'option_'.$position.'_name'})
                ->filter()
                ->unique()
                ->first();

            if ($name !== null) {
                $names[$position] = $name;
            }
        }

        if ($names === []) {
            return;
        }

        // Tạo thuộc tính + giá trị.
        foreach ($names as $position => $name) {
            $attribute = ProductAttribute::firstOrCreate(
                ['product_id' => $product->id, 'position' => $position],
                ['name' => $name],
            );

            $values = $variants
                ->map(fn (ProductVariant $v) => $v->{'option_'.$position.'_value'})
                ->filter()
                ->unique();

            foreach ($values as $index => $value) {
                $attribute->values()->firstOrCreate(
                    ['value' => $value],
                    ['sort_order' => $index],
                );
            }
        }

        // Gán chữ ký cho từng biến thể.
        foreach ($variants as $variant) {
            $parts = [];

            foreach ([1, 2, 3] as $position) {
                $parts[] = (string) ($variant->{'option_'.$position.'_value'} ?? '');
            }

            $variant->forceFill([
                'attribute_signature' => implode('|', $parts),
            ])->save();
        }
    }
}
