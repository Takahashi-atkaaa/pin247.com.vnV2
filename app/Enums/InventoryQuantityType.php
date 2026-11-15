<?php

namespace App\Enums;

/**
 * Loại tồn kho mà BigSeller có thể trả về.
 *
 * QUAN TRỌNG: BigSeller chỉ trả về những trường tồn kho thực sự có trong
 * response của API. Không tự suy luận thêm trường không có trong tài liệu.
 *
 * - Available: tồn kho khả dụng (có thể bán) — trường cốt lõi cho website.
 * - Reserved:  tồn kho đang được giữ cho đơn khác.
 * - OnHand:    tồn kho thực tế trong kho.
 * - Locked:   tồn kho bị khóa.
 * - Shipped:  tồn kho đã xuất.
 */
enum InventoryQuantityType: string
{
    case Available = 'AVAILABLE';
    case Reserved = 'RESERVED';
    case OnHand = 'ON_HAND';
    case Locked = 'LOCKED';
    case Shipped = 'SHIPPED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
