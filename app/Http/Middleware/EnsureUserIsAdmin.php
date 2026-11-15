<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ Admin mới truy cập được khu vực quản trị.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            // Mọi request tới API luôn nhận JSON 403 (kể cả khi client
            // không gửi header `Accept: application/json`) — tránh trả về
            // trang HTML lỗi khiến frontend không parse được.
            if (is_api_request($request) || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Bạn không có quyền truy cập khu vực quản trị.',
                ], 403);
            }

            abort(403, 'Bạn không có quyền truy cập khu vực quản trị.');
        }

        return $next($request);
    }
}
