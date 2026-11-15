<?php

use App\Jobs\RetryBigSellerSyncJob;
use App\Jobs\SyncBigSellerInventoryJob;
use App\Jobs\SyncBigSellerOrderStatusJob;
use App\Jobs\SyncBigSellerProductsJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Lịch chạy tự động (Scheduler)
|--------------------------------------------------------------------------
|
| Yêu cầu:
|   - dev:        php artisan schedule:work
|   - production: cron * * * * * php artisan schedule:run
|
| Các job đều tự dispatch vào queue (database), nên không cần runInBackground.
|
*/

// Đồng bộ tồn kho BigSeller theo chu kỳ cấu hình (mặc định 15 phút).
Schedule::job(new SyncBigSellerInventoryJob)
    ->cron('*/'.max(1, (int) config('bigseller.inventory_sync_every_minutes', 15)).' * * * *')
    ->withoutOverlapping()
    ->onOneServer();

// Đồng bộ trạng thái đơn hàng (mỗi 10 phút).
Schedule::job(new SyncBigSellerOrderStatusJob)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Thử lại các đơn đồng bộ lỗi (mỗi 30 phút).
Schedule::job(new RetryBigSellerSyncJob)
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Đồng bộ sản phẩm (chỉ hiệu lực khi có quyền API sản phẩm) - 1 lần/ngày 2h sáng.
Schedule::job(new SyncBigSellerProductsJob)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
