<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed dữ liệu khởi tạo cho PIN247.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            CategorySeeder::class,
            AdminUserSeeder::class,
            BigSellerConnectionSeeder::class,
            ProductSeeder::class,
            PriceTierSeeder::class,
            ProductAttributeBackfillSeeder::class,
            ProductUnitSeeder::class,
        ]);
    }
}
