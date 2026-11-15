<?php

namespace App\Enums;

/**
 * Trạng thái thanh toán của đơn hàng.
 *
 * Đơn KHÔNG được đánh dấu "paid" nếu chưa có xác nhận thanh toán hợp lệ.
 * Với COD, đơn ở trạng thái "pending"/"cod" cho tới khi giao hàng thu tiền.
 */
enum PaymentStatus: string
{
    case Unpaid = 'UNPAID';
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Failed = 'FAILED';
    case Refunded = 'REFUNDED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';

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
            self::Unpaid => 'Chưa thanh toán',
            self::Pending => 'Đang chờ thanh toán',
            self::Paid => 'Đã thanh toán',
            self::Failed => 'Thanh toán thất bại',
            self::Refunded => 'Đã hoàn tiền',
            self::PartiallyRefunded => 'Hoàn tiền một phần',
        };
    }
}
