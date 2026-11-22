<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thuộc tính sản phẩm — cơ chế clone từ Haravan.
 *
 * Mỗi sản phẩm có tối đa 3 thuộc tính (Haravan giới hạn 3).
 * Ví dụ: "Kiểu đầu" (Makita/Bosch/Hitachi), "Dung lượng" (2.0Ah/5.0Ah).
 *
 * Biến thể được sinh tự động bằng cách ghép (cartesian product) các giá trị
 * của tất cả thuộc tính.
 */
class ProductAttribute extends Model
{
    /**
     * Haravan cho phép tối đa 3 thuộc tính trên mỗi sản phẩm.
     */
    public const MAX_ATTRIBUTES = 3;

    protected $fillable = [
        'product_id',
        'name',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)->orderBy('sort_order');
    }
}
