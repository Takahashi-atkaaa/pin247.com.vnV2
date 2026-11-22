<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'option_1_name',
        'option_1_value',
        'option_2_name',
        'option_2_value',
        'option_3_name',
        'option_3_value',
        'attribute_signature',
        'price',
        'sale_price',
        'cost_price',
        'image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Đơn vị tính của biến thể (GĐ2 — clone Haravan).
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('sort_order');
    }

    /**
     * Lịch sử cập nhật biến thể (GĐ3 — clone Haravan).
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ProductVariantHistory::class)->latest('id');
    }

    /**
     * Các bậc giá theo số lượng yêu cầu (qua SKU của biến thể).
     */
    public function priceTiers(): HasManyThrough
    {
        return $this->hasManyThrough(
            PriceTier::class,
            Sku::class,
            'product_variant_id',
            'sku_id',
        )->orderBy('min_qty');
    }

    public function bigSellerMapping(): HasOne
    {
        return $this->hasOne(BigSellerProductMapping::class);
    }

    public function effectivePrice(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    /**
     * Mảng [tên option, giá trị option] cho tối đa 3 thuộc tính.
     *
     * @return array<int, array{0: ?string, 1: ?string}>
     */
    public function optionPairs(): array
    {
        return [
            [$this->option_1_name, $this->option_1_value],
            [$this->option_2_name, $this->option_2_value],
            [$this->option_3_name, $this->option_3_value],
        ];
    }

    /**
     * Chữ ký thuộc tính của biến thể, dùng để đối chiếu nhanh.
     *
     * Ví dụ: "Makita|2.0Ah|" (thuộc tính chưa có giá trị → chuỗi rỗng).
     */
    public function computeAttributeSignature(): string
    {
        $parts = array_map(
            fn (array $pair) => (string) ($pair[1] ?? ''),
            $this->optionPairs(),
        );

        return implode('|', $parts);
    }

    /**
     * Tên hiển thị đầy đủ của biến thể (VD: "2.0Ah — Kiểu Makita").
     *
     * Tên trong DB đã có sẵn theo định dạng này (từ BigSeller), nên hàm này
     * chỉ là fallback khi tên trống.
     */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        $values = collect($this->optionPairs())
            ->map(fn (array $pair) => $pair[1])
            ->filter()
            ->implode(' - ');

        return $values !== '' ? $values : (string) $this->sku;
    }
}
