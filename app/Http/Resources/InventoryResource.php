<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sku
 */
class InventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'available_stock' => (int) $this->available_stock,
            'reserved_stock' => (int) $this->reserved_stock,
            'on_hand_stock' => (int) $this->on_hand_stock,
            'locked_stock' => (int) $this->locked_stock,
            'shipped_stock' => (int) $this->shipped_stock,
            'safety_stock' => (int) $this->safety_stock,
            'sellable_quantity' => $this->sellableQuantity(),
            'is_in_stock' => $this->isInStock(),
            'price' => (float) $this->price,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'sync_state' => $this->sync_state->value,
            'sync_state_label' => $this->sync_state->label(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_error' => $this->sync_error,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'image' => $this->product->image,
            ]),
            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id' => $this->variant->id,
                'name' => $this->variant->name,
            ] : null),
        ];
    }
}
