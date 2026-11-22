<?php

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill thuộc tính & chữ ký cho biến thể đã tồn tại.
     *
     * GĐ1 yêu cầu `product_attributes` + `attribute_signature` để tự sinh biến
     * thể. Dữ liệu cũ chỉ có option_1/option_2 dạng text — migration này gom
     * chúng thành thuộc tính để UI quản lý có thể dựng lại form.
     *
     * Chạy an toàn nhiều lần (idempotent).
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_attributes')) {
            return;
        }

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

    public function down(): void
    {
        // Không hoàn tác: backfill là dữ liệu, không phải cấu trúc.
    }
};
