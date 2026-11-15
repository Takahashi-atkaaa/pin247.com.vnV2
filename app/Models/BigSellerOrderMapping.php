<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BigSellerOrderMapping extends Model
{
    protected $table = 'bigseller_order_mappings';

    protected $fillable = [
        'order_id',
        'idempotency_key',
        'bigseller_order_code',
        'bigseller_shop_code',
        'bigseller_status',
        'mapped_status',
        'request_payload',
        'response_payload',
        'attempts',
        'last_attempt_at',
        'synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
