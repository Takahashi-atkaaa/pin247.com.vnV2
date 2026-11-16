<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Khách hàng demo + 2 đơn hàng mẫu.
 *
 * Dùng cho:
 *   - E2E test (đăng nhập khách, kiểm tra phân quyền 403).
 *   - Demo trên hosting: khách có sẵn đơn để xem màn hình tra cứu đơn hàng.
 *
 * Tài khoản: khachdemo@example.com / secret1234
 */
class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'khachdemo@example.com'],
            [
                'name' => 'Lê Minh Anh',
                'phone' => '0912345678',
                'password' => Hash::make('secret1234'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ],
        );

        $customer = Customer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Lê Minh Anh',
                'email' => 'khachdemo@example.com',
                'phone' => '0912345678',
                'address' => '88 Nguyễn Thị Minh Khai',
                'ward' => 'Phường 6',
                'district' => 'Quận 3',
                'province' => 'TP. Hồ Chí Minh',
                'total_orders' => 2,
                'total_spent' => 2750000,
            ],
        );

        $specs = [
            ['PIN247-20260111-DEMO001', 1000000, 1200000, 'UNPAID', 'SYNCED', 10],
            ['PIN247-20260112-DEMO002', 1550000, 1550000, 'PAID', 'SYNCED', 15],
        ];

        foreach ($specs as [$code, $subtotal, $total, $payStatus, $syncStatus, $qty]) {
            $order = Order::firstOrCreate(
                ['order_code' => $code],
                [
                    'user_id' => $user->id,
                    'customer_id' => $customer->id,
                    'customer_name' => 'Lê Minh Anh',
                    'customer_phone' => '0912345678',
                    'shipping_address' => '88 Nguyễn Thị Minh Khai',
                    'ward' => 'Phường 6',
                    'district' => 'Quận 3',
                    'province' => 'TP. Hồ Chí Minh',
                    'subtotal' => $subtotal,
                    'shipping_fee' => $total - $subtotal,
                    'total_amount' => $total,
                    'payment_method' => 'COD',
                    'payment_status' => $payStatus,
                    'sync_status' => $syncStatus,
                ],
            );

            OrderItem::firstOrCreate(
                ['order_id' => $order->id, 'sku' => 'CELL-18650-PAN-3000'],
                [
                    'product_name' => 'Pin 18650 Panasonic 3000mAh',
                    'variant_name' => '3000mAh',
                    'quantity' => $qty,
                    'price' => 100000,
                    'subtotal' => $subtotal,
                ],
            );
        }
    }
}
