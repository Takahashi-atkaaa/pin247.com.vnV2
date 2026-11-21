<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Yêu cầu hỗ trợ của khách hàng (chat với admin).
 *
 * Khách không cần đăng nhập — họ giữ `public_token` (uuid) trong
 * localStorage để đọc lại cuộc trò chuyện.
 */
class SupportRequest extends Model
{
    protected $fillable = [
        'public_token',
        'user_id',
        'customer_id',
        'name',
        'email',
        'phone',
        'message',
        'status',
        'topic',
        'resolved_at',
        'resolved_by',
    ];

    /**
     * Token KHÔNG được serialize ra ngoài (chỉ trả về đúng lúc tạo).
     */
    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
