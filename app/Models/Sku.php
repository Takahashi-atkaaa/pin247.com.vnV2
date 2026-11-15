<?php

namespace App\Models;

use App\Enums\SyncState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SKU — đơn vị tồn kho.
 *
 * SKU là mã nghiệp vụ (unique), KHÔNG phải khóa chính của bảng.
 * Khóa chính vẫn là `id`.
 */
class Sku extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'sku',
        'name',
        'source',
        'available_stock',
        'reserved_stock',
        'local_reserved_stock',
        'on_hand_stock',
        'locked_stock',
        'shipped_stock',
        'safety_stock',
        'price',
        'cost_price',
        'sync_state',
        'last_synced_at',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'source' => \App\Enums\ProductSource::class,
            'sync_state' => SyncState::class,
            'available_stock' => 'integer',
            'reserved_stock' => 'integer',
            'local_reserved_stock' => 'integer',
            'on_hand_stock' => 'integer',
            'locked_stock' => 'integer',
            'shipped_stock' => 'integer',
            'safety_stock' => 'integer',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Các bậc giá theo số lượng yêu cầu.
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(PriceTier::class)->orderBy('min_qty');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(InventorySnapshot::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * Số lượng tối đa website được phép bán.
     *
     * = tồn kho khả dụng (từ BigSeller) - lượng website đang giữ cho đơn
     *   nội bộ (local_reserved_stock) - tồn kho an toàn.
     *
     * KHÔNG trừ `reserved_stock` vì đó là lượng BigSeller đã trừ sẵn khỏi
     * `available` (trừ thêm sẽ tính đôi).
     *
     * Không bao giờ nhỏ hơn 0.
     */
    public function sellableQuantity(): int
    {
        return max(0, $this->available_stock - $this->local_reserved_stock - $this->safety_stock);
    }

    /**
     * Tồn kho có thể bán được không.
     */
    public function isInStock(): bool
    {
        return $this->sellableQuantity() > 0;
    }
}
