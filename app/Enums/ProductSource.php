<?php

namespace App\Enums;

/**
 * Nguồn gốc của bản ghi sản phẩm / SKU.
 *
 * Dùng để phân biệt sản phẩm do Admin quản lý trực tiếp trên website
 * với sản phẩm được đồng bộ (nhập) từ BigSeller.
 */
enum ProductSource: string
{
    /** Admin tạo và quản lý trực tiếp trên website. */
    case Manual = 'MANUAL';

    /** Đồng bộ từ BigSeller. */
    case BigSeller = 'BIGSELLER';

    /** Nhập khởi tạo bằng CSV/Excel khi chưa có API sản phẩm BigSeller. */
    case Imported = 'IMPORTED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Quản lý trên website',
            self::BigSeller => 'Đồng bộ từ BigSeller',
            self::Imported => 'Nhập từ CSV/Excel',
        };
    }
}
