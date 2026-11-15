<?php

namespace App\Http\Resources;

use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sku
 */
class PriceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tiers = $this->priceTiers;

        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'product_name' => $this->product?->name ?? $this->name ?? $this->sku,
            'variant_name' => $this->variant?->name,
            'image' => $this->variant?->image ?? $this->product?->image,
            'brand' => $this->product?->brand,
            'category' => $this->whenLoaded('product', fn () => $this->product?->category ? [
                'id' => $this->product->category->id,
                'name' => $this->product->category->name,
                'slug' => $this->product->category->slug,
            ] : null),

            // Số lượng yêu cầu tối thiểu để đạt mức giá tốt nhất của SKU này.
            'min_order_qty' => $tiers->isNotEmpty() ? (int) $tiers->max('min_qty') : null,

            'tiers' => $tiers->map(fn ($tier) => [
                'id' => $tier->id,
                'min_qty' => (int) $tier->min_qty,
                'max_qty' => $tier->max_qty === null ? null : (int) $tier->max_qty,
                'unit_price' => (float) $tier->unit_price,
                'label' => $tier->displayLabel(),
            ])->values(),
        ];
    }
}
