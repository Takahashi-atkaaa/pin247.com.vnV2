<?php

namespace App\Models;

use App\Enums\ProductSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'brand',
        'description',
        'specifications',
        'price',
        'sale_price',
        'cost_price',
        'image',
        'source',
        'has_variants',
        'is_active',
        'is_featured',
        'is_new',
        'sales_count',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'source' => ProductSource::class,
            'specifications' => 'array',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'has_variants' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'sales_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (blank($product->slug)) {
                $product->slug = static::generateSlug($product->name, $product->id);
            }
        });
    }

    /**
     * Sinh slug duy nhất.
     */
    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'product';
        }

        $slug = $base;
        $counter = 1;

        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$counter);
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Thuộc tính động của sản phẩm (GĐ1 — clone Haravan).
     *
     * Mỗi sản phẩm tối đa 3 thuộc tính; biến thể được sinh tự động từ
     * tích hợp các giá trị của các thuộc tính này.
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('position');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bigSellerMapping(): HasOne
    {
        return $this->hasOne(BigSellerProductMapping::class);
    }

    /**
     * Giá bán hiện tại (ưu tiên giá khuyến mãi).
     */
    public function effectivePrice(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price < (float) $this->price;
    }

    /**
     * Tổng tồn kho khả dụng của tất cả SKU thuộc sản phẩm.
     *
     * Dùng quan hệ đã load sẵn nếu có, tránh truy vấn N+1 khi render danh sách.
     */
    public function totalAvailableStock(): int
    {
        if ($this->relationLoaded('skus')) {
            return (int) $this->skus->sum('available_stock');
        }

        return (int) $this->skus()->sum('available_stock');
    }
}
