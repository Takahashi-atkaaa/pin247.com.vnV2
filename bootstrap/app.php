<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

require_once __DIR__.'/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'auth.api' => \App\Http\Middleware\AuthenticateApi::class,
        ]);

        // ---------------------------------------------------------------
        // QUAN TRỌNG — ghi đè `redirectGuestsTo` mặc định của Laravel.
        //
        // Laravel 12 mặc định đăng ký:
        //     ->redirectGuestsTo(fn () => route('login'))
        // Ứng dụng này là SPA, KHÔNG có route tên `login` ở phía server =>
        // `route('login')` ném RouteNotFoundException NGAY TRONG middleware
        // `auth:sanctum` (trước cả bước render exception) => HTTP 500.
        //
        // - Request API: trả `null` => Laravel dựng response JSON 401.
        // - Request web: chuyển hướng về `/` (SPA tự xử lý client-side).
        // ---------------------------------------------------------------
        $middleware->redirectGuestsTo(function (Request $request) {
            if (is_api_request($request) || $request->expectsJson()) {
                return null;
            }

            return url('/');
        });

        // Rate limiting cho API.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Trả lỗi BigSeller dưới dạng JSON chuẩn cho frontend.
        $exceptions->render(function (\App\Exceptions\BigSellerConfigurationException $e, Request $request) {
            if (is_api_request($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => 'BIGSELLER_NOT_CONFIGURED',
                ], 503);
            }
        });

        $exceptions->render(function (\App\Exceptions\BigSellerApiException $e, Request $request) {
            if (is_api_request($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => $e->errorCode,
                ], 502);
            }
        });

        // API chưa đăng nhập => luôn trả 401 JSON.
        //
        // `shouldRenderJsonWhen` buộc mọi lỗi của request API được render
        // dạng JSON, nên `unauthenticated()` trả JSON 401 thay vì redirect
        // (và không bao giờ chạm tới `route('login')`).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e) => is_api_request($request) || $request->expectsJson()
        );

        // Message rõ ràng hơn cho API.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if (is_api_request($request)) {
                return response()->json([
                    'message' => 'Chưa đăng nhập.',
                ], 401);
            }
        });
    })->create();
