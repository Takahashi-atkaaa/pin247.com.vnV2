<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{--
        Base path mà ứng dụng đang được mount (ví dụ `/duanma` khi chạy dưới
        XAMPP, hoặc rỗng khi chạy ở domain gốc). Frontend đọc giá trị này để
        dựng đúng URL cho Vue Router và API.
    --}}
    <meta name="app-base" content="{{ $appBase ?? '' }}">
    <title>PIN247</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-900 antialiased">
    <div id="app"></div>
</body>
</html>
