<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    protected $fillable = [
        'sku_id',
        'warehouse_id',
        'sku',
        'previous_available',
        'new_available',
        'delta',
        'reason',
        'sync_run_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'previous_available' => 'integer',
            'new_available' => 'integer',
            'delta' => 'integer',
            'meta' => 'array',
        ];
    }

    public function skuRecord(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
