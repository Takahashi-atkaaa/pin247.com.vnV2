<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /**
     * Bảng `customers` có cột `deleted_at` từ migration gốc. Dùng SoftDeletes
     * để hồ sơ đã xoá mềm không xuất hiện trong truy vấn thường — đồng thời
     * khớp với cột generated `phone_active` (unique index chỉ áp dụng cho
     * hồ sơ chưa bị xoá mềm).
     */
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'address',
        'ward',
        'district',
        'province',
        'total_orders',
        'total_spent',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'total_orders' => 'integer',
            'total_spent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
