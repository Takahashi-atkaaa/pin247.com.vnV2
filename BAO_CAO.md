# BÁO CÁO DỰ ÁN PIN247

**Ngày lập báo cáo:** 13/11/2026
**Dự án:** Hệ thống thương mại điện tử PIN247
**Địa chỉ:** `/Applications/XAMPP/xamppfiles/htdocs/duanma`
**Trạng thái:** ✅ Hoàn thành — đang chạy thật tại `http://127.0.0.1/duanma/`

---

## MỤC LỤC

1. [Tổng quan](#1-tổng-quan)
2. [Kiến trúc hệ thống](#2-kiến-trúc-hệ-thống)
3. [Những gì đã thực hiện](#3-những-gì-đã-thực-hiện)
4. [Tích hợp BigSeller](#4-tích-hợp-bigseller)
5. [Kiến trúc chống oversell](#5-kiến-trúc-chống-oversell)
6. [Cơ sở dữ liệu](#6-cơ-sở-dữ-liệu)
7. [API](#7-api)
8. [Frontend Vue 3](#8-frontend-vue-3)
9. [Jobs & Scheduler](#9-jobs--scheduler)
10. [Kiểm thử](#10-kiểm-thử)
11. [Lỗi đã phát hiện & sửa](#11-lỗi-đã-phát-hiện--sửa)
12. [Kết quả kiểm chứng end-to-end](#12-kết-quả-kiểm-chứng-end-to-end)
13. [Cách chạy dự án](#13-cách-chạy-dự-án)
14. [Chuyển sang BigSeller thật](#14-chuyển-sang-bigseller-thật)
15. [Tồn đọng & việc cần làm tiếp](#15-tồn-đọng--việc-cần-làm-tiếp)

---

## 1. TỔNG QUAN

### 1.1. Thông tin tổng quan

| Hạng mục | Con số |
|---|---|
| PHP (backend + tests) | **10.013 dòng** |
| JS/Vue (frontend) | **5.040 dòng** |
| Eloquent models | 19 |
| Controllers | 13 |
| Middleware | 2 |
| Form Request | 1 |
| API Resource | 3 |
| Enums | 7 |
| Jobs | 5 |
| Migrations | 24 file / **19 bảng** |
| Seeders | 6 |
| Vue pages | 15 (9 storefront + 6 admin) |
| Vue components | 15 |
| Vue stores (Pinia) | 3 |
| API routes | **52** |
| Test | **51 test / 224 assertions — PASS** |

### 1.2. Công nghệ sử dụng

| Tầng | Công nghệ |
|---|---|
| Frontend | Vue 3 (Composition API), Vite 7, Pinia 3, Vue Router 4, Tailwind CSS 4, Axios |
| Backend | Laravel 12, PHP 8.2+ (khuyến nghị 8.4), Laravel Sanctum 4 |
| Database | MySQL 8 — `pin247_shop`, charset `utf8mb4` |
| Hàng đợi | Laravel Queue (driver `database`) |
| Cache/Session | Database driver |
| Lập lịch | Laravel Scheduler (cron) |
| Môi trường | XAMPP (Apache + MySQL) |

### 1.3. Phạm vi nghiệp vụ

Thương hiệu **PIN247** chuyên: pin lithium, pin máy khoan, cell 18650, cell 21700,
mạch BMS, bộ sạc pin, linh kiện đóng pin, phụ kiện.

Hệ thống gồm hai cổng:

- **Storefront** — khách hàng duyệt sản phẩm, thêm giỏ, thanh toán, tra cứu đơn.
- **Admin portal** — quản lý sản phẩm, tồn kho, đơn hàng, khách hàng, và điều khiển
  đồng bộ với BigSeller (kho tập trung).

---

## 2. KIẾN TRÚC HỆ THỐNG

```
┌────────────────────────── Browser ───────────────────────────┐
│  Vue 3 SPA (Vite)                                            │
│  ├─ Storefront: Home / Products / Detail / Cart /            │
│  │               Checkout / OrderTracking / Login / Register  │
│  └─ Admin:     Dashboard / Products / Inventory /            │
│                Orders / Customers / BigSeller                 │
│  Pinia stores: auth · cart · catalog                         │
└─────────────────────────────┬─────────────────────────────────┘
                              │ HTTP + Sanctum Bearer token
┌─────────────────────────────▼─────────────────────────────────┐
│  Laravel 12                                                    │
│  ├─ routes/api.php  → Controller → API Resource               │
│  ├─ Middleware: AuthenticateApi · EnsureUserIsAdmin · CORS    │
│  │                  RateLimiter (api 120/phút, login 10/phút) │
│  ├─ Domain services → OrderService · InventoryService ·       │
│  │                     ProductService                           │
│  ├─ BigSeller facade → BigSellerService                        │
│  │      └─ Contracts\BigSellerClient  (interface DUY NHẤT)     │
│  │            ├─ HttpBigSellerClient   (API thật)              │
│  │            └─ MockBigSellerClient   (dev / test)            │
│  ├─ Jobs (queue) → 5 BigSeller jobs                            │
│  └─ Scheduler → cron định kỳ                                   │
└─────────────────────────────┬─────────────────────────────────┘
                              │ PDO
                 ┌────────────▼────────────┐        ┌──────────────┐
                 │  MySQL                  │        │  BigSeller   │
                 │  pin247_shop (19 bảng) │        │  Open API    │
                 └─────────────────────────┘        └──────────────┘
```

### 2.1. Nguyên tắc kiến trúc

| Nguyên tắc | Thực thi |
|---|---|
| Contract duy nhất | Toàn bộ ứng dụng chỉ phụ thuộc `Contracts\BigSellerClient`. Không service nào gọi trực tiếp `HttpBigSellerClient`. |
| Chuyển chế độ | `BIGSELLER_MODE=mock\|live`, bind trong `AppServiceProvider` |
| Không bịa endpoint | Mọi path lấy từ biến môi trường. Thiếu → `BigSellerConfigurationException` (HTTP 503) |
| Không đoán trạng thái | Mapper trả `null` khi chưa biết → giữ nguyên trạng thái nội bộ |
| Không rò token | Log chỉ ghi request/response đã che; không ghi `app_key`, `access_token` |
| Mock luôn minh bạch | Response có `mode`, `is_mock`; Admin UI hiện banner cảnh báo |
| Không trừ đôi tồn kho | `sellable` chỉ trừ `local_reserved_stock` + `safety_stock`, **không** trừ `reserved_stock` |

---

## 3. NHỮNG GÌ ĐÃ THỰC HIỆN

### 3.1. Nền tảng & cấu hình

- Khởi tạo Laravel 12 (`composer create-project laravel/laravel:^12.0 .`) — 82 package,
  không có cảnh báo bảo mật, `APP_KEY` đã sinh.
- `.env` trỏ MySQL `pin247_shop` / `utf8mb4`; `QUEUE_CONNECTION=database`,
  `CACHE_STORE=database`, `SESSION_DRIVER=database`.
- `config/bigseller.php` — file cấu hình duy nhất cho toàn bộ tích hợp BigSeller:
  mode, endpoint, capability, timeout, mock options.
- `bootstrap/app.php` — khai báo middleware `admin` / `auth.api`, tiền hạ tầm CORS,
  và render lỗi BigSeller dạng JSON (`502` cho API error, `503` cho chưa cấu hình).
- **Ghim các gói Symfony ở `^7.4`** (`clock`, `css-selector`, `event-dispatcher`,
  `string`, `translation`) → dự án chạy được trên **cả** PHP 8.4 (CLI) lẫn PHP 8.2
  (Apache XAMPP). Đây là fix bắt buộc vì Laravel 12 mặc định đòi `php >= 8.4.1`.

### 3.2. Models (19)

| Nhóm | Model |
|---|---|
| Người dùng | `User` (có `is_admin`), `PersonalAccessToken` |
| Danh mục / SP | `Category`, `Product`, `ProductVariant`, `ProductImage`, `Sku` |
| Kho | `Warehouse`, `InventorySnapshot`, `InventoryLog` |
| Bán hàng | `Customer`, `Order`, `OrderItem`, `Payment` |
| BigSeller | `BigSellerConnection`, `BigSellerProductMapping`, `BigSellerWarehouseMapping`, `BigSellerOrderMapping`, `BigSellerSyncLog` |
| Hệ thống | `Setting` |

Ghi chú kỹ thuật: các bảng BigSeller dùng tên tiền tố `bigseller_` (không phải
`big_seller_`) → phải khai báo `protected $table` tường minh cho từng model.

### 3.3. Enums (7)

| Enum | Giá trị |
|---|---|
| `OrderSyncStatus` | `PENDING_SYNC`, `SYNCING`, `SYNCED`, `SYNC_FAILED`, `CANCEL_PENDING`, `CANCELLED`, `PROCESSING`, `SHIPPED`, `COMPLETED` |
| `PaymentStatus` | `UNPAID`, `PENDING`, `PAID`, `FAILED`, `REFUNDED`, `PARTIALLY_REFUNDED` |
| `PaymentMethod` | `COD`, `BANK_TRANSFER` |
| `ProductSource` | `MANUAL`, `BIGSELLER`, `IMPORTED` |
| `SyncState` | `PENDING`, `SYNCING`, `SYNCED`, `FAILED` |
| `InventoryQuantityType` | `AVAILABLE`, `RESERVED`, `ON_HAND`, `LOCKED`, `SHIPPED` |
| `ImportType` | `CATEGORIES`, `PRODUCTS`, `SKU_MAPPING`, `INVENTORY` |

Mỗi enum có method `label()` trả về nhãn tiếng Việt dùng hiển thị trên UI.

### 3.4. Domain services

| Service | Trách nhiệm |
|---|---|
| `OrderService` | Tạo đơn (khoá row, giữ chỗ tồn kho), hủy đơn, giải phóng reservation, đẩy lên BigSeller |
| `InventoryService` | Kiểm tra khả dụng, đồng bộ tồn kho, ghi snapshot + log, SKU sắp hết |
| `ProductService` | Truy vấn & quản lý sản phẩm |
| `BigSellerService` | Facade: `status()`, `capabilities()`, `requireCapability()`, `recentLogs()` |

### 3.5. Middleware & Request

| Class | Vai trò |
|---|---|
| `AuthenticateApi` | Xác thực token Sanctum cho API → trả JSON 401 thay vì redirect |
| `EnsureUserIsAdmin` | Chặn non-admin truy cập nhóm route `/api/admin/*` |
| `CheckoutRequest` | Validate giỏ + thông tin khách + phương thức thanh toán, kèm message tiếng Việt |

---

## 4. TÍCH HỢP BIGSELLER

### 4.1. Cấu trúc lớp

```
app/Services/BigSeller/
├── Contracts/BigSellerClient.php   ← interface DUY NHẤT
├── Data/
│   ├── InventoryItem.php            (sku, available, reserved, onHand, locked, shipped, warehouseCode)
│   ├── ManualOrderResult.php        (success, bigSellerOrderCode, status, errorCode, errorMessage, raw)
│   ├── OrderStatusResult.php
│   ├── ProductData.php
│   └── WarehouseData.php
├── HttpBigSellerClient.php          ← gọi API thật qua Laravel Http facade
├── MockBigSellerClient.php          ← dev/test, mô phỏng timeout + lỗi nghiệp vụ
├── BigSellerAuthService.php         ← capability + token + auth headers
├── BigSellerInventoryService.php
├── BigSellerOrderService.php
├── BigSellerProductService.php
└── BigSellerStatusMapper.php
```

### 4.2. Interface `BigSellerClient`

```php
interface BigSellerClient
{
    public function checkConnection(): array;
    public function getInventoryListDetail(array $params): array;
    public function createManualOrder(array $payload): ManualOrderResult;
    public function cancelOrder(array $params): OrderStatusResult;
    public function getOrderStatus(array $params): OrderStatusResult;
    public function getProducts(array $params): array;
    public function getWarehouses(array $params): array;
}
```

Mỗi method được ghi chú rõ **chỉ gọi được khi capability tương ứng đã được cấp quyền**.

### 4.3. Bảng ánh xạ trạng thái

Nằm duy nhất trong `BigSellerStatusMapper`:

| BigSeller | Nội bộ (`OrderSyncStatus`) |
|---|---|
| `PENDING` | `PENDING_SYNC` |
| `PENDING_CONFIRM`, `WAITING_STOCK` | `PROCESSING` |
| `PROCESSING`, `PICKING`, `PICKED`, `PACKED`, `READY_TO_SHIP`, `AWAITING_SHIP` | `PROCESSING` |
| `SHIPPED`, `SHIPPING`, `DELIVERING`, `IN_TRANSIT` | `SHIPPED` |
| `COMPLETED`, `DELIVERED`, `SUCCESS`, `FINISHED` | `COMPLETED` |
| `CANCELLED`, `CANCELED` | `CANCELLED` |
| `FAILED`, `ERROR` | `SYNC_FAILED` |

Hành vi:

- `map()` → trả `null` + ghi log cảnh báo nếu trạng thái chưa biết (không đoán).
- `mapOrKeep($external, $current)` → giữ nguyên trạng thái hiện tại nếu không ánh xạ được.
- Trạng thái không phân biệt hoa/thường (chuẩn hoá `strtoupper(trim(...))`).

**Tiêu chí "đẩy đơn thành công"** là đã có `bigseller_order_code` — **không** phải
`sync_status === SYNCED`, vì BigSeller có thể trả trạng thái đầu vào khác
(vd. `PENDING_CONFIRM`) dù đơn đã tạo thành công.

### 4.4. Idempotency & chống trùng lặp

| Vấn đề | Cơ chế |
|---|---|
| Đẩy đơn lên BigSeller 2 lần | `BigSellerOrderMapping` unique theo `order_id`; **kiểm tra mapping trước khi gọi API**; nếu đã có mã → bỏ qua, trả `duplicate: true` |
| Timeout không rõ đã tạo hay chưa | Retry dùng lại **cùng** `idempotency_key` → BigSeller từ chối tạo trùng |
| Đồng bộ tồn kho nhiều lần | `bigseller_sync_logs` lưu `run_id` (uuid) + `operation` + `status` + `duration_ms` |
| Đồng bộ sản phẩm trùng SKU | `bigseller_product_mappings` unique theo `sku` / `bigseller_product_code` |
| SKU không tồn tại trên BigSeller | Đánh dấu cảnh báo `missing_skus`, **không** ghi 0 (tránh mất hàng oops) |
| Job chạy chồng nhau | `WithoutOverlapping(...)->dontRelease()` trên từng job |

### 4.5. Mock client

`MockBigSellerClient` mô phỏng đầy đủ để test không cần quyền API thật:

- Tồn kho theo SKU (`setStock`), SKU không tồn tại (`markMissingSku`).
- Timeout (`simulateTimeoutOnNextCall`) và lỗi nghiệp vụ (`simulateErrorOnNextCall`).
- Tự sinh mã đơn `BS-MOCK-000001`… và **tự chống trùng** theo `idempotency_key`.
- Mọi lời gọi đều ghi vào `bigseller_sync_logs` với `request_url = mock://...`.

---

## 5. KIẾN TRÚC CHỐNG OVERSELL

### 5.1. Ba lớp số lượng tồn kho

| Trường | Ý nghĩa | Nguồn |
|---|---|---|
| `available_stock` | Tồn kho khả dụng thực tế | Đồng bộ từ BigSeller (source of truth) |
| `reserved_stock` | Lượng BigSeller đã giữ | Đồng bộ từ BigSeller |
| `local_reserved_stock` | Lượng website đang giữ tạm | `OrderService` (giữ ngắn hạn) |
| `safety_stock` | Ngưỡng an toàn tự cấu hình | `settings` / `BIGSELLER_SAFETY_STOCK` |

### 5.2. Công thức

\[
\text{sellableQuantity()} = \max(0,\ \text{available\_stock} - \text{local\_reserved\_stock} - \text{safety\_stock})
\]

> **Không trừ `reserved_stock`** — BigSeller đã trừ sẵn khỏi `available`, trừ thêm là tính đôi.

### 5.3. Luồng đặt hàng (`OrderService::createOrder`)

| Bước | Hành động | Mục đích |
|---|---|---|
| 1 | Chuẩn hóa & gộp dòng trùng SKU | Tránh gom/trừ sai |
| 2 | Kiểm tra SKU tồn tại | Chặn SKU ảo |
| 3 | Kiểm tra tồn kho cache (`checkAvailability`) | Phản hồi nhanh |
| 4 | **Kiểm tra trực tiếp BigSeller** (`checkAvailable`) | Giảm rủi ro do sync định kỳ bị trễ |
| 4a | Không lấy được dữ liệu BigSeller | Đơn vẫn tạo ở trạng thái *chờ đồng bộ* + `warning` cho khách — **không** coi là còn hàng âm thầm |
| 5 | **Transaction + `lockForUpdate()`** | Hai request đặt hàng đồng thời không cùng đọc giá trị tồn kho cũ; cộng `local_reserved_stock` |
| 6 | Tạo `Order` (`PENDING_SYNC`) + `OrderItem` + `Payment` | Nghiệp vụ đơn hàng |
| 7 | Cập nhật `Customer.total_orders` / `total_spent` | Thống kê khách |

### 5.4. Vòng đời reservation

```
Đặt hàng ──► local_reserved_stock += qty  (trong transaction, row-locked)
     │
     ├─► Đẩy BigSeller OK ──► giữ nguyên (BigSeller sẽ xác nhận lại qua sync tồn kho)
     │
     └─► Hủy đơn ──► releaseLocalReservation() ──► local_reserved_stock -= qty
```

### 5.5. Kiểm thử chống oversell

Test `concurrent order placements do not oversell` mô phỏng 2 request đặt hàng
cùng lúc trên cùng SKU → chỉ một đơn được chấp nhận, tổng bán ≤ tồn kho.

---

## 6. CƠ SỞ DỮ LIỆU

### 6.1. 19 bảng

| Nhóm | Bảng |
|---|---|
| Người dùng | `users` (+ cột `is_admin`), `personal_access_tokens` |
| Danh mục / SP | `categories`, `products`, `product_variants`, `product_images`, `skus` |
| Kho | `warehouses`, `inventory_snapshots`, `inventory_logs` |
| Bán hàng | `customers`, `orders`, `order_items`, `payments` |
| BigSeller | `bigseller_connections`, `bigseller_product_mappings`, `bigseller_warehouse_mappings`, `bigseller_order_mappings`, `bigseller_sync_logs` |
| Hệ thống | `settings`, `cache`, `jobs` |

### 6.2. Migration đáng chú ý

| File | Nội dung |
|---|---|
| `2026_11_12_161800_add_admin_fields_to_users_table.php` | Thêm `is_admin` |
| `2026_11_12_162000_add_local_reserved_stock_to_skus_table.php` | Thêm `local_reserved_stock` — trụ cột của cơ chế chống oversell |

### 6.3. Seeders

| Seeder | Nội dung |
|---|---|
| `SettingSeeder` | Ngưỡng tồn kho, chu kỳ sync, phí vận chuyển |
| `CategorySeeder` | 8 danh mục (Pin máy khoan, Pin lithium, Cell 18650, Cell 21700, Mạch BMS, Bộ sạc pin, Linh kiện đóng pin, Phụ kiện) |
| `AdminUserSeeder` | `admin@pin247.vn` / `admin1234` |
| `BigSellerConnectionSeeder` | Kết nối mock + mapping kho `WH-HN-01` |
| `ProductSeeder` | 14 sản phẩm thật + biến thể + SKU + snapshot tồn kho |

---

## 7. API

### 7.1. Storefront (public)

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/products` | Danh sách + tìm kiếm + lọc (danh mục, thương hiệu, giá, thuộc tính, tồn kho) + sắp xếp + phân trang |
| GET | `/api/products/{slug}` | Chi tiết + biến thể + SKU + ảnh + thông số + sản phẩm liên quan |
| GET | `/api/categories` | Cây danh mục (chỉ nhóm cha + con active) |
| GET | `/api/stock` | Số lượng bán được theo danh sách SKU |
| POST | `/api/cart/validate` | Kiểm tra giỏ (tồn kho, giá) — throttle 30/phút |
| POST | `/api/checkout` | Tạo đơn hàng — throttle 10/phút |
| POST | `/api/orders/track` | Tra cứu đơn bằng mã + SĐT — throttle 20/phút |

### 7.2. Xác thực

| Method | Path | Mô tả |
|---|---|---|
| POST | `/api/auth/register` | Đăng ký — throttle 6/phút |
| POST | `/api/auth/login` | Đăng nhập → trả Sanctum token |
| POST | `/api/auth/logout` | Thu hồi token hiện tại |
| GET | `/api/me` | Thông tin user đang đăng nhập |

### 7.3. Admin (`auth:sanctum` + `admin`)

| Nhóm | Endpoints |
|---|---|
| Dashboard | `GET /api/admin/dashboard` |
| Sản phẩm | `GET`/`POST` `/api/admin/products` · `GET`/`PUT`/`DELETE` `/{id}` · `POST`/`DELETE` `/{id}/images` |
| Danh mục | `GET`/`POST` `/api/admin/categories` · `PUT`/`DELETE` `/{id}` |
| Tồn kho | `GET /api/admin/inventory` · `POST /sync` · `POST /sync/dispatch` · `GET /history/{sku}` · `GET /warehouses` |
| Đơn hàng | `GET /api/admin/orders` · `GET /{id}` · `POST /{id}/resync` · `POST /{id}/push` · `POST /{id}/cancel` · `POST /{id}/refresh-status` · `PUT /{id}/note` |
| Khách hàng | `GET /api/admin/customers` · `GET /{id}` |
| BigSeller | `GET /status` · `POST /check-connection` · `POST /sync/inventory` · `POST /sync/inventory/dispatch` · `POST /sync/products` · `POST /sync/products/dispatch` · `POST /sync/orders` · `GET /reconcile-skus` · `GET /logs` · `GET`/`POST` `/sku-mappings` · `GET`/`PUT` `/schedule` |

### 7.4. Quy ước response

```jsonc
// Resource đơn giản
{ "data": { ... } }

// Resource collection có phân trang
{ "data": [...], "links": {...}, "meta": {...} }

// Lỗi validation
{ "message": "...", "errors": { "field": ["..."] } }

// Lỗi BigSeller
{ "message": "...", "error_code": "TIMEOUT" }   // 502
{ "message": "...", "error_code": "BIGSELLER_NOT_CONFIGURED" }  // 503
```

### 7.5. Rate limiting

| Limiter | Giới hạn |
|---|---|
| `api` (mặc định `throttleApi()`) | 120 req/phút / user hoặc IP |
| `login` | 10 req/phút / email+IP |
| `checkout` | 10 req/phút |
| `cart/validate` | 30 req/phút |
| `orders/track` | 20 req/phút |
| `auth/register`, `auth/login` | 6 req/phút |

---

## 8. FRONTEND VUE 3

### 8.1. Cấu trúc

```
resources/js/
├── app.js                 # entry point
├── bootstrap.js
├── config.js              # APP_BASE động (đọc <meta name="app-base">)
├── router/index.js        # route cha storefront + admin (guard auth/admin/guest)
├── services/api.js        # axios instance + interceptor 401
├── stores/                # auth · cart · catalog (Pinia)
├── composables/useFormat.js
├── layouts/               # StorefrontLayout · AdminLayout (dùng <RouterView />)
├── components/            # 15 component (7 dùng chung + 8 admin)
└── pages/                 # 15 page (9 storefront + 6 admin)
```

### 8.1.1. Cấu trúc route & layout

Router dùng mô hình **route cha + children** để gắn layout:

```js
// resources/js/router/index.js
{
    path: '/',
    component: () => import('../layouts/StorefrontLayout.vue'),
    children: [
        { path: '', name: 'home', component: () => import('../pages/HomePage.vue') },
        { path: 'products', name: 'products', ... },
        { path: 'cart', name: 'cart', ... },
        // ...
        { path: ':pathMatch(.*)*', name: 'not-found', ... },
    ],
},
{
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [ /* 6 trang admin */ ],
},
```

> **Lưu ý quan trọng:** route con **không** có dấu `/` ở đầu vì kế thừa path của route cha.
> Nếu viết `'/products'` thì URL sẽ ra `/products` thay vì `/duanma/products`.

**Layout PHẢI dùng `<RouterView />`, không dùng `<slot />`.** Vue Router 4 không tự render
route con vào slot của route cha — dùng `<slot />` sẽ khiến phần nội dung trang **rỗng**
(header/footer vẫn hiện nhưng `<main>` trống).

```vue
<!-- resources/js/layouts/StorefrontLayout.vue -->
<template>
    <div class="min-h-screen flex flex-col bg-white">
        <AppHeader />
        <main class="flex-1">
            <RouterView />
        </main>
        <AppFooter />
    </div>
</template>
```

### 8.2. Storefront (9 trang)

| Trang | Chức năng |
|---|---|
| `HomePage` | Hero, danh mục nổi bật, sản phẩm mới/bán chạy, chính sách |
| `ProductListPage` | Grid + lọc (danh mục, giá, thương hiệu, tồn kho) + sắp xếp + phân trang |
| `ProductDetailPage` | Chọn biến thể, ảnh, tồn kho, thông số, sản phẩm liên quan |
| `CartPage` | Giỏ hàng, cập nhật số lượng, gợi ý sản phẩm |
| `CheckoutPage` | Form thông tin + phương thức thanh toán + kiểm tra tồn kho trước khi gửi |
| `OrderTrackingPage` | Tra cứu bằng mã đơn + SĐT |
| `LoginPage` / `RegisterPage` | Xác thực (nằm trong `StorefrontLayout` — có header/footer) |
| `NotFoundPage` | 404 (nằm trong `StorefrontLayout`) |

### 8.3. Admin (6 trang)

| Trang | Chức năng |
|---|---|
| `DashboardPage` | Thẻ chỉ số, thống kê danh mục, cảnh báo tồn kho thấp, doanh thu (website vs đa sàn — **không gộp** khi chưa có nguồn), banner mock mode |
| `ProductsPage` | Danh sách, tìm kiếm, lọc theo danh mục/nguồn, modal tạo/sửa kèm biến thể & giá |
| `InventoryPage` | Bảng SKU (available/reserved/sellable), nút đồng bộ, modal lịch sử thay đổi, mapping kho |
| `OrdersPage` | Lọc theo trạng thái/thanh toán, đồng bộ lại, đẩy lên BigSeller, hủy đơn, ghi chú nội bộ |
| `CustomersPage` | Danh sách khách + tổng chi tiêu + modal lịch sử mua hàng |
| `BigSellerPage` | Trạng thái kết nối, kiểm tra capability, nút đồng bộ thủ công, đối soát SKU, cấu hình lịch, xem log |

### 8.3.1. Nút "Thêm vào giỏ" nhanh trên `ProductCard`

Ở danh sách sản phẩm, mỗi card có nút thêm nhanh vào giỏ. Vì `cart.add()` cần một SKU
cụ thể, API trả thêm 2 trường trong `ProductResource`:

```php
// app/Http/Resources/ProductResource.php
$data['variants_count'] = $this->variantsCount();
$data['default_variant'] = $this->defaultVariantData();
```

| Trường | Ý nghĩa |
|---|---|
| `variants_count` | Số biến thể **đang hoạt động** của sản phẩm |
| `default_variant` | Biến thể đầu tiên còn hàng: `{ sku, name, price, image, available_stock }` |

Quy tắc hiển thị nút:

| Điều kiện | Hiển thị |
|---|---|
| `variants_count <= 1` và còn hàng | Nút **"Thêm vào giỏ"** → thêm thẳng biến thể mặc định |
| `variants_count > 1` | Nút **"Chọn biến thể"** → dẫn tới trang chi tiết để chọn |
| Hết hàng | Không hiển thị nút |

Nút chuyển sang trạng thái **"Đã thêm vào giỏ"** trong 2 giây sau khi bấm, đồng thời badge
trên icon giỏ hàng ở header tăng tương ứng.

### 8.4. Xử lý base path động (quan trọng cho XAMPP)

Ứng dụng nằm trong thư mục con `/duanma`, nên base path **không cố định**:

```php
// resources/views/app.blade.php
<meta name="app-base" content="{{ $appBase ?? '' }}">
```

```js
// resources/js/config.js
export const APP_BASE = (() => {
    const meta = document.querySelector('meta[name="app-base"]');
    return meta?.getAttribute('content')?.replace(/\/$/, '') ?? '';
})();
```

```js
// resources/js/router/index.js
history: createWebHistory(APP_BASE),

// resources/js/services/api.js
baseURL: `${APP_BASE}/api`,
```

Nhờ vậy **cùng một build** chạy được ở domain gốc lẫn trong thư mục con.

---

## 9. JOBS & SCHEDULER

### 9.1. Jobs

| Job | Tác nụng | Chống trùng |
|---|---|---|
| `SyncBigSellerInventoryJob` | Đồng bộ tồn kho từ BigSeller, ghi snapshot + log | `WithoutOverlapping` |
| `SyncBigSellerProductsJob` | Đồng bộ sản phẩm/SKU | `WithoutOverlapping` |
| `SyncBigSellerOrderStatusJob` | Cập nhật trạng thái đơn từ BigSeller | `WithoutOverlapping` |
| `PushWebsiteOrderToBigSellerJob` | Đẩy đơn website lên BigSeller | `WithoutOverlapping('push-order-{id}')` |
| `RetryBigSellerSyncJob` | Retry các đơn `PENDING_SYNC`/`SYNC_FAILED` chưa có mapping | `WithoutOverlapping` |

Cấu hình chung: `tries = 5`, `backoff = [15, 30, 60, 120, 300]`, `timeout = 60`.

### 9.2. Scheduler (`routes/console.php`)

| Lịch | Job |
|---|---|
| `*/15 * * * *` (chu kỳ cấu hình) | `SyncBigSellerInventoryJob` |
| Mỗi 10 phút | `SyncBigSellerOrderStatusJob` |
| Mỗi 30 phút | `RetryBigSellerSyncJob` |
| `dailyAt('02:00')` | `SyncBigSellerProductsJob` |

Tất cả dùng `withoutOverlapping()` + `onOneServer()`.

### 9.3. Chạy nền

```bash
php artisan queue:work      # terminal 1
php artisan schedule:work   # terminal 2
```

---

## 10. KIỂM THỬ

### 10.1. Kết quả

```
Tests:    51 passed (224 assertions)
Duration: ~1.0s
```

### 10.2. 51 test theo 7 file

| File | Số test | Phạm vi |
|---|---|---|
| `tests/Feature/CheckoutTest.php` | 4 | Kịch bản 1–4: đặt hàng thành công · SKU không tồn tại · vượt tồn kho · **race condition chống oversell** |
| `tests/Feature/BigSellerSyncTest.php` | 18 | Kịch bản 5–15: timeout · lỗi API · token hết hạn · SKU trùng · `SYNC_FAILED` · retry · retry idempotent · huỷ đơn · huỷ đơn đã sync · giải phóng reservation · đối soát SKU · mất kết nối · SKU thiếu · job inventory · job push order |
| `tests/Feature/AuthTest.php` | 6 | Sanctum register/login/logout · phân quyền admin · chặn unauthenticated |
| `tests/Feature/StorefrontTest.php` | 9 | Catalogue công khai · chi tiết (kèm biến thể + tồn kho bán được) · danh mục · validate giỏ · tra cứu đơn |
| `tests/Feature/StockLogicTest.php` | 4 | `sellableQuantity()` · không âm · `isInStock()` · unique constraint SKU |
| `tests/Feature/AdminApiTest.php` | 4 | 12 endpoint admin trả JSON hợp lệ · filter ngưỡng tồn kho theo cấu hình · phân trang khách hàng |
| `tests/Unit/EnumAndMapperTest.php` | 8 | Transition enum · mapper biết/không biết/case-insensitive · `mapOrKeep` · nhãn tiếng Việt · giá trị enum khớp cột DB |

### 10.3. Lệnh chạy

```bash
php artisan test                                    # toàn bộ
php artisan test --filter=BigSellerSyncTest        # riêng nhóm BigSeller
php artisan test --filter=CheckoutTest             # riêng nhóm checkout
php artisan test --filter=AdminApiTest             # riêng nhóm admin
```

---

## 11. LỖI ĐÃ PHÁT HIỆN & SỬA

| # | Vấn đề | Nguyên nhân | Cách sửa |
|---|---|---|---|
| 1 | `/duanma/` trả 500 | `tempnam()` không tạo được file trong `storage/framework/views` (sai quyền ghi của Apache) | `chmod -R 777 storage bootstrap/cache` |
| 2 | Vue Router hiện 404 tại `/duanma/` | `createWebHistory('/')` — base path cố định | Thêm `resources/js/config.js` đọc `<meta name="app-base">`; router + axios dùng `APP_BASE` |
| 3 | Route SPA không khớp đường dẫn nhiều cấp | `/{any?}` chỉ khớp 1 cấp | Đổi thành `/{any}` với regex loại trừ `api\|storage\|build` |
| 4 | Dashboard 500: `Unknown column '5' in 'where clause'` | `whereColumn('available_stock','<=',$threshold)` — `whereColumn` so sánh 2 cột, không so sánh giá trị | Đổi thành `where(...)` ở `DashboardController`, `InventoryController`, `InventoryService` |
| 5 | `/api/admin/customers` 500 (`TypeError`) | Khai báo kiểu trả về `AnonymousResourceCollection` nhưng thực tế trả `JsonResponse` | Đổi kiểu trả về về `JsonResponse` |
| 6 | Chi tiết sản phẩm không có `variants` | `ProductResource` dùng `routeIs('api.products.show')` nhưng các route **chưa được đặt tên** → không bao giờ khớp | So khớp theo đường dẫn `$request->is('api/products/*')` |
| 7 | `Setting::set()` không tồn tại | Thiếu helper ghi setting | Bổ sung `Setting::set()` với suy luận kiểu giá trị |
| 8 | Composer đòi PHP `>= 8.4.1` qua XAMPP | Laravel 12 mặc định | Ghim Symfony `^7.4` + `composer update` |
| 9 | CSS/JS 500 qua `/duanma/build/...` | DocumentRoot trỏ `htdocs/` chứ không phải `public/` | `.htaccess` map `^build/(.*)$` → `public/build/$1`, `^storage/(.*)$` → `public/storage/$1` |
| 10 | Seeder lỗi `big_seller_connections` not found | Tên bảng thực tế là `bigseller_connections` | Đặt `protected $table` tường minh cho 5 model BigSeller |
| 11 | Vite lỗi `Expression expected` | Thiếu `@vitejs/plugin-vue` | Thêm plugin vào `vite.config.js` |
| 12 | Vite lỗi thiếu thẻ đóng `<div>` | Lỗi cú pháp template | Sửa `HomePage.vue` |
| 13 | Vite không resolve `../../composables/useFormat` | Sai đường dẫn import | Đổi thành `../composables/useFormat` |
| 14 | `Rate limiter [api] is not defined` | Chưa định nghĩa limiter | Định nghĩa `RateLimiter::for('api'|'login')` trong `AppServiceProvider::boot()` |
| 15 | SQLite: `NOT NULL constraint failed: skus.reserved_stock` | Select thiếu cột khi `select()` | Bổ sung `reserved_stock`, `on_hand_stock`, `locked_stock`, `shipped_stock` vào select list |
| 16 | `pushToBigSeller` trả `success: false` dù đơn đã tạo | Tiêu chí dựa vào `sync_status === SYNCED`, nhưng BigSeller trả `PENDING_CONFIRM` | Đổi tiêu chí sang `filled($result['bigseller_order_code'])` |

**Tất cả lỗi từ #4–#7 đều đã được thêm test hồi quy tương ứng.**

---

## 12. KẾT QUẢ KIỂM CHỨNG END-TO-END

Thực hiện trên **MySQL thật** (XAMPP) qua HTTP, không chỉ qua test tự động:

| Bước | Kết quả |
|---|---|
| `php artisan migrate:fresh --seed` | ✅ 19 bảng, 8 danh mục, 14 sản phẩm, admin, cấu hình BigSeller |
| `GET /api/products?per_page=2` | ✅ `total: 14`, links/meta đầy đủ |
| `GET /api/categories` | ✅ 8 danh mục |
| `POST /api/checkout` (2× `BMS-3S-20A`) | ✅ Đơn `PIN247-20261112-NSAIJG`, tổng 64.000 ₫, `sync_status = PENDING_SYNC` |
| Kiểm tra giữ chỗ tồn kho | ✅ `available=180, local_reserved=2, safety=5 → sellable=173` |
| `queue:work` → `PushWebsiteOrderToBigSellerJob` | ✅ `bigseller_order_code = BS-MOCK-000001`, mapping được tạo |
| Ánh xạ trạng thái | ✅ `PENDING_CONFIRM` → `PROCESSING` (đúng bảng ánh xạ) |
| Đẩy đơn lần thứ 2 (idempotency) | ✅ Không tạo mapping trùng, `sync_attempts` vẫn = 1 |
| 12 endpoint admin có token | ✅ Đều trả `{"data": ...}` hợp lệ |
| 9 route SPA | ✅ HTTP 200 |
| Giao diện: trang chủ / danh sách / chi tiết | ✅ Render đúng với dữ liệu thật từ MySQL |
| `php artisan test` | ✅ 51 passed (224 assertions) |
| `npm run build` | ✅ Build thành công |

---

## 13. CÁCH CHẠY DỰ ÁN

### 13.1. Điều kiện tiên quyết

- **XAMPP** đang chạy **Apache** + **MySQL**
- PHP **8.2+** (khuyến nghị 8.4), Node.js **18+**, Composer 2

### 13.2. Cài đặt lần đầu

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/duanma

# 1. Dependency
composer install
npm install

# 2. Tạo database (phpMyAdmin hoặc CLI)
#    CREATE DATABASE pin247_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 3. Kiểm tra .env
#    DB_CONNECTION=mysql
#    DB_DATABASE=pin247_shop
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Key + migrate + seed
php artisan key:generate
php artisan migrate --seed

# 5. Build frontend
npm run build

# 6. Quyền ghi cho Apache
chmod -R 777 storage bootstrap/cache
```

### 13.3. Truy cập

| URL | Mô tả |
|---|---|
| `http://127.0.0.1/duanma/` | Storefront (trang chủ) |
| `http://127.0.0.1/duanma/products` | Danh sách sản phẩm |
| `http://127.0.0.1/duanma/products/{slug}` | Chi tiết sản phẩm |
| `http://127.0.0.1/duanma/cart` | Giỏ hàng |
| `http://127.0.0.1/duanma/checkout` | Thanh toán |
| `http://127.0.0.1/duanma/order-tracking` | Tra cứu đơn |
| `http://127.0.0.1/duanma/login` | Đăng nhập |
| `http://127.0.0.1/duanma/admin/dashboard` | Admin portal |
| `http://127.0.0.1/duanma/api/...` | REST API |

**Tài khoản admin:** `admin@pin247.vn` / `admin1234` → **phải đổi khi triển khai production.**

### 13.4. Chạy nền (bắt buộc cho đồng bộ BigSeller)

```bash
# Terminal 1
php artisan queue:work

# Terminal 2
php artisan schedule:work
```

> Không chạy 2 lệnh này thì đơn vẫn tạo được nhưng **không tự đẩy lên BigSeller**.
> Có thể đẩy thủ công tại Admin → Đơn hàng → nút "Đẩy lên BigSeller".

### 13.5. Chạy test

```bash
php artisan test
php artisan test --filter=BigSellerSyncTest
php artisan test --filter=CheckoutTest
php artisan test --filter=AdminApiTest
```

### 13.6. Development frontend (hot reload)

```bash
npm run dev
```

### 13.7. Lệnh quản trị hay dùng

```bash
php artisan migrate:fresh --seed     # Reset sạch + seed lại
php artisan optimize:clear           # Xoá cache config/route/view
php artisan route:list --path=api    # Xem API routes
php artisan about                    # Thông tin môi trường
```

### 13.8. Lưu ý triển khai dưới XAMPP

| Vấn đề | Xử lý |
|---|---|
| DocumentRoot trỏ `htdocs/` chứ không phải `public/` | File `index.php` ở thư mục gốc chuyển tiếp sang `public/index.php` |
| Static assets (`/duanma/build/*`) | `.htaccess` ở thư mục gốc rewrite `^build/(.*)$` → `public/build/$1` |
| Upload (`/duanma/storage/*`) | `.htaccess` rewrite `^storage/(.*)$` → `public/storage/$1` |
| Base path thư mục con | `<meta name="app-base">` + `APP_BASE` (mục 8.4) |
| Quyền ghi `storage/` | `chmod -R 777 storage bootstrap/cache` |
| Khác biệt PHP version (CLI 8.4 vs Apache 8.2) | Symfony ghim `^7.4` (mục 3.1) |

---

## 14. CHUYỂN SANG BIGSELLER THẬT

Hiện đang ở chế độ **mock** (`BIGSELLER_MODE=mock`). Để bật live:

```dotenv
BIGSELLER_MODE=live
BIGSELLER_BASE_URL=<lấy từ tài liệu BigSeller>
BIGSELLER_APP_ID=...
BIGSELLER_APP_KEY=...
BIGSELLER_ACCESS_TOKEN=...
BIGSELLER_REFRESH_TOKEN=...
BIGSELLER_TOKEN_EXPIRES_AT=...

BIGSELLER_PATH_INVENTORY=...
BIGSELLER_PATH_CREATE_ORDER=...
BIGSELLER_PATH_CANCEL_ORDER=...
BIGSELLER_PATH_ORDER_STATUS=...
BIGSELLER_PATH_PRODUCTS=...
BIGSELLER_PATH_WAREHOUSES=...

BIGSELLER_CAPABILITIES=inventory,create_order,order_status
BIGSELLER_INVENTORY_SYNC_EVERY=15
BIGSELLER_SAFETY_STOCK=0
```

> **Tài liệu API BigSeller không được công khai tự do** — chỉ được cấp sau khi tài khoản
> được duyệt quyền truy cập. Dự án **cố tình không bịa** endpoint hay chữ ký xác thực:
> thiếu cấu hình → báo lỗi `BIGSELLER_NOT_CONFIGURED` (503) thay vì gọi URL sai.
>
> Khi nhận được tài liệu, chỉ cần sửa 2 chỗ:
> 1. `HttpBigSellerClient::endpoint()` — ánh xạ key → path
> 2. `BigSellerOrderService::buildPayload()` — cấu trúc payload

---

## 15. TỒN ĐỌNG & VIỆC CẦN LÀM TIẾP

| # | Vấn đề | Mức độ | Hướng xử lý |
|---|---|---|---|
| 1 | **Admin UI chưa verify bằng mắt** — browser bị chặn ở bước submit form đăng nhập (auto-review chặn nhập credential) | Trung bình | Toàn bộ endpoint admin đã cover bằng `AdminApiTest` (12 endpoint). Đăng nhập thủ công tại `/duanma/login` để kiểm tra giao diện |
| 2 | **BigSeller ở chế độ mock** | Cao (nếu muốn lên production) | Cần tài liệu API chính thức — xem mục 14 |
| 3 | **Chưa có git repo** | Trung bình | `git init` + commit để có lịch sử thay đổi trước khi triển khai |
| 4 | **`chmod 777` là cách nhanh cho dev** | Thấp | Production nên dùng user/group đúng của Apache thay vì 777 |
| 5 | **Chưa có ảnh sản phẩm thật** | Thấp | Hiện sản phẩm dùng placeholder; endpoint upload ảnh đã sẵn sàng |
| 6 | **Chưa có thanh toán online thật** | Thấp | Hiện hỗ trợ COD + chuyển khoản (ghi `Payment`), chưa tích hợp cổng thanh toán |
| 7 | **Chưa có email/SMS gửi đơn** | Thấp | `MAIL_MAILER=log` — cần cấu hình SMTP thật khi production |

---

*Tài liệu kỹ thuật chi tiết hơn: xem `PROJECT_PROGRESS.md`.*
