<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lịch sử cập nhật biến thể — cơ chế clone từ Haravan.
 *
 * Mỗi lần giá/SKU/trạng thái biến thể thay đổi sẽ ghi một dòng lịch sử,
 * hiển thị tại màn "Chi tiết biến thể".
 */
class ProductVariantHistory extends Model
{
    protected $fillable = [
        'product_variant_id',
        'product_id',
        'changed_by',
        'changed_fields',
        'before',
        'after',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
