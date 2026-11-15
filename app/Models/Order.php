<?php

namespace App\Models;

use App\Enums\OrderSyncStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_code',
        'bigseller_order_code',
        'idempotency_key',
        'customer_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'ward',
        'district',
        'province',
        'note',
        'internal_note',
        'subtotal',
        'shipping_fee',
        'discount_amount',
        'total_amount',
        'sync_status',
        'payment_method',
        'payment_status',
        'shipping_status',
        'tracking_number',
        'carrier',
        'sync_attempts',
        'last_sync_attempt_at',
        'synced_at',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'sync_status' => OrderSyncStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sync_attempts' => 'integer',
            'last_sync_attempt_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->order_code)) {
                $order->order_code = static::generateOrderCode();
            }

            if (blank($order->idempotency_key)) {
                $order->idempotency_key = static::generateIdempotencyKey($order);
            }
        });
    }

    /**
     * Mã đơn hàng nội bộ, dạng PIN247-YYYYMMDD-XXXXXX.
     */
    public static function generateOrderCode(): string
    {
        do {
            $code = 'PIN247-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('order_code', $code)->exists());

        return $code;
    }

    /**
     * Idempotency key chống tạo đơn trùng.
     *
     * Dùng hash của order_code + tổng tiền + danh sách SKU/số lượng.
     * Nhờ vậy retry cùng một đơn luôn cho cùng key, còn hai đơn khác nhau
     * (dù trùng nội dung) vẫn có key khác nhau.
     */
    public static function generateIdempotencyKey(self $order): string
    {
        $items = $order->items ?? collect();

        $fingerprint = $items->map(fn ($i) => $i->sku.':'.$i->quantity)
            ->sort()
            ->implode(',');

        return hash('sha256', implode('|', [
            $order->order_code,
            $fingerprint,
            (string) $order->total_amount,
            (string) $order->customer_phone,
        ]));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function bigSellerMapping(): HasOne
    {
        return $this->hasOne(BigSellerOrderMapping::class);
    }

    /**
     * Đơn đã được BigSeller nhận hay chưa.
     */
    public function isSyncedToBigSeller(): bool
    {
        return filled($this->bigseller_order_code);
    }

    /**
     * Đơn cần được đẩy (hoặc đẩy lại) lên BigSeller.
     */
    public function needsPush(): bool
    {
        return $this->sync_status->requiresPush();
    }
}
