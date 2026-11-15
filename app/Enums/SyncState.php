<?php

namespace App\Enums;

/**
 * Trạng thái đồng bộ của một bản ghi (SKU, đơn hàng, mapping).
 */
enum SyncState: string
{
    /** Chưa từng đồng bộ. */
    case Pending = 'PENDING';

    /** Đang đồng bộ. */
    case Syncing = 'SYNCING';

    /** Đồng bộ thành công, dữ liệu đã cập nhật. */
    case Synced = 'SYNCED';

    /** Đồng bộ thất bại. */
    case Failed = 'FAILED';

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
            self::Pending => 'Chờ đồng bộ',
            self::Syncing => 'Đang đồng bộ',
            self::Synced => 'Đã đồng bộ',
            self::Failed => 'Lỗi',
        };
    }
}
