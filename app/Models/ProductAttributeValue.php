<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giá trị của một thuộc tính sản phẩm.
 *
 * Ví dụ: thuộc tính "Kiểu đầu" có các giá trị "Makita", "Bosch", "Hitachi".
 */
class ProductAttributeValue extends Model
{
    protected $fillable = [
        'product_attribute_id',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }
}
