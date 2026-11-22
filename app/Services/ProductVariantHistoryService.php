<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\ProductVariantHistory;

/**
 * Ghi & đọc lịch sử cập nhật biến thể — clone cơ chế Haravan.
 *
 * Haravan có mục "Lịch sử cập nhật" tại màn chi tiết biến thể, ghi lại các
 * thay đổi về giá bán, giá so sánh, SKU, barcode...
 */
class ProductVariantHistoryService
{
    /**
     * Các trường được theo dõi trong lịch sử.
     *
     * @var array<int, string>
     */
    public const TRACKED_FIELDS = [
        'price',
        'sale_price',
        'cost_price',
        'sku',
        'name',
        'is_active',
        'image',
    ];

    /**
     * Ghi lịch sử nếu có thay đổi có ý nghĩa.
     *
     * @param  array<string, mixed>  $before  Thuộc tính trước khi đổi.
     * @param  array<string, mixed>  $after   Thuộc tính sau khi đổi.
     */
    public function record(ProductVariant $variant, array $before, array $after, ?int $changedBy = null): ?ProductVariantHistory
    {
        $changedFields = [];
        $beforeSnapshot = [];
        $afterSnapshot = [];
        $summaryParts = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            // So sánh "thô" nhưng chuẩn hoá số 0/null cho giá.
            if ($this->normalize($field, $old) === $this->normalize($field, $new)) {
                continue;
            }

            $changedFields[] = $field;
            $beforeSnapshot[$field] = $old;
            $afterSnapshot[$field] = $new;
            $summaryParts[] = $this->describe($field, $old, $new);
        }

        if ($changedFields === []) {
            return null;
        }

        return ProductVariantHistory::create([
            'product_variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'changed_by' => $changedBy,
            'changed_fields' => $changedFields,
            'before' => $beforeSnapshot,
            'after' => $afterSnapshot,
            'summary' => mb_substr(implode('; ', $summaryParts), 0, 500),
        ]);
    }

    /**
     * Chuẩn hoá giá trị trước khi so sánh (null và 0 là tương đương với
     * các trường giá; "1" và true là tương đương với boolean).
     */
    protected function normalize(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, ['price', 'sale_price', 'cost_price'], true)) {
            return round((float) $value, 2);
        }

        if ($field === 'is_active') {
            return (bool) $value;
        }

        return is_scalar($value) ? (string) $value : $value;
    }

    /**
     * Mô tả thay đổi bằng tiếng Việt để hiển thị trong lịch sử.
     */
    protected function describe(string $field, mixed $old, mixed $new): string
    {
        $labels = [
            'price' => 'Giá bán',
            'sale_price' => 'Giá so sánh',
            'cost_price' => 'Giá vốn',
            'sku' => 'SKU',
            'name' => 'Tên',
            'is_active' => 'Trạng thái',
            'image' => 'Ảnh',
        ];

        $label = $labels[$field] ?? $field;

        if ($field === 'is_active') {
            return $label.': '.($new ? 'đang bán' : 'ẩn');
        }

        if (in_array($field, ['price', 'sale_price', 'cost_price'], true)) {
            $format = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.').'đ';

            return $label.': '.$format($old).' → '.$format($new);
        }

        $format = fn ($v) => $v === null || $v === '' ? '—' : (string) $v;

        return $label.': '.$format($old).' → '.$format($new);
    }
}
