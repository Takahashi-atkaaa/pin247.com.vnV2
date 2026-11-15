<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực API bằng Sanctum (Bearer token) hoặc session.
 *
 * Trả 401 JSON thay vì redirect để frontend xử lý đúng.
 */
class AuthenticateApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return response()->json([
                'message' => 'Chưa đăng nhập.',
            ], 401);
        }

        return $next($request);
    }
}
