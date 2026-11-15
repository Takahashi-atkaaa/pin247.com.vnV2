<?php

namespace App\Providers;

use App\Services\BigSeller\Contracts\BigSellerClient;
use App\Services\BigSeller\HttpBigSellerClient;
use App\Services\BigSeller\MockBigSellerClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mock client là singleton để test có thể cấu hình tồn kho / lỗi
        // trước khi gọi service.
        $this->app->singleton(MockBigSellerClient::class, fn () => new MockBigSellerClient);

        // Bind BigSeller client theo chế độ cấu hình.
        //
        // 'mock' -> MockBigSellerClient (CHỈ development / test)
        // 'live' -> HttpBigSellerClient (production, cần endpoint thật)
        $this->app->singleton(BigSellerClient::class, function ($app) {
            $mode = config('bigseller.mode', 'mock');

            if ($mode === 'live') {
                return new HttpBigSellerClient($app->make(\App\Services\BigSeller\BigSellerAuthService::class));
            }

            return $app->make(MockBigSellerClient::class);
        });

        // Cho phép test inject client giả lập cụ thể.
        $this->app->bind(
            \App\Services\BigSeller\BigSellerAuthService::class,
            fn ($app) => new \App\Services\BigSeller\BigSellerAuthService,
        );
    }

    public function boot(): void
    {
        // Định nghĩa rate limiter "api" dùng chung cho toàn bộ endpoint.
        //
        // Route-level throttle (vd. 'throttle:10,1') vẫn được áp dụng riêng,
        // nhưng limiter "api" là mặc định của middleware throttleApi().
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Rate limiter cho các endpoint nhạy cảm (đăng nhập / đăng ký).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });
    }
}
