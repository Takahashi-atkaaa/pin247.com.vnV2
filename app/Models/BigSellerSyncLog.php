<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BigSellerSyncLog extends Model
{
    protected $table = 'bigseller_sync_logs';

    protected $fillable = [
        'sync_run_id',
        'operation',
        'entity_type',
        'entity_id',
        'request_url',
        'request_payload',
        'response_status',
        'response_payload',
        'error_code',
        'error_message',
        'duration_ms',
        'success',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'success' => 'boolean',
            'retry_count' => 'integer',
        ];
    }
}
