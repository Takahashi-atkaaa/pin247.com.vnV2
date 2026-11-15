<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * Đọc giá trị đã cast theo kiểu đã khai báo.
     */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            'decimal' => (float) $this->value,
            default => $this->value,
        };
    }

    /**
     * Lấy giá trị setting theo key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting?->typedValue() ?? $default;
    }

    /**
     * Ghi (hoặc tạo mới) một setting theo key.
     *
     * Giá trị được lưu dạng chuỗi; kiểu được suy ra từ giá trị truyền vào
     * để `typedValue()` trả về đúng kiểu khi đọc lại.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        $type = match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'decimal',
            is_array($value) => 'json',
            default => 'string',
        };

        $stored = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'group' => $group],
        );
    }
}
