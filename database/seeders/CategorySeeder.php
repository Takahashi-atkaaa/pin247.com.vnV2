<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Danh mục sản phẩm PIN247.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Pin máy khoan',
                'slug' => 'pin-may-khoan',
                'description' => 'Pin lithium dùng cho máy khoan không dây, pin cho máy khoan 12V-21V.',
                'icon' => '🔋',
                'sort_order' => 1,
            ],
            [
                'name' => 'Pin lithium',
                'slug' => 'pin-lithium',
                'description' => 'Pin lithium Li-ion các loại, dùng cho thiết bị gia dụng và điện tử.',
                'icon' => '🔌',
                'sort_order' => 2,
            ],
            [
                'name' => 'Cell 18650',
                'slug' => 'cell-18650',
                'description' => 'Cell 18650 chính hãng, các mức dung lượng 2000mAh - 3500mAh.',
                'icon' => '🔩',
                'sort_order' => 3,
            ],
            [
                'name' => 'Cell 21700',
                'slug' => 'cell-21700',
                'description' => 'Cell 21700 dung lượng cao, phổ biến cho pin máy khoan và xe điện.',
                'icon' => '🧪',
                'sort_order' => 4,
            ],
            [
                'name' => 'Mạch BMS',
                'slug' => 'mach-bms',
                'description' => 'Mạch BMS bảo vệ pin, quản lý sạc xả và cân bằng cell.',
                'icon' => '🔧',
                'sort_order' => 5,
            ],
            [
                'name' => 'Bộ sạc pin',
                'slug' => 'bo-sac-pin',
                'description' => 'Bộ sạc nhanh, sạc chậm, bộ sạc nguồn cho các loại pin.',
                'icon' => '🔥',
                'sort_order' => 6,
            ],
            [
                'name' => 'Linh kiện đóng pin',
                'slug' => 'linh-kien-dong-pin',
                'description' => 'Đế pin, kẹp nickel, dây nối, đầu nối cho đóng pin.',
                'icon' => '⚙️',
                'sort_order' => 7,
            ],
            [
                'name' => 'Phụ kiện',
                'slug' => 'phu-kien',
                'description' => 'Dây sạc, đầu cắm, bộ chuyển đổi và phụ kiện kèm theo.',
                'icon' => '🧰',
                'sort_order' => 8,
            ],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['slug' => $data['slug']],
                $data + [
                    'is_active' => true,
                    'meta_title' => $data['name'].' - PIN247',
                    'meta_description' => $data['description'],
                ],
            );
        }
    }
}
