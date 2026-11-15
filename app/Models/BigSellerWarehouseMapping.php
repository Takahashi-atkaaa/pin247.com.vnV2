<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BigSellerWarehouseMapping extends Model
{
    protected $table = 'bigseller_warehouse_mappings';

    protected $fillable = [
        'warehouse_id',
        'bigseller_warehouse_code',
        'bigseller_warehouse_id',
        'bigseller_warehouse_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'bigseller_warehouse_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
