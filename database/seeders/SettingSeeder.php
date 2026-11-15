<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'inventory.low_stock_threshold',
                'value' => '5',
                'type' => 'int',
                'group' => 'inventory',
                'description' => 'Ngưỡng cảnh báo sắp hết hàng (số lượng).',
            ],
            [
                'key' => 'inventory.safety_stock',
                'value' => '5',
                'type' => 'int',
                'group' => 'inventory',
                'description' => 'Tồn kho an toàn mặc định cho mỗi SKU.',
            ],
            [
                'key' => 'bigseller.inventory_sync_every_minutes',
                'value' => '15',
                'type' => 'int',
                'group' => 'bigseller',
                'description' => 'Chu kỳ đồng bộ tồn kho từ BigSeller (phút).',
            ],
            [
                'key' => 'bigseller.safety_stock',
                'value' => '0',
                'type' => 'int',
                'group' => 'bigseller',
                'description' => 'Tồn kho an toàn áp dụng khi đồng bộ từ BigSeller.',
            ],
            [
                'key' => 'bigseller.order_push_retry_attempts',
                'value' => '3',
                'type' => 'int',
                'group' => 'bigseller',
                'description' => 'Số lần tự động thử lại khi đẩy đơn hàng lỗi.',
            ],
            [
                'key' => 'shop.name',
                'value' => 'PIN247',
                'type' => 'string',
                'group' => 'shop',
                'description' => 'Tên cửa hàng hiển thị trên website.',
            ],
            [
                'key' => 'shop.shipping_fee',
                'value' => '30000',
                'type' => 'int',
                'group' => 'shop',
                'description' => 'Phí vận chuyển mặc định (VNĐ).',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
