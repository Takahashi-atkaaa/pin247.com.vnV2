<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quản lý thuộc tính sản phẩm & tự sinh biến thể — clone cơ chế Haravan.
 *
 * Theo Haravan:
 *   - Sản phẩm có tối đa 3 thuộc tính.
 *   - Biến thể = tích hợp (cartesian product) các giá trị của các thuộc tính.
 *   - Ví dụ: "Kiểu đầu" (Makita/Bosch) × "Dung lượng" (2.0Ah/5.0Ah)
 *     → 4 biến thể.
 *
 * Nguyên tắc quan trọng: khi đồng bộ lại thuộc tính, các biến thể CŨ vẫn
 * tồn tại (không bị xoá) vì chúng có thể đã có đơn hàng, tồn kho, lịch sử.
 * Biến thể mới được sinh ra; biến thể không còn khớp thuộc tính được đánh
 * dấu `is_active = false` (ẩn khỏi website) thay vì bị xoá cứng.
 */
class ProductAttributeService
{
    /**
     * Số biến thể tối đa cho một sản phẩm (giống Haravan: 500).
     */
    public const MAX_VARIANTS = 500;

    /**
     * Số giá trị tối đa cho một thuộc tính (giống Haravan: 200).
     */
    public const MAX_VALUES = 200;

    /**
     * Đồng bộ thuộc tính của sản phẩm.
     *
     * @param  array<int, array{name: string, values: array<int, string>}>  $attributes
     */
    public function syncAttributes(Product $product, array $attributes): void
    {
        DB::transaction(function () use ($product, $attributes): void {
            $product->attributes()->delete();

            $position = 0;

            foreach (array_slice($attributes, 0, ProductAttribute::MAX_ATTRIBUTES) as $attribute) {
                $name = trim((string) ($attribute['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $position++;

                $model = ProductAttribute::create([
                    'product_id' => $product->id,
                    'name' => $name,
                    'position' => $position,
                ]);

                $values = collect($attribute['values'] ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->take(self::MAX_VALUES)
                    ->values();

                foreach ($values as $index => $value) {
                    $model->values()->create([
                        'value' => $value,
                        'sort_order' => $index,
                    ]);
                }
            }
        });
    }

    /**
     * Sinh lại biến thể từ thuộc tính hiện tại của sản phẩm.
     *
     * - Biến thể chưa có => tạo mới (SKU tự sinh nếu admin chưa đặt).
     * - Biến thể đã có => cập nhật option + giá mặc định nếu chưa có.
     * - Biến thể không còn khớp => `is_active = false` (ẩn, không xoá).
     *
     * @param  array<int, array<string, mixed>>  $manualVariants
     *         Dữ liệu biến thể do admin nhập tay (giá, SKU, ảnh...) để ghi đè
     *         giá trị mặc định của biến thể được sinh ra.
     * @return array{created: int, updated: int, deactivated: int}
     */
    public function regenerateVariants(Product $product, array $manualVariants = []): array
    {
        $attributes = $product->attributes()
            ->with('values')
            ->get()
            ->filter(fn (ProductAttribute $attribute) => $attribute->values->isNotEmpty())
            ->sortBy('position')
            ->values();

        // Chuẩn hoá dữ liệu nhập tay.
        //
        // Admin có thể gửi kèm biến thể để chỉnh giá/SKU/tên. Dữ liệu này
        // được ghép với biến thể được sinh ra theo 2 cách:
        //   1. Theo `option_*_value` — tạo chữ ký để đối chiếu (cách chính,
        //      hoạt động ngay cả khi biến thể mới chưa có trong DB).
        //   2. Theo `sku` — dùng khi biến thể đã tồn tại từ trước.
        $manualBySignature = [];
        $manualBySku = [];

        foreach ($manualVariants as $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));

            if ($sku !== '') {
                $manualBySku[$sku] = $variant;
            }

            $signature = $this->signatureFromManual($variant);

            if ($signature !== null) {
                $manualBySignature[$signature] = $variant;
            }
        }

        $created = 0;
        $updated = 0;
        $deactivated = 0;

        DB::transaction(function () use (
            $product,
            $attributes,
            $manualBySku,
            $manualBySignature,
            &$created,
            &$updated,
            &$deactivated
        ): void {
            $combinations = $this->combinations($attributes);

            // Bảo đảm không vượt giới hạn biến thể.
            $combinations = array_slice($combinations, 0, self::MAX_VARIANTS);

            $keptIds = [];

            foreach ($combinations as $index => $combination) {
                $signature = $this->signature($combination);

                // Tìm biến thể hiện có theo chữ ký thuộc tính.
                $variant = $product->variants()
                    ->where('attribute_signature', $signature)
                    ->first();

                if ($variant === null) {
                    $variant = new ProductVariant([
                        'product_id' => $product->id,
                        'sku' => $this->generateSku($product, $combination, $manualBySku),
                    ]);
                    $created++;
                } else {
                    $updated++;
                }

                $optionNames = $attributes->pluck('name')->values()->all();
                $optionValues = $combination;

                $variant->fill([
                    'product_id' => $product->id,
                    'name' => $this->buildVariantName($optionNames, $optionValues, $product),
                    'option_1_name' => $optionNames[0] ?? null,
                    'option_1_value' => $optionValues[0] ?? null,
                    'option_2_name' => $optionNames[1] ?? null,
                    'option_2_value' => $optionValues[1] ?? null,
                    'option_3_name' => $optionNames[2] ?? null,
                    'option_3_value' => $optionValues[2] ?? null,
                    'attribute_signature' => $signature,
                    'sort_order' => $index,
                    // Giá mặc định = giá sản phẩm (admin có thể sửa riêng).
                    'price' => $product->price,
                    'is_active' => true,
                ]);

                // Áp dụng dữ liệu admin nhập tay (giá/SKU/ảnh...) nếu có.
                //
                // Ưu tiên khớp theo chữ ký option (độc lập SKU), sau đó mới
                // thử khớp theo SKU (biến thể đã tồn tại từ trước).
                $manual = $manualBySignature[$signature] ?? $manualBySku[$variant->sku] ?? null;

                if ($manual !== null) {
                    // Admin đặt SKU riêng → dùng SKU đó (đảm bảo unique).
                    $manualSku = trim((string) ($manual['sku'] ?? ''));

                    if ($manualSku !== '' && $manualSku !== $variant->sku) {
                        $existingSku = ProductVariant::withTrashed()
                            ->where('sku', $manualSku)
                            ->whereKeyNot($variant->id)
                            ->first();

                        if ($existingSku === null) {
                            $variant->sku = $manualSku;
                        }
                    }

                    $variant->fill([
                        'name' => $manual['name'] ?? $variant->name,
                        'price' => $manual['price'] ?? $variant->price,
                        'sale_price' => $manual['sale_price'] ?? null,
                        'cost_price' => $manual['cost_price'] ?? null,
                        'image' => $manual['image'] ?? null,
                        'is_active' => $manual['is_active'] ?? true,
                    ]);
                }

                $variant->save();

                $keptIds[] = $variant->id;

                $this->syncSkuRecord($product, $variant);
            }

            // Ẩn (không xoá) các biến thể không còn khớp thuộc tính.
            $stale = $product->variants()
                ->whereNotIn('id', $keptIds ?: [0])
                ->where('is_active', true)
                ->get();

            foreach ($stale as $variant) {
                $variant->forceFill(['is_active' => false])->save();
                $deactivated++;
            }

            $product->forceFill(['has_variants' => $keptIds !== []])->save();
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => $deactivated,
        ];
    }

    /**
     * Tích hợp các giá trị thuộc tính → danh sách tổ hợp.
     *
     * @param  \Illuminate\Support\Collection<int, ProductAttribute>  $attributes
     * @return array<int, array<int, string>>
     */
    protected function combinations($attributes): array
    {
        if ($attributes->isEmpty()) {
            return [];
        }

        $result = [[]];

        foreach ($attributes as $attribute) {
            $values = $attribute->values->pluck('value')->all();

            $next = [];

            foreach ($result as $combination) {
                foreach ($values as $value) {
                    $next[] = array_merge($combination, [$value]);
                }
            }

            $result = $next;
        }

        return $result;
    }

    /**
     * Chữ ký thuộc tính: "value1|value2|value3".
     *
     * @param  array<int, string>  $combination
     */
    protected function signature(array $combination): string
    {
        $parts = array_pad($combination, ProductAttribute::MAX_ATTRIBUTES, '');

        return implode('|', array_map('strval', $parts));
    }

    /**
     * Dựng chữ ký từ dữ liệu biến thể admin nhập tay.
     *
     * Chỉ dùng được khi biến thể có đủ option value — nếu thiếu thì trả
     * null để không ghép nhầm.
     *
     * @param  array<string, mixed>  $variant
     */
    protected function signatureFromManual(array $variant): ?string
    {
        $values = [
            $variant['option_1_value'] ?? null,
            $variant['option_2_value'] ?? null,
            $variant['option_3_value'] ?? null,
        ];

        // Cần có ít nhất 1 giá trị option.
        $filled = array_filter($values, fn ($value) => filled($value));

        if ($filled === []) {
            return null;
        }

        return $this->signature(array_map('strval', $values));
    }

    /**
     * Tạo tên biến thể từ tên + giá trị thuộc tính.
     *
     * VD: product "Pin 21V", options ["Kiểu đầu"="Makita", "Dung lượng"="5.0Ah"]
     *   → "Pin 21V — Kiểu Makita — Dung lượng 5.0Ah"
     *
     * @param  array<int, string>  $names
     * @param  array<int, string>  $values
     */
    protected function buildVariantName(array $names, array $values, Product $product): string
    {
        $parts = [];

        foreach ($names as $index => $name) {
            $value = $values[$index] ?? null;

            if (filled($value)) {
                $parts[] = $name.' '.$value;
            }
        }

        if ($parts === []) {
            return $product->name;
        }

        return $product->name.' — '.implode(' — ', $parts);
    }

    /**
     * Sinh SKU cho biến thể mới.
     *
     * Ưu tiên SKU admin đã nhập; nếu không có thì sinh từ slug tên sản phẩm +
     * giá trị thuộc tính, và đảm bảo unique trong DB.
     *
     * @param  array<int, string>  $combination
     * @param  array<string, array<string, mixed>>  $manualBySku
     */
    protected function generateSku(Product $product, array $combination, array $manualBySku): string
    {
        $base = Str::upper(Str::slug($product->name.' '.implode(' ', $combination), '-'));
        $base = Str::limit($base, 80, '');
        $base = $base !== '' ? $base : 'SKU';

        $candidate = $base;
        $suffix = 1;

        while (true) {
            $exists = ProductVariant::withTrashed()->where('sku', $candidate)->exists();

            if (! $exists) {
                return $candidate;
            }

            $suffix++;
            $candidate = $base.'-'.$suffix;

            if ($suffix > 1000) {
                return $base.'-'.uniqid();
            }
        }
    }

    /**
     * Đảm bảo biến thể có bản ghi SKU (tồn kho).
     */
    protected function syncSkuRecord(Product $product, ProductVariant $variant): void
    {
        $skuRecord = \App\Models\Sku::withTrashed()->where('sku', $variant->sku)->first();

        if ($skuRecord === null) {
            \App\Models\Sku::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'source' => $product->source,
                'available_stock' => 0,
                'safety_stock' => (int) config('bigseller.safety_stock', 0),
                'price' => $variant->effectivePrice(),
                'cost_price' => $variant->cost_price,
            ]);

            return;
        }

        if ($skuRecord->trashed()) {
            $skuRecord->restore();
        }

        $skuRecord->forceFill([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => $variant->name,
            'price' => $variant->effectivePrice(),
        ])->save();
    }
}
