<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'bigseller_warehouse_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'bigseller_warehouse_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function bigSellerMapping()
    {
        return $this->hasOne(BigSellerWarehouseMapping::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(InventorySnapshot::class);
    }
}
