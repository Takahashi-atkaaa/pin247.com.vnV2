<?php

namespace Database\Seeders;

use App\Models\BigSellerConnection;
use App\Models\BigSellerWarehouseMapping;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class BigSellerConnectionSeeder extends Seeder
{
    /**
     * Tạo kết nối BigSeller mặc định ở chế độ MOCK.
     *
     * Ở chế độ mock, `access_token`/`app_key` là giá trị giả và sẽ được
     * mã hoá at-rest. Khi chuyển sang production, cần cập nhật lại tài khoản
     * với app_id/app_key/access_token thật từ BigSeller Open API.
     */
    public function run(): void
    {
        $connection = BigSellerConnection::updateOrCreate(
            ['name' => 'BigSeller (mặc định)'],
            [
                'app_id' => 'PIN247-DEMO-APP',
                'app_key' => 'mock-app-key',
                'access_token' => 'mock-access-token',
                'refresh_token' => 'mock-refresh-token',
                'token_expires_at' => now()->addDays(30),
                'capabilities' => [
                    'inventory',
                    'create_order',
                    'cancel_order',
                    'order_status',
                ],
                'shop_code' => 'PIN247',
                'shop_name' => 'PIN247 Official Store',
                'is_active' => true,
                'is_mock' => true,
            ],
        );

        // Kho mặc định + ánh xạ kho BigSeller.
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'KHO-HN'],
            [
                'name' => 'Kho chính Hà Nội',
                'address' => 'Số 1 Đường Láng, Đống Đa, Hà Nội',
                'phone' => '0900000000',
                'is_active' => true,
            ],
        );

        $connection->forceFill(['default_warehouse_id' => $warehouse->id])->save();

        BigSellerWarehouseMapping::updateOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'bigseller_warehouse_code' => 'WH-HN-01',
            ],
            [
                'bigseller_warehouse_id' => 1,
                'bigseller_warehouse_name' => 'Kho Hà Nội (BigSeller)',
                'is_active' => true,
            ],
        );
    }
}
