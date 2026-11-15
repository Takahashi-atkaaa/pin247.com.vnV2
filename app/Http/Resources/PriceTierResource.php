<?php

namespace App\Http\Resources;

use App\Models\PriceTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bậc giá — dạng phẳng dùng cho trang quản trị.
 *
 * @mixin PriceTier
 */
class PriceTierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku?->sku,
            'product_name' => $this->sku?->product?->name ?? $this->sku?->name,
            'variant_name' => $this->sku?->variant?->name,
            'min_qty' => (int) $this->min_qty,
            'max_qty' => $this->max_qty === null ? null : (int) $this->max_qty,
            'unit_price' => (float) $this->unit_price,
            'label' => $this->displayLabel(),
        ];
    }
}
