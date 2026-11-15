<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chế độ làm việc
    |--------------------------------------------------------------------------
    |
    | "mock" : dùng MockBigSellerClient (CHỈ dành cho development / test).
    | "live" : dùng HttpBigSellerClient gọi API thật của BigSeller.
    |
    | TUYỆT ĐỐI không dùng "mock" trên production. Trạng thái mock luôn được
    | hiển thị rõ ràng trong Admin UI và trong API response.
    |
    */

    'mode' => env('BIGSELLER_MODE', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Thông tin xác thực
    |--------------------------------------------------------------------------
    |
    | Chỉ điền các giá trị BigSeller cung cấp sau khi tài khoản được duyệt
    | quyền truy cập Open API. Không tự bịa giá trị.
    |
    */

    'app_id' => env('BIGSELLER_APP_ID'),
    'app_key' => env('BIGSELLER_APP_KEY'),
    'access_token' => env('BIGSELLER_ACCESS_TOKEN'),
    'refresh_token' => env('BIGSELLER_REFRESH_TOKEN'),
    'token_expires_at' => env('BIGSELLER_TOKEN_EXPIRES_AT'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint
    |--------------------------------------------------------------------------
    |
    | Các đường dẫn API của BigSeller KHÔNG được công khai tự do. Chỉ điền
    | sau khi đã nhận tài liệu API chính thức từ BigSeller.
    | Nếu để trống, service sẽ ném BigSellerConfigurationException
    | thay vì gọi một URL tự bịa.
    |
    */

    'base_url' => env('BIGSELLER_BASE_URL'),

    'endpoints' => [
        'inventory' => env('BIGSELLER_PATH_INVENTORY'),
        'create_order' => env('BIGSELLER_PATH_CREATE_ORDER'),
        'cancel_order' => env('BIGSELLER_PATH_CANCEL_ORDER'),
        'order_status' => env('BIGSELLER_PATH_ORDER_STATUS'),
        'products' => env('BIGSELLER_PATH_PRODUCTS'),
        'warehouses' => env('BIGSELLER_PATH_WAREHOUSES'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Năng lực được cấp
    |--------------------------------------------------------------------------
    |
    | Danh sách API mà tài khoản được phép gọi. Admin chỉ bật được những
    | capability thực sự đã được BigSeller cấp quyền.
    |
    */

    'capabilities' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BIGSELLER_CAPABILITIES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('BIGSELLER_TIMEOUT', 20),
    'connect_timeout' => (int) env('BIGSELLER_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Nghiệp vụ
    |--------------------------------------------------------------------------
    */

    'inventory_sync_every_minutes' => (int) env('BIGSELLER_INVENTORY_SYNC_EVERY', 15),

    'safety_stock' => (int) env('BIGSELLER_SAFETY_STOCK', 0),

    /*
    |--------------------------------------------------------------------------
    | Mock (development only)
    |--------------------------------------------------------------------------
    */

    'mock' => [
        // Số lượng tồn kho ngẫu nhiên trả về cho SKU không có dữ liệu mô phỏng
        'default_stock' => 100,
        // Cố ý mô phỏng timeout để kiểm thử (chỉ trong dev)
        'simulate_timeout' => (bool) env('BIGSELLER_MOCK_SIMULATE_TIMEOUT', false),
    ],

];
