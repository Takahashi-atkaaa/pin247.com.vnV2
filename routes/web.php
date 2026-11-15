<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — PIN247 (SPA)
|--------------------------------------------------------------------------
|
| Toàn bộ giao diện Vue 3 chạy ở client-side. Route duy nhất trả về
| blade "app" là điểm khởi động; các đường dẫn con được xử lý bởi
| Vue Router (history mode).
|
| `.*` cho phép khớp nhiều cấp (ví dụ /duanma/products/pin-18650) vì
| ứng dụng có thể được cài trong thư mục con dưới XAMPP.
|
*/

Route::get('/{any}', function () {
    return view('app', [
        'appBase' => rtrim(request()->getBasePath(), '/'),
    ]);
})->where('any', '^(?!api|storage|build).*$');
