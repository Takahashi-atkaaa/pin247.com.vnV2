<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bậc giá của một SKU.
 *
 * Mỗi SKU có nhiều bậc giá, mỗi bậc áp dụng cho một khoảng số lượng
 * yêu cầu: `min_qty <= SL <= max_qty` (max_qty NULL = trở lên).
 */
class PriceTier extends Model
{
    protected $fillable = [
        'sku_id',
        'min_qty',
        'max_qty',
        'unit_price',
        'label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'unit_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    /**
     * Bậc giá này có áp dụng cho số lượng đã cho không.
     */
    public function matchesQuantity(int $quantity): bool
    {
        if ($quantity < $this->min_qty) {
            return false;
        }

        return $this->max_qty === null || $quantity <= $this->max_qty;
    }

    /**
     * Nhãn hiển thị, tự sinh nếu Admin không nhập.
     */
    public function displayLabel(): string
    {
        if (filled($this->label)) {
            return $this->label;
        }

        return $this->max_qty === null
            ? 'Từ '.$this->min_qty
            : $this->min_qty.'-'.$this->max_qty;
    }
}
