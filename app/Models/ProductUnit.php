<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đơn vị tính (UoM) của một biến thể — cơ chế clone từ Haravan.
 *
 * Ví dụ: biến thể "Pin 18650 Panasonic 3000mAh" có các đơn vị:
 *   - "viên"  (base, rate = 1)    → giá 79.000đ
 *   - "lô"    (rate = 20)         → giá tự tính = 79.000 × 20 = 1.580.000đ
 *
 * Quy tắc Haravan được giữ nguyên:
 *   - `conversion_rate` không sửa được sau khi tạo (chỉ thêm mới).
 *   - Giá của đơn vị quy đổi TỰ ĐỘNG bằng giá cơ bản × tỷ lệ, trừ khi
 *     người dùng đặt giá riêng cho đơn vị đó.
 */
class ProductUnit extends Model
{
    protected $fillable = [
        'product_variant_id',
        'name',
        'conversion_rate',
        'is_base',
        'price',
        'is_sellable',
        'barcode',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'conversion_rate' => 'integer',
            'is_base' => 'boolean',
            'is_sellable' => 'boolean',
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Giá bán của đơn vị này.
     *
     * - Nếu có giá riêng (`price` khác NULL) => dùng giá đó.
     * - Nếu là đơn vị cơ bản => dùng giá của biến thể.
     * - Nếu là đơn vị quy đổi => giá cơ bản × `conversion_rate`.
     *
     * @param  float  $basePrice  Giá của đơn vị cơ bản (của biến thể).
     */
    public function resolvedPrice(float $basePrice): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        return round($basePrice * $this->conversion_rate, 2);
    }
}
