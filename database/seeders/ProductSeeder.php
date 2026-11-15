<?php

namespace Database\Seeders;

use App\Enums\ProductSource;
use App\Enums\SyncState;
use App\Models\Category;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sku;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Danh mục sản phẩm mẫu của PIN247.
     *
     * Mỗi sản phẩm có thể có nhiều biến thể (variant), mỗi biến thể có
     * đúng một SKU quản lý tồn kho.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $catalog = [
        [
            'category' => 'pin-may-khoan',
            'name' => 'Pin máy khoan không dây 12V 2.0Ah thay thế',
            'brand' => 'PIN247',
            'price' => 289000,
            'sale_price' => 249000,
            'cost_price' => 165000,
            'is_featured' => true,
            'description' => 'Pin lithium thay thế cho máy khoan không dây 12V, dung lượng 2.0Ah, tương thích với dòng máy khoan phổ thông.',
            'specifications' => [
                'Điện áp' => '12V',
                'Dung lượng' => '2.0Ah (6000mWh)',
                'Loại cell' => 'Li-ion 18650',
                'Số cell' => '3S1P',
                'Đầu nối' => 'Đầu kẹp bản lề kiểu Makita',
            ],
            'variants' => [
                ['name' => '2.0Ah — Kiểu Makita', 'sku' => 'PIN-KHOAN-12V-20-MAK', 'price' => 249000, 'stock' => 45, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Makita', 'option_2_name' => 'Dung lượng', 'option_2_value' => '2.0Ah'],
                ['name' => '2.0Ah — Kiểu Bosch', 'sku' => 'PIN-KHOAN-12V-20-BOS', 'price' => 249000, 'stock' => 30, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Bosch', 'option_2_name' => 'Dung lượng', 'option_2_value' => '2.0Ah'],
                ['name' => '2.0Ah — Kiểu Hitachi', 'sku' => 'PIN-KHOAN-12V-20-HIT', 'price' => 259000, 'stock' => 18, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Hitachi', 'option_2_name' => 'Dung lượng', 'option_2_value' => '2.0Ah'],
            ],
        ],
        [
            'category' => 'pin-may-khoan',
            'name' => 'Pin máy khoan không dây 21V 5.0Ah siêu trâu',
            'brand' => 'PIN247',
            'price' => 849000,
            'sale_price' => 749000,
            'cost_price' => 520000,
            'is_featured' => true,
            'is_new' => true,
            'description' => 'Pin 21V 5.0Ah dùng cell 21700, công suất lớn, thời gian xử lý pin khoan dài gấp 2.5 lần so với pin 2.0Ah.',
            'specifications' => [
                'Điện áp' => '21V',
                'Dung lượng' => '5.0Ah (10500mWh)',
                'Loại cell' => 'Li-ion 21700',
                'Số cell' => '5S1P',
                'Đầu nối' => 'Đầu kẹp bản lề kiểu Makita',
            ],
            'variants' => [
                ['name' => '5.0Ah — Kiểu Makita', 'sku' => 'PIN-KHOAN-21V-50-MAK', 'price' => 749000, 'stock' => 22, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Makita', 'option_2_name' => 'Dung lượng', 'option_2_value' => '5.0Ah'],
                ['name' => '5.0Ah — Kiểu Bosch', 'sku' => 'PIN-KHOAN-21V-50-BOS', 'price' => 749000, 'stock' => 15, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Bosch', 'option_2_name' => 'Dung lượng', 'option_2_value' => '5.0Ah'],
            ],
        ],
        [
            'category' => 'pin-may-khoan',
            'name' => 'Pin máy khoan không dây 18V 4.0Ah',
            'brand' => 'PIN247',
            'price' => 599000,
            'cost_price' => 410000,
            'description' => 'Pin 18V 4.0Ah dùng cell 21700, cân bằng giữa dung lượng và trọng lượng.',
            'specifications' => [
                'Điện áp' => '18V',
                'Dung lượng' => '4.0Ah (7200mWh)',
                'Loại cell' => 'Li-ion 21700',
                'Số cell' => '5S1P',
            ],
            'variants' => [
                ['name' => '4.0Ah — Kiểu Makita', 'sku' => 'PIN-KHOAN-18V-40-MAK', 'price' => 599000, 'stock' => 12, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Makita', 'option_2_name' => 'Dung lượng', 'option_2_value' => '4.0Ah'],
            ],
        ],
        [
            'category' => 'cell-18650',
            'name' => 'Cell 18650 3000mAh Panasonic NCR18650B',
            'brand' => 'Panasonic',
            'price' => 89000,
            'sale_price' => 79000,
            'cost_price' => 52000,
            'is_featured' => true,
            'description' => 'Cell 18650 xuất xưởng Nhật Bản, dung lượng thực 2950-3000mAh, xả tải 6.5A, phù hợp đóng pin máy khoan và đèn pin.',
            'specifications' => [
                'Kích thước' => '18mm x 65mm',
                'Dung lượng' => '3000mAh / 3400mWh',
                'Xả tải tối đa' => '6.5A',
                'Sạc chuẩn' => '0.5C',
                'Cell' => 'NCR18650B',
            ],
            'variants' => [
                ['name' => 'NCR18650B — 3000mAh', 'sku' => 'CELL-18650-PAN-3000', 'price' => 79000, 'stock' => 260, 'option_1_name' => 'Model', 'option_1_value' => 'NCR18650B', 'option_2_name' => 'Dung lượng', 'option_2_value' => '3000mAh'],
                ['name' => 'NCR18650B — 3000mAh (Hàng lô 20)', 'sku' => 'CELL-18650-PAN-3000-X20', 'price' => 1500000, 'stock' => 8, 'option_1_name' => 'Model', 'option_1_value' => 'NCR18650B', 'option_2_name' => 'Dung lượng', 'option_2_value' => '3000mAh'],
            ],
        ],
        [
            'category' => 'cell-18650',
            'name' => 'Cell 18650 2500mAh Samsung 25R',
            'brand' => 'Samsung',
            'price' => 65000,
            'cost_price' => 42000,
            'description' => 'Cell 18650 Samsung 25R 2500mAh, xả tải 25A, phù hợp cho thiết bị đòi hỏi dòng cao.',
            'specifications' => [
                'Dung lượng' => '2500mAh',
                'Xả tải tối đa' => '25A',
                'Cell' => 'INR18650-25R',
            ],
            'variants' => [
                ['name' => 'INR18650-25R', 'sku' => 'CELL-18650-SAM-2500', 'price' => 65000, 'stock' => 140, 'option_1_name' => 'Model', 'option_1_value' => '25R', 'option_2_name' => 'Dung lượng', 'option_2_value' => '2500mAh'],
            ],
        ],
        [
            'category' => 'cell-21700',
            'name' => 'Cell 21700 4000mAh Molicel P45B',
            'brand' => 'Molicel',
            'price' => 179000,
            'sale_price' => 159000,
            'cost_price' => 105000,
            'is_featured' => true,
            'description' => 'Cell 21700 4000mAh xả tải 45A, cell tốt nhất phân khúc cho pin máy khoan 21V và xe điện.',
            'specifications' => [
                'Kích thước' => '21mm x 70mm',
                'Dung lượng' => '4000mAh / 14800mWh',
                'Xả tải tối đa' => '45A',
                'Cell' => 'P45B',
            ],
            'variants' => [
                ['name' => 'P45B — 4000mAh', 'sku' => 'CELL-21700-MOL-4000', 'price' => 159000, 'stock' => 95, 'option_1_name' => 'Model', 'option_1_value' => 'P45B', 'option_2_name' => 'Dung lượng', 'option_2_value' => '4000mAh'],
            ],
        ],
        [
            'category' => 'cell-21700',
            'name' => 'Cell 21700 5000mAh Samsung 50E',
            'brand' => 'Samsung',
            'price' => 245000,
            'cost_price' => 175000,
            'is_new' => true,
            'description' => 'Cell 21700 5000mAh, dung lượng cực cao, phù hợp đóng pin nguồn dự phòng.',
            'specifications' => [
                'Dung lượng' => '5000mAh',
                'Xả tải tối đa' => '10A',
                'Cell' => 'INR21700-50E',
            ],
            'variants' => [
                ['name' => 'INR21700-50E', 'sku' => 'CELL-21700-SAM-5000', 'price' => 245000, 'stock' => 40, 'option_1_name' => 'Model', 'option_1_value' => '50E', 'option_2_name' => 'Dung lượng', 'option_2_value' => '5000mAh'],
            ],
        ],
        [
            'category' => 'mach-bms',
            'name' => 'Mạch BMS 3S 20A 18650 có cân bằng',
            'brand' => 'PIN247',
            'price' => 39000,
            'sale_price' => 32000,
            'cost_price' => 18000,
            'is_featured' => true,
            'description' => 'Mạch BMS 3S 20A dành cho pack 3 cell 18650, có chức năng cân bằng cell, bảo vệ quá dòng và ngắn mạch.',
            'specifications' => [
                'Số cell' => '3S',
                'Dòng tối đa' => '20A',
                'Cân bằng' => 'Có',
                'Điện áp sạc tối đa' => '12.6V',
            ],
            'variants' => [
                ['name' => '3S 20A', 'sku' => 'BMS-3S-20A', 'price' => 32000, 'stock' => 180, 'option_1_name' => 'Số cell', 'option_1_value' => '3S', 'option_2_name' => 'Dòng', 'option_2_value' => '20A'],
                ['name' => '3S 30A', 'sku' => 'BMS-3S-30A', 'price' => 48000, 'stock' => 75, 'option_1_name' => 'Số cell', 'option_1_value' => '3S', 'option_2_name' => 'Dòng', 'option_2_value' => '30A'],
            ],
        ],
        [
            'category' => 'mach-bms',
            'name' => 'Mạch BMS 5S 30A 21700 cho pin 21V',
            'brand' => 'PIN247',
            'price' => 79000,
            'cost_price' => 48000,
            'description' => 'Mạch BMS 5S 30A cho pack 5 cell 21700, chuyên dụng đóng pin 21V cho máy khoan.',
            'specifications' => [
                'Số cell' => '5S',
                'Dòng tối đa' => '30A',
                'Cân bằng' => 'Có',
                'Điện áp sạc tối đa' => '21V',
            ],
            'variants' => [
                ['name' => '5S 30A', 'sku' => 'BMS-5S-30A', 'price' => 79000, 'stock' => 55, 'option_1_name' => 'Số cell', 'option_1_value' => '5S', 'option_2_name' => 'Dòng', 'option_2_value' => '30A'],
            ],
        ],
        [
            'category' => 'bo-sac-pin',
            'name' => 'Bộ sạc nhanh 21V 2A cho pin máy khoan',
            'brand' => 'PIN247',
            'price' => 199000,
            'sale_price' => 169000,
            'cost_price' => 95000,
            'is_featured' => true,
            'description' => 'Bộ sạc nguồn 21V 2A cho pin 21V 5S1P, có đèn LED báo trạng thái sạc.',
            'specifications' => [
                'Điện áp đầu ra' => '21V',
                'Dòng sạc' => '2A',
                'Đầu vào' => '220V',
                'Tương thích' => 'Pin 21V kiểu Makita',
            ],
            'variants' => [
                ['name' => '21V 2A — Kiểu Makita', 'sku' => 'SAC-21V-2A-MAK', 'price' => 169000, 'stock' => 33, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Makita', 'option_2_name' => 'Dòng sạc', 'option_2_value' => '2A'],
                ['name' => '21V 2A — Kiểu Bosch', 'sku' => 'SAC-21V-2A-BOS', 'price' => 169000, 'stock' => 21, 'option_1_name' => 'Kiểu đầu', 'option_1_value' => 'Bosch', 'option_2_name' => 'Dòng sạc', 'option_2_value' => '2A'],
            ],
        ],
        [
            'category' => 'bo-sac-pin',
            'name' => 'Bộ sạc 18650 4 kênh 1A kiểm tra dung lượng',
            'brand' => 'PIN247',
            'price' => 290000,
            'cost_price' => 175000,
            'description' => 'Bộ sạc 4 kênh cho cell 18650/26650, tự cân bằng và hiển thị dung lượng thực tế trên màn hình LED.',
            'specifications' => [
                'Số kênh' => '4',
                'Dòng sạc mỗi kênh' => '1A',
                'Loại cell' => '18650 / 26650 / 21700',
                'Nguồn' => '220V',
            ],
            'variants' => [
                ['name' => '4 kênh 1A', 'sku' => 'SAC-18650-4CH-1A', 'price' => 290000, 'stock' => 26, 'option_1_name' => 'Kênh', 'option_1_value' => '4 kênh', 'option_2_name' => 'Dòng', 'option_2_value' => '1A'],
            ],
        ],
        [
            'category' => 'linh-kien-dong-pin',
            'name' => 'Bộ đế pin 18650 kẹp nickel đầy đủ',
            'brand' => 'PIN247',
            'price' => 12000,
            'cost_price' => 5500,
            'description' => 'Đế pin 18650 kẹp bản lề, kèm kẹp nickel và dây nối. Dùng đóng pin máy khoan.',
            'specifications' => [
                'Loại cell' => '18650',
                'Số cell tối đa' => '10',
                'Kèm kẹp nickel' => 'Có',
            ],
            'variants' => [
                ['name' => 'Đế 1 cell', 'sku' => 'DEP-18650-1C', 'price' => 5000, 'stock' => 320, 'option_1_name' => 'Số cell', 'option_1_value' => '1'],
                ['name' => 'Đế 3 cell', 'sku' => 'DEP-18650-3C', 'price' => 12000, 'stock' => 150, 'option_1_name' => 'Số cell', 'option_1_value' => '3'],
                ['name' => 'Đế 5 cell', 'sku' => 'DEP-18650-5C', 'price' => 18000, 'stock' => 88, 'option_1_name' => 'Số cell', 'option_1_value' => '5'],
            ],
        ],
        [
            'category' => 'phu-kien',
            'name' => 'Dây sạc Micro-USB 1m chống đứt',
            'brand' => 'PIN247',
            'price' => 25000,
            'sale_price' => 19000,
            'cost_price' => 9000,
            'description' => 'Dây sạc Micro-USB 1m, đầu nối kim loại chịu lực tốt, phù hợp sạc nhiều loại pin.',
            'specifications' => [
                'Loại cổng' => 'Micro-USB',
                'Chiều dài' => '1m',
                'Dòng sạc tối đa' => '3A',
            ],
            'variants' => [
                ['name' => 'Micro-USB 1m', 'sku' => 'CAB-MUSB-1M', 'price' => 19000, 'stock' => 400, 'option_1_name' => 'Cổng', 'option_1_value' => 'Micro-USB', 'option_2_name' => 'Chiều dài', 'option_2_value' => '1m'],
                ['name' => 'Type-C 1m', 'sku' => 'CAB-TYPE-C-1M', 'price' => 25000, 'stock' => 280, 'option_1_name' => 'Cổng', 'option_1_value' => 'Type-C', 'option_2_name' => 'Chiều dài', 'option_2_value' => '1m'],
            ],
        ],
        [
            'category' => 'pin-lithium',
            'name' => 'Pin lithium 3.7V 3000mAh cho thiết bị điện tử',
            'brand' => 'PIN247',
            'price' => 95000,
            'cost_price' => 58000,
            'description' => 'Pin lithium 3.7V 3000mAh kèm bản sạc, dùng cho đèn LED, loa, thiết bị đo lường.',
            'specifications' => [
                'Điện áp' => '3.7V',
                'Dung lượng' => '3000mAh',
                'Kích thước' => '65 x 18mm',
                'Bảo vệ' => 'BMS tích hợp',
            ],
            'variants' => [
                ['name' => '3.7V 3000mAh + sạc', 'sku' => 'PIN-LI-37V-3000', 'price' => 95000, 'stock' => 64, 'option_1_name' => 'Điện áp', 'option_1_value' => '3.7V', 'option_2_name' => 'Dung lượng', 'option_2_value' => '3000mAh'],
            ],
        ],
    ];

    public function run(): void
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'KHO-HN'],
            [
                'name' => 'Kho chính Hà Nội',
                'address' => 'Số 1 Đường Láng, Đống Đa, Hà Nội',
                'phone' => '0900000000',
                'is_active' => true,
            ],
        );

        foreach ($this->catalog as $item) {
            $category = Category::where('slug', $item['category'])->first();

            if (! $category) {
                continue;
            }

            $product = Product::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($item['name'])],
                [
                    'category_id' => $category->id,
                    'name' => $item['name'],
                    'brand' => $item['brand'],
                    'description' => $item['description'],
                    'specifications' => $item['specifications'],
                    'price' => $item['price'],
                    'sale_price' => $item['sale_price'] ?? null,
                    'cost_price' => $item['cost_price'],
                    'source' => ProductSource::Manual,
                    'has_variants' => true,
                    'is_active' => true,
                    'is_featured' => $item['is_featured'] ?? false,
                    'is_new' => $item['is_new'] ?? false,
                    'meta_title' => $item['name'].' - PIN247',
                    'meta_description' => $item['description'],
                ],
            );

            foreach ($item['variants'] as $index => $variantData) {
                $variant = ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'],
                    ],
                    [
                        'name' => $variantData['name'],
                        'option_1_name' => $variantData['option_1_name'] ?? null,
                        'option_1_value' => $variantData['option_1_value'] ?? null,
                        'option_2_name' => $variantData['option_2_name'] ?? null,
                        'option_2_value' => $variantData['option_2_value'] ?? null,
                        'price' => $variantData['price'],
                        'cost_price' => $item['cost_price'],
                        'sort_order' => $index,
                        'is_active' => true,
                    ],
                );

                $stock = (int) $variantData['stock'];

                $sku = Sku::updateOrCreate(
                    ['sku' => $variantData['sku']],
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'name' => $variantData['name'],
                        'source' => ProductSource::Manual,
                        'available_stock' => $stock,
                        'reserved_stock' => 0,
                        'on_hand_stock' => $stock,
                        'locked_stock' => 0,
                        'shipped_stock' => 0,
                        'safety_stock' => 5,
                        'price' => $variantData['price'],
                        'cost_price' => $item['cost_price'],
                        'sync_state' => SyncState::Pending,
                    ],
                );

                // Ảnh chụp tồn kho ban đầu.
                InventorySnapshot::updateOrCreate(
                    [
                        'sku_id' => $sku->id,
                        'sync_run_id' => 'seed-'.now()->format('YmdHis'),
                    ],
                    [
                        'warehouse_id' => $warehouse->id,
                        'sku' => $sku->sku,
                        'available' => $stock,
                        'reserved' => 0,
                        'on_hand' => $stock,
                        'locked' => 0,
                        'shipped' => 0,
                        'captured_at' => now(),
                    ],
                );
            }
        }
    }
}
