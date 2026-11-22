<?php

namespace App\Services;

use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Quản lý đơn vị tính (UoM) — clone cơ chế Haravan.
 *
 * Quy tắc Haravan được giữ nguyên:
 *   - Mỗi biến thể có nhiều đơn vị tính.
 *   - Đúng 1 đơn vị CƠ BẢN (`is_base = true`, `conversion_rate = 1`).
 *   - Giá của đơn vị quy đổi TỰ ĐỘNG = giá cơ bản × tỷ lệ quy đổi.
 *   - `conversion_rate` KHÔNG sửa được sau khi tạo (chỉ thêm mới / xoá).
 *   - Mỗi đơn vị có barcode riêng + cờ "bán hàng" (`is_sellable`).
 */
class ProductUnitService
{
    /**
     * Đồng bộ đơn vị tính của một biến thể.
     *
     * @param  array<int, array<string, mixed>>  $units
     */
    public function syncUnits(ProductVariant $variant, array $units): void
    {
        DB::transaction(function () use ($variant, $units): void {
            // Các đơn vị đang tồn tại, key theo id (để biết đơn vị nào được giữ).
            $existing = $variant->units()->get()->keyBy('id');

            // Đơn vị cơ bản cũ (nếu có) sẽ bị thay thế nếu payload không
            // mang `id` của nó. Xoá trước để tránh vi phạm unique index
            // (product_variant_id, is_base_key).
            $payloadIds = collect($units)
                ->map(fn ($unit) => isset($unit['id']) ? (int) $unit['id'] : null)
                ->filter()
                ->all();

            $existing->each(function (ProductUnit $unit) use ($payloadIds): void {
                if ($unit->is_base && ! in_array($unit->id, $payloadIds, true)) {
                    $unit->delete();
                }
            });

            $existing = $variant->units()->get()->keyBy('id');

            $keptIds = [];
            $sortOrder = 0;
            $hasBase = false;

            foreach ($units as $unit) {
                $name = trim((string) ($unit['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $id = isset($unit['id']) ? (int) $unit['id'] : null;

                // Tỷ lệ quy đổi: bắt buộc >= 1. Đơn vị cơ bản luôn = 1.
                $rate = max(1, (int) ($unit['conversion_rate'] ?? 1));

                $isBase = (bool) ($unit['is_base'] ?? false) || $rate === 1;

                // Chỉ 1 đơn vị cơ bản: đơn vị đầu tiên được đánh dấu base.
                if ($hasBase && $isBase) {
                    $isBase = false;
                    $rate = max(2, $rate);
                }

                if ($isBase) {
                    $hasBase = true;
                    $rate = 1;
                }

                $model = ($id && $existing->has($id))
                    ? $existing->get($id)
                    : new ProductUnit(['product_variant_id' => $variant->id]);

                // Tỷ lệ quy đổi không được sửa sau khi tạo (quy tắc Haravan).
                // Đơn vị mới thì lấy giá trị gửi lên; đơn vị cũ giữ nguyên.
                $conversionRate = $model->exists ? $model->conversion_rate : $rate;

                $model->fill([
                    'product_variant_id' => $variant->id,
                    'name' => $name,
                    'conversion_rate' => $conversionRate,
                    'is_base' => $isBase,
                    'price' => isset($unit['price']) && $unit['price'] !== null && $unit['price'] !== ''
                        ? (float) $unit['price']
                        : $model->price,
                    'is_sellable' => (bool) ($unit['is_sellable'] ?? $model->is_sellable ?? true),
                    'barcode' => $unit['barcode'] ?? $model->barcode,
                    'sort_order' => $sortOrder,
                ]);

                $model->save();

                $keptIds[] = $model->id;
                $sortOrder++;
            }

            // Xoá các đơn vị không còn trong danh sách.
            $existing->each(function (ProductUnit $unit) use ($keptIds): void {
                if (! in_array($unit->id, $keptIds, true)) {
                    $unit->delete();
                }
            });
        });
    }

    /**
     * Giá bán của từng đơn vị tính (đã tính theo tỷ lệ quy đổi).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pricedUnits(ProductVariant $variant): array
    {
        $basePrice = $variant->effectivePrice();

        return $variant->units->map(fn (ProductUnit $unit) => [
            'id' => $unit->id,
            'name' => $unit->name,
            'conversion_rate' => $unit->conversion_rate,
            'is_base' => $unit->is_base,
            'price' => $unit->resolvedPrice($basePrice),
            'has_custom_price' => $unit->price !== null,
            'is_sellable' => $unit->is_sellable,
            'barcode' => $unit->barcode,
        ])->values()->all();
    }
}
