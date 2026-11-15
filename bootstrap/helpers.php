<?php

use Illuminate\Http\Request;

if (! function_exists('is_api_request')) {
    /**
     * Kiểm tra request có thuộc API hay không.
     *
     * LƯU Ý QUAN TRỌNG: phải dùng pattern có tiền tố `*` thay vì chỉ
     * `api/*`.
     *
     * `Request::path()` trả về path KHÔNG có base path. Khi ứng dụng nằm
     * trong thư mục con (ví dụ XAMPP: `/duanma/api/products`), `path()` trả
     * về `duanma/api/products` => pattern `api/*` KHÔNG match.
     *
     * Trên InfinityFree (domain gốc) `path()` là `api/products` nên pattern
     * `api/*` match. Dùng pattern có tiền tố `*` để hợp lệ cho CẢ HAI
     * trường hợp.
     */
    function is_api_request(Request $request): bool
    {
        return $request->is('api/*') || $request->is('*/api/*');
    }
}
