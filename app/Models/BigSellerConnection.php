<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kết nối BigSeller.
 *
 * Bảo mật:
 * - `app_key`, `access_token`, `refresh_token` được mã hoá at-rest bằng
 *   encrypted cast (dựa trên APP_KEY).
 * - Model này KHÔNG được serialize trực tiếp ra API. Luôn dùng
 *   BigSellerConnectionResource để lọc bỏ các trường nhạy cảm.
 */
class BigSellerConnection extends Model
{
    protected $table = 'bigseller_connections';

    protected $fillable = [
        'name',
        'app_id',
        'app_key',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'capabilities',
        'shop_code',
        'shop_name',
        'default_warehouse_id',
        'is_active',
        'last_checked_at',
        'last_success_at',
        'last_error',
        'is_mock',
    ];

    protected $hidden = [
        'app_key',
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'app_key' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'capabilities' => 'array',
            'token_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
            'is_active' => 'boolean',
            'is_mock' => 'boolean',
        ];
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    /**
     * Token đã hết hạn hay chưa (có xét trước thời điểm hết hạn).
     */
    public function isTokenExpired(): bool
    {
        if (blank($this->token_expires_at)) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Tài khoản có được cấp quyền API cụ thể hay không.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }
}
