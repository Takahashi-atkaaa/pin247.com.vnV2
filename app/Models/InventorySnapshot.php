<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySnapshot extends Model
{
    protected $fillable = [
        'sku_id',
        'warehouse_id',
        'sku',
        'available',
        'reserved',
        'on_hand',
        'locked',
        'shipped',
        'sync_run_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'integer',
            'reserved' => 'integer',
            'on_hand' => 'integer',
            'locked' => 'integer',
            'shipped' => 'integer',
            'captured_at' => 'datetime',
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
