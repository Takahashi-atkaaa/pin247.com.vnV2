<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Biến thể sản phẩm — màn "Chi tiết biến thể" (clone Haravan).
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sku = $this->relationLoaded('skus') ? $this->skus->first() : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->displayName(),
            'sku' => $this->sku,
            'option_1_name' => $this->option_1_name,
            'option_1_value' => $this->option_1_value,
            'option_2_name' => $this->option_2_name,
            'option_2_value' => $this->option_2_value,
            'option_3_name' => $this->option_3_name,
            'option_3_value' => $this->option_3_value,
            'attribute_signature' => $this->attribute_signature,
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'effective_price' => $this->effectivePrice(),
            'image' => $this->image,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,

            // Thẻ kho (clone Haravan) — 6 chỉ số.
            'inventory' => [
                'available_stock' => (int) ($sku?->available_stock ?? 0),
                'reserved_stock' => (int) ($sku?->reserved_stock ?? 0),
                'local_reserved_stock' => (int) ($sku?->local_reserved_stock ?? 0),
                'on_hand_stock' => (int) ($sku?->on_hand_stock ?? 0),
                'shipped_stock' => (int) ($sku?->shipped_stock ?? 0),
                'safety_stock' => (int) ($sku?->safety_stock ?? 0),
                'sellable_quantity' => (int) ($sku?->sellableQuantity() ?? 0),
                'is_in_stock' => (bool) ($sku?->isInStock() ?? false),
                'sku_id' => $sku?->id,
                'sync_state' => $sku?->sync_state?->value,
                'sync_state_label' => $sku?->sync_state?->label(),
                'last_synced_at' => $sku?->last_synced_at?->toIso8601String(),
            ],

            // GĐ2 — Đơn vị tính (giá đã tính theo tỷ lệ quy đổi).
            'units' => $this->whenLoaded('units', fn () => $this->units->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'conversion_rate' => (int) $unit->conversion_rate,
                'is_base' => (bool) $unit->is_base,
                'price' => $unit->resolvedPrice($this->effectivePrice()),
                'has_custom_price' => $unit->price !== null,
                'is_sellable' => (bool) $unit->is_sellable,
                'barcode' => $unit->barcode,
            ])->values()),

            // GĐ3 — Lịch sử cập nhật.
            'histories' => $this->whenLoaded('histories', fn () => $this->histories->map(fn ($history) => [
                'id' => $history->id,
                'changed_fields' => $history->changed_fields,
                'before' => $history->before,
                'after' => $history->after,
                'summary' => $history->summary,
                'changed_by' => $history->changedBy?->name,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values()),

            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ]),
        ];
    }
}
