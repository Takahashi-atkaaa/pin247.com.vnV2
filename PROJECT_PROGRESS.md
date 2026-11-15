# PIN247 — Tiến độ dự án & tài liệu kỹ thuật

> Hệ thống thương mại điện tử cho thương hiệu **PIN247** (pin lithium, pin máy khoan,
> cell 18650/21700, mạch BMS, bộ sạc, linh kiện đóng pin).
> Frontend **Vue 3 + Vite + Pinia + Vue Router + Tailwind CSS**, backend **Laravel 12**,
> tích hợp tồn kho trung tâm **BigSeller**.

---

## 1. Trạng thái hiện tại

| Hạng mục | Trạng thái |
|---|---|
| Nền tảng Laravel 12 + Vue 3 (Vite/Pinia/Router/Tailwind) | ✅ Hoàn thành |
| Cấu hình `.env` MySQL, `bigseller.php`, Sanctum, queue/cache | ✅ Hoàn thành |
| 19 bảng CSDL + migration bổ sung `local_reserved_stock` | ✅ Hoàn thành |
| Eloquent models, relationships, enums | ✅ Hoàn thành |
| Lớp BigSeller: contract + HTTP client + Mock client + services | ✅ Hoàn thành |
| Domain services: Product, Inventory, Order | ✅ Hoàn thành |
| Jobs + Scheduler | ✅ Hoàn thành |
| API controllers, resources, routes | ✅ Hoàn thành |
| Storefront Vue (9 trang) + `StorefrontLayout` (header/footer/nút giỏ hàng) | ✅ Hoàn thành |
| Admin Vue (6 trang + shared components) + `AdminLayout` | ✅ Hoàn thành |
| Nút "Thêm vào giỏ" nhanh trên `ProductCard` | ✅ Hoàn thành |
| Seeders (danh mục, sản phẩm, admin, BigSeller, settings) | ✅ Hoàn thành |
| Test suite — **51 test / 224 assertions** | ✅ Tất cả pass |
| Build assets (`npm run build`) | ✅ Hoàn thành |
| Chạy thử trên XAMPP (`http://127.0.0.1/duanma/`) | ✅ Hoàn thành |

---

## 2. Kiến trúc tổng thể

```
┌──────────────────────── Browser ────────────────────────┐
│  Vue 3 SPA (Vite)                                        │
│  ├─ Storefront: Home / Products / Detail / Cart /        │
│  │               Checkout / OrderTracking / Auth        │
│  └─ Admin:     Dashboard / Products / Inventory /        │
│                Orders / Customers / BigSeller            │
└───────────────────────────┬──────────────────────────────┘
                            │ HTTPS + Sanctum Bearer token
┌───────────────────────────▼──────────────────────────────┐
│  Laravel 12                                                │
│  ├─ routes/api.php   → controllers → resources            │
│  ├─ Domain services → OrderService / InventoryService /   │
│  │                     ProductService                      │
│  ├─ BigSeller facade → BigSellerService                    │
│  │      └─ Contracts\BigSellerClient  (interface duy nhất) │
│  │            ├─ HttpBigSellerClient  (API thật)           │
│  │            └─ MockBigSellerClient  (dev/test)           │
│  ├─ Jobs (queue) → SyncBigSeller*Job, PushWebsiteOrder…    │
│  └─ Scheduler → cron định kỳ                              │
└───────────────────────────┬──────────────────────────────┘
                            │ PDO
                   ┌────────▼─────────┐        ┌──────────────┐
                   │  MySQL           │        │  BigSeller   │
                   │  pin247_shop     │        │  API         │
                   └──────────────────┘        └──────────────┘
```

### Ranh giới trách nhiệm (quan trọng)

- **Contract duy nhất**: toàn bộ ứng dụng chỉ phụ thuộc vào
  `App\Services\BigSeller\Contracts\BigSellerClient`. Không service nào được gọi
  trực tiếp `HttpBigSellerClient`.
- **Chuyển chế độ** bằng `BIGSELLER_MODE=mock|live` trong `.env`, bind trong
  `AppServiceProvider`.
- **Không bịa endpoint**: mọi path API đều lấy từ biến môi trường
  (`BIGSELLER_PATH_*`). Nếu thiếu, service ném
  `BigSellerConfigurationException` (HTTP 503) thay vì gọi URL tự chế.
- **Trạng thái mock luôn hiển thị** rõ ràng trong Admin UI và trong response
  (`mode`, `is_mock`).

---

## 3. Kiến trúc chống oversell (double-reservation prevention)

Đây là phần cốt lõi nhất của hệ thống. Ba lớp số lượng tồn kho:

| Trường | Ý nghĩa | Nguồn |
|---|---|---|
| `available_stock` | Tồn kho khả dụng thực tế | Đồng bộ từ BigSeller (source of truth) |
| `reserved_stock` | Tồn kho BigSeller báo đã giữ | Đồng bộ từ BigSeller |
| `local_reserved_stock` | Tồn kho website đang giữ tạm | `OrderService` (giữ ngắn hạn) |
| `safety_stock` | Ngưỡng an toàn tự cấu hình | `settings` / `BIGSELLER_SAFETY_STOCK` |

Công thức số lượng bán được (nằm trong `Sku::sellableQuantity()`):

\[
\text{sellable} = \max(0,\ \text{available\_stock} - \text{local\_reserved\_stock} - \text{safety\_stock})
\]

### Luồng đặt hàng

1. **Kiểm tra cache tồn kho** — `InventoryService::checkAvailability()`.
2. **Kiểm tra trực tiếp BigSeller** (`checkAvailable()`) để giảm rủi ro đồng bộ
   định kỳ bị trễ.
   - Không lấy được dữ liệu → đơn vẫn được tạo ở trạng thái *chờ đồng bộ*,
     kèm `warning` cho người dùng (không coi là còn hàng một cách âm thầm).
3. **Transaction + `lockForUpdate()`** — khoá row `skus` để hai request đặt hàng
   đồng thời không cùng đọc một giá trị tồn kho cũ, rồi cộng
   `local_reserved_stock`.
4. **Tạo đơn** ở trạng thái `PENDING_SYNC`.
5. **Đẩy lên BigSeller** (job hoặc thủ công) — `bigseller_order_code` được lưu
   vào `bigseller_order_mappings`.
6. **Hủy đơn** → `releaseLocalReservation()` trả lại `local_reserved_stock`.

---

## 4. Ánh xạ trạng thái BigSeller

Nằm duy nhất trong `App\Services\BigSeller\BigSellerStatusMapper`.

> Nguyên tắc: **không đoán** trạng thái. Trạng thái chưa biết → ghi log cảnh báo
> và giữ nguyên trạng thái nội bộ hiện tại (`mapOrKeep()`).

| BigSeller | Nội bộ (`OrderSyncStatus`) |
|---|---|
| `PENDING` | `PENDING_SYNC` |
| `PENDING_CONFIRM`, `WAITING_STOCK` | `PROCESSING` |
| `PROCESSING`, `PICKING`, `PICKED`, `PACKED`, `READY_TO_SHIP`, `AWAITING_SHIP` | `PROCESSING` |
| `SHIPPED`, `SHIPPING`, `DELIVERING`, `IN_TRANSIT` | `SHIPPED` |
| `COMPLETED`, `DELIVERED`, `SUCCESS`, `FINISHED` | `COMPLETED` |
| `CANCELLED`, `CANCELED` | `CANCELLED` |
| `FAILED`, `ERROR` | `SYNC_FAILED` |

Tiêu chí "đẩy đơn thành công" là **đã có `bigseller_order_code`** — không phải
`sync_status === SYNCED`, vì BigSeller có thể trả trạng thái đầu vào khác
(vd. `PENDING_CONFIRM`) dù đơn đã tạo thành công.

---

## 5. Idempotency & chống trùng lặp

| Vấn đề | Cơ chế |
|---|---|
| Đồng bộ tồn kho chạy lại nhiều lần | `big_seller_sync_logs` lưu `run_id` (uuid) + `operation` + `status` |
| Đẩy đơn lên BigSeller 2 lần | `big_seller_order_mappings` unique theo `order_id`; kiểm tra mapping trước khi gọi API |
| Đồng bộ sản phẩm trùng SKU | `big_seller_product_mappings` unique theo `sku` / `bigseller_product_code` |
| Retry job | `RetryBigSellerSyncJob` chỉ xử lý đơn `PENDING_SYNC`/`SYNC_FAILED` **và** chưa có mapping |

---

## 6. API endpoints

### Storefront (public)

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/products` | Danh sách sản phẩm (lọc, phân trang, sắp xếp) + `variants_count` + `default_variant` (cho nút thêm nhanh) |
| GET | `/api/products/{slug}` | Chi tiết sản phẩm + biến thể + tồn kho |
| GET | `/api/categories` | Cây danh mục |
| GET | `/api/stock` | Số lượng bán được theo SKU |
| POST | `/api/cart/validate` | Kiểm tra giỏ hàng (tồn kho, giá) |
| POST | `/api/checkout` | Tạo đơn hàng (throttle 10/1 phút) |
| POST | `/api/orders/track` | Tra cứu đơn bằng mã + SĐT |

### Xác thực

| Method | Path | Mô tả |
|---|---|---|
| POST | `/api/auth/register` | Đăng ký (throttle 6/1 phút) |
| POST | `/api/auth/login` | Đăng nhập → trả Sanctum token |
| POST | `/api/auth/logout` | Thu hồi token hiện tại |
| GET | `/api/me` | Thông tin user đang đăng nhập |

### Admin (`auth:sanctum` + `admin`)

| Nhóm | Endpoints |
|---|---|
| Dashboard | `GET /api/admin/dashboard` |
| Sản phẩm | `GET/POST /api/admin/products`, `GET/PUT/DELETE /api/admin/products/{id}`, `POST/DELETE .../images` |
| Danh mục | `GET/POST /api/admin/categories`, `PUT/DELETE /api/admin/categories/{id}` |
| Tồn kho | `GET /api/admin/inventory`, `POST /api/admin/inventory/sync`, `.../sync/dispatch`, `GET /api/admin/inventory/history/{sku}`, `GET /api/admin/inventory/warehouses` |
| Đơn hàng | `GET /api/admin/orders`, `GET .../{id}`, `POST .../resync`, `POST .../push`, `POST .../cancel`, `POST .../refresh-status`, `PUT .../note` |
| Khách hàng | `GET /api/admin/customers`, `GET .../{id}` |
| BigSeller | `GET .../bigseller/status`, `POST .../check-connection`, `POST .../sync/inventory`, `.../sync/products`, `.../sync/orders`, `GET .../reconcile-skus`, `GET .../logs`, `GET/POST .../sku-mappings`, `GET/PUT .../schedule` |

Tất cả response dùng envelope `{"data": ...}` (resource collection) hoặc
`{"data": ..., "meta": ..., "links": ...}` (phân trang).

---

## 7. Cơ sở dữ liệu (19 bảng)

| Nhóm | Bảng |
|---|---|
| Người dùng | `users`, `personal_access_tokens` |
| Danh mục / SP | `categories`, `products`, `product_variants`, `product_images`, `skus` |
| Kho | `warehouses`, `inventory_snapshots`, `inventory_logs` |
| Bán hàng | `customers`, `orders`, `order_items`, `payments` |
| BigSeller | `bigseller_connections`, `bigseller_product_mappings`, `bigseller_warehouse_mappings`, `bigseller_order_mappings`, `bigseller_sync_logs` |
| Hệ thống | `settings`, `cache`, `jobs` |

---

## 8. Jobs & Scheduler

| Job | Tác nụng |
|---|---|
| `SyncBigSellerInventoryJob` | Đồng bộ tồn kho từ BigSeller |
| `SyncBigSellerProductsJob` | Đồng bộ sản phẩm/SKU |
| `SyncBigSellerOrderStatusJob` | Cập nhật trạng thái đơn từ BigSeller |
| `PushWebsiteOrderToBigSellerJob` | Đẩy đơn website lên BigSeller |
| `RetryBigSellerSyncJob` | Retry các đơn đồng bộ lỗi |

Scheduler (`routes/console.php`) — tất cả job đều dispatch vào queue, không chạy
song song nhờ `withoutOverlapping()` + `onOneServer()`:

| Lịch | Job |
|---|---|
| `*/15 * * * *` (chu kỳ cấu hình) | `SyncBigSellerInventoryJob` |
| Mỗi 10 phút | `SyncBigSellerOrderStatusJob` |
| Mỗi 30 phút | `RetryBigSellerSyncJob` |
| `dailyAt('02:00')` | `SyncBigSellerProductsJob` |

---

## 9. Kiểm thử

```bash
php artisan test
```

**Kết quả hiện tại: 51 test / 224 assertions — tất cả PASS.**

| File | Phạm vi |
|---|---|
| `tests/Feature/CheckoutTest.php` | Kịch bản 1–4: đặt hàng thành công, SKU không tồn tại, vượt tồn kho, race condition chống oversell |
| `tests/Feature/BigSellerSyncTest.php` | Kịch bản 5–15: timeout, lỗi API, token hết hạn, SKU trùng, `SYNC_FAILED`, retry, retry idempotent, huỷ đơn, huỷ đơn đã sync, giải phóng reservation, đối soát SKU, mất kết nối, SKU thiếu, job inventory + push order |
| `tests/Feature/AuthTest.php` | Sanctum register/login/logout, phân quyền admin |
| `tests/Feature/StorefrontTest.php` | Catalogue công khai, chi tiết (kèm biến thể + tồn kho bán được), validate giỏ, tra cứu đơn |
| `tests/Feature/StockLogicTest.php` | `sellableQuantity()`, unique constraint SKU |
| `tests/Feature/AdminApiTest.php` | Toàn bộ endpoint admin trả JSON hợp lệ; filter ngưỡng tồn kho theo cấu hình; phân trang khách hàng |
| `tests/Unit/EnumAndMapperTest.php` | `BigSellerStatusMapper` + enum transition |

---

## 10. Cài đặt & chạy

### Yêu cầu

- PHP 8.2+ (khuyến nghị 8.4), MySQL 8
- Node.js 18+, npm
- XAMPP (Apache + MySQL)

### Cài đặt

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/duanma

composer install
npm install

cp .env.example .env
php artisan key:generate

# Cấu hình DB trong .env
# DB_CONNECTION=mysql
# DB_DATABASE=pin247_shop
# DB_USERNAME=root
# DB_PASSWORD=

php artisan migrate --seed
npm run build
```

### Truy cập

| URL | Mô tả |
|---|---|
| `http://127.0.0.1/duanma/` | Storefront |
| `http://127.0.0.1/duanma/admin/dashboard` | Admin portal |
| `http://127.0.0.1/duanma/api/...` | REST API |

Tài khoản admin mặc định (seed): `admin@pin247.vn` / `admin1234`
→ **phải đổi khi triển khai production**.

### Queue worker (nếu dùng queue thật)

```bash
php artisan queue:work
php artisan schedule:work
```

---

## 11. Ghi chú triển khai dưới XAMPP

Ứng dụng nằm trong thư mục con `/duanma`, nên cần lưu ý:

1. **Base path động** — `resources/views/app.blade.php` render
   `<meta name="app-base" content="{{ $appBase }}">` (giá trị lấy từ
   `request()->getBasePath()`). Frontend đọc ở `resources/js/config.js` để dựng
   đúng URL cho Vue Router (`createWebHistory(APP_BASE)`) và axios
   (`baseURL: ${APP_BASE}/api`). Nhờ vậy cùng một build chạy được ở domain gốc
   lẫn thư mục con.
2. **Rewrite tĩnh** — `.htaccess` ở thư mục gốc ánh xạ:
   - `/duanma/build/*` → `public/build/*`
   - `/duanma/storage/*` → `public/storage/*`
3. **Quyền ghi** — Apache cần quyền ghi vào `storage/framework/views` và
   `storage/logs`:
   ```bash
   chmod -R 777 storage bootstrap/cache
   ```
4. **Route fallback** — `routes/web.php` có một route duy nhất trả về blade
   `app`; toàn bộ đường dẫn con do Vue Router xử lý (history mode).
5. **PHP version** — `composer.json` ghim các gói Symfony ở `^7.4` để dự án
   chạy được trên cả PHP 8.4 (CLI) lẫn PHP 8.2 (Apache của XAMPP).

---

## 12. Các lỗi đã phát hiện & sửa trong quá trình kiểm thử

| # | Vấn đề | Nguyên nhân | Cách sửa |
|---|---|---|---|
| 1 | `/duanma/` trả về 500 | `tempnam()` không tạo được file trong `storage/framework/views` (sai quyền ghi của Apache) | `chmod -R 777 storage bootstrap/cache` |
| 2 | Vue Router hiện 404 tại `/duanma/` | `createWebHistory('/')` — base path cố định | Thêm `resources/js/config.js` đọc `<meta name="app-base">` do server render; router + axios dùng `APP_BASE` |
| 3 | Route SPA không khớp đường dẫn nhiều cấp | `/{any?}` chỉ khớp 1 cấp | Đổi thành `/{any}` với regex loại trừ `api|storage|build` |
| 4 | Dashboard 500: `Unknown column '5' in 'where clause'` | `whereColumn('available_stock', '<=', $threshold)` dùng sai — `whereColumn` so sánh 2 cột | Đổi thành `where('available_stock', '<=', $threshold)` (cả ở `InventoryController` và `InventoryService`) |
| 5 | `/api/admin/customers` 500 (`TypeError`) | Khai báo kiểu trả về `AnonymousResourceCollection` nhưng thực tế trả `JsonResponse` | Đổi kiểu trả về về `JsonResponse` |
| 6 | Chi tiết sản phẩm không có `variants` | `ProductResource` dùng `routeIs('api.products.show')` nhưng các route **chưa được đặt tên** → không bao giờ khớp | So khớp theo đường dẫn `$request->is('api/products/*')` |
| 7 | `Setting::set()` không tồn tại | Thiếu helper ghi setting | Bổ sung `Setting::set()` với suy luận kiểu giá trị |
| 8 | Storefront không có header/footer/nút giỏ hàng | `StorefrontLayout.vue` được viết nhưng **không route nào dùng** — các route storefront trỏ thẳng vào page component | Bọc route storefront trong route cha `path: '/'` + `children` dùng `StorefrontLayout` |
| 9 | `<main>` rỗng sau khi bọc layout | Vue Router 4 **không** render route con vào `<slot />` của route cha | Đổi `<slot />` → `<RouterView />` trong `StorefrontLayout` và `AdminLayout` |
| 10 | URL `/products` thay vì `/duanma/products` | Route con viết `'path: '/products'` (có dấu `/` ở đầu) nên không kế thừa path route cha | Bỏ dấu `/` ở đầu mọi `path` của route con |
| 11 | `totalAvailableStock()` gây N+1 query | Dùng `$this->skus()->sum(...)` (query mới) thay vì dùng quan hệ đã eager-load | Dùng `$this->skus` khi `relationLoaded('skus')` |

Tất cả lỗi trên đều đã được thêm test hồi quy tương ứng.

---

## 13. Quy trình kiểm thử thực tế đã chạy

Ngoài test tự động, dự án đã được kiểm chứng end-to-end trên MySQL thật
(XAMPP) qua HTTP:

| Bước | Kết quả |
|---|---|
| `php artisan migrate:fresh --seed` | ✅ 19 bảng + 8 danh mục + 14 sản phẩm + admin + cấu hình BigSeller |
| `POST /api/checkout` (2 × `BMS-3S-20A`) | ✅ Tạo đơn `PIN247-20261112-NSAIJG`, tổng 64.000 ₫, `sync_status = PENDING_SYNC` |
| Kiểm tra giữ chỗ tồn kho | ✅ `available=180, local_reserved=2, safety=5 → sellable=173` |
| `queue:work` → `PushWebsiteOrderToBigSellerJob` | ✅ `bigseller_order_code = BS-MOCK-000001`, mapping được tạo |
| Ánh xạ trạng thái | ✅ `PENDING_CONFIRM` → `PROCESSING` (đúng bảng ánh xạ) |
| Đẩy đơn lần thứ 2 (idempotency) | ✅ Không tạo mapping trùng, `sync_attempts` vẫn = 1 |
| Toàn bộ endpoint admin (12 route) | ✅ Trả JSON hợp lệ |
| Giao diện: trang chủ / danh sách / chi tiết | ✅ Render đúng, dữ liệu thật từ MySQL |

---

## 14. Cấu hình BigSeller

`.env`:

```dotenv
BIGSELLER_MODE=mock                # mock | live
BIGSELLER_APP_ID=
BIGSELLER_APP_KEY=
BIGSELLER_ACCESS_TOKEN=
BIGSELLER_REFRESH_TOKEN=
BIGSELLER_TOKEN_EXPIRES_AT=

# Chỉ điền sau khi nhận tài liệu API chính thức từ BigSeller
BIGSELLER_BASE_URL=
BIGSELLER_PATH_INVENTORY=
BIGSELLER_PATH_CREATE_ORDER=
BIGSELLER_PATH_CANCEL_ORDER=
BIGSELLER_PATH_ORDER_STATUS=
BIGSELLER_PATH_PRODUCTS=
BIGSELLER_PATH_WAREHOUSES=

BIGSELLER_CAPABILITIES=inventory,create_order,cancel_order,order_status
BIGSELLER_INVENTORY_SYNC_EVERY=15
BIGSELLER_SAFETY_STOCK=0
```

> Tài liệu API BigSeller không được công khai tự do — chỉ được cấp sau khi tài
> khoản được duyệt. Dự án **không bịa** endpoint hay chữ ký xác thực; mọi
> giá trị đều lấy từ biến môi trường và thiếu thì báo lỗi cấu hình rõ ràng.

---

## 15. Cấu trúc thư mục

```
duanma/
├── app/
│   ├── Enums/                 # OrderSyncStatus, ProductSource, PaymentStatus…
│   ├── Exceptions/            # BigSellerApiException, BusinessRuleException…
│   ├── Http/
│   │   ├── Controllers/       # Storefront + Admin + Auth
│   │   ├── Middleware/        # AuthenticateApi, EnsureUserIsAdmin
│   │   ├── Requests/          # CheckoutRequest
│   │   └── Resources/         # Product/Order/Inventory resources
│   ├── Jobs/                  # 5 BigSeller jobs
│   ├── Models/                # 20 models
│   ├── Providers/
│   └── Services/
│       ├── BigSeller/
│       │   ├── Contracts/BigSellerClient.php   ← interface duy nhất
│       │   ├── Data/                            ← DTO
│       │   ├── HttpBigSellerClient.php
│       │   ├── MockBigSellerClient.php
│       │   └── BigSellerStatusMapper.php
│       ├── BigSellerService.php
│       ├── InventoryService.php
│       ├── OrderService.php
│       └── ProductService.php
├── database/{migrations,seeders,factories}
├── resources/
│   ├── js/
│   │   ├── components/  layouts/  pages/  stores/  composables/
│   │   ├── router/index.js
│   │   ├── services/api.js
│   │   └── config.js              ← APP_BASE động
│   ├── css/app.css
│   └── views/app.blade.php
├── routes/{api.php,web.php,console.php}
└── tests/{Feature,Unit}
```
