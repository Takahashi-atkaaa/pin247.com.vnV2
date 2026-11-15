<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'bigseller_order_code' => $this->bigseller_order_code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'shipping_address' => $this->shipping_address,
            'ward' => $this->ward,
            'district' => $this->district,
            'province' => $this->province,
            'note' => $this->note,
            'internal_note' => $this->internal_note,
            'subtotal' => (float) $this->subtotal,
            'shipping_fee' => (float) $this->shipping_fee,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            'sync_status' => $this->sync_status->value,
            'sync_status_label' => $this->sync_status->label(),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'shipping_status' => $this->shipping_status,
            'tracking_number' => $this->tracking_number,
            'carrier' => $this->carrier,
            'sync_attempts' => (int) $this->sync_attempts,
            'sync_error' => $this->sync_error,
            'synced_at' => $this->synced_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'image' => $item->image,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'amount' => (float) $payment->amount,
                'paid_amount' => (float) $payment->paid_amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])),
        ];
    }
}
