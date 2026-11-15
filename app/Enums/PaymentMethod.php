<?php

namespace App\Enums;

/**
 * Phương thức thanh toán được hỗ trợ.
 */
enum PaymentMethod: string
{
    case Cod = 'COD';
    case BankTransfer = 'BANK_TRANSFER';

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
            self::Cod => 'Thanh toán khi nhận hàng (COD)',
            self::BankTransfer => 'Chuyển khoản ngân hàng',
        };
    }
}
