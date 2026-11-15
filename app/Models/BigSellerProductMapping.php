<?php

namespace App\Models;

use App\Enums\SyncState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BigSellerProductMapping extends Model
{
    protected $table = 'bigseller_product_mappings';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'sku_id',
        'bigseller_sku',
        'bigseller_product_id',
        'bigseller_variant_id',
        'product_name',
        'image',
        'price',
        'attributes',
        'sync_state',
        'last_synced_at',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price' => 'decimal:2',
            'bigseller_product_id' => 'integer',
            'bigseller_variant_id' => 'integer',
            'sync_state' => SyncState::class,
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

    public function skuRecord(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }
}
