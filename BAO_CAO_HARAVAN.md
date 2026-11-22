# Báo cáo: Clone cơ chế quản lý sản phẩm từ Haravan

> **Ngày thực hiện:** 23/11/2026
> **Phạm vi:** 3 giai đoạn (GĐ1 – GĐ2 – GĐ3) — đơn vị tính ✅ · nhân bản ✅ · barcode ❌ (theo yêu cầu)
> **Nguồn tham chiếu:** [help.haravan.com](https://help.haravan.com/docs/products/create-product/thuoc-tinh-va-bien-the-cua-san-pham/)

---

## 1. Tóm tắt

Đã clone cơ chế quản lý sản phẩm của Haravan vào website PIN247, gồm 3 giai đoạn:

| GĐ | Tính năng | Trạng thái |
|---|---|---|
| **GĐ1** | Thuộc tính động & tự sinh biến thể (cartesian product) | ✅ Hoàn thành |
| **GĐ2** | Đơn vị tính (UoM) — quy đổi + giá tự tính | ✅ Hoàn thành |
| **GĐ3** | Lịch sử cập nhật, nhân bản sản phẩm, thao tác hàng loạt | ✅ Hoàn thành |

**Barcode:** không bắt buộc (theo yêu cầu). Cột `barcode` vẫn tồn tại trong `product_units` dạng nullable cho tương thích tương lai nhưng **không được bắt buộc hay kiểm tra** trong logic nghiệp vụ — mọi đơn vị tính đều để `NULL`.

Kèm theo là **7 lỗi có sẵn được phát hiện & sửa** (chi tiết ở mục 6), trong đó lỗi 6 là bug deploy khiến bảng mới trên production bị rỗng.

**Kết quả kiểm thử:**
- Unit test: `124 passed (560 assertions)` — không regression
- Import SQL (mô phỏng DB trống): `368/368` câu lệnh OK, `36/36` bảng tạo mới, **0 lỗi**
- CI pipeline: **success** — smoke test `22/22`, E2E test `10/10`
- E2E production (`scripts/e2e-haravan-test.sh`): cả 3 giai đoạn đều pass trên `https://tunganhahihi.io.vn`

---

## 2. Cơ chế Haravan & cách clone

### 2.1. Nguồn: Haravan quản lý gì?

| Khía cạnh | Haravan | Tình trạng trước khi clone |
|---|---|---|
| Số thuộc tính | Tối đa **3** | Cứng nhắc `option_1` / `option_2` |
| Số giá trị / thuộc tính | Tối đa **200** | Không giới hạn |
| Số biến thể / sản phẩm | Tối đa **500** | Không giới hạn |
| Sinh biến thể | Tự sinh từ tích hợp giá trị | Phải nhập tay |
| Đơn vị tính | Có — quy đổi + giá tự tính | ❌ Không có |
| Lịch sử cập nhật biến thể | Có | ❌ Không có |
| Nhân bản sản phẩm | Có | ❌ Không có |
| Thao tác hàng loạt | Có | ❌ Không có |
| Thẻ kho biến thể | 6 chỉ số | Chỉ 1 chỉ số |

### 2.2. Nguyên tắc giữ nguyên theo Haravan

1. **Tối đa 3 thuộc tính** — `ProductAttribute::MAX_ATTRIBUTES = 3`
2. **Đơn vị tính không sửa tỷ lệ quy đổi sau khi tạo** (chỉ thêm mới / xoá)
3. **Giá đơn vị quy đổi tự tính** = giá đơn vị cơ bản × tỷ lệ quy đổi
4. **Đúng 1 đơn vị cơ bản** cho mỗi biến thể
5. **Mỗi đơn vị có cờ "bán hàng"** riêng (`is_sellable`)
6. **Biến thể không khớp thuộc tính được ẨN, không xoá cứng** (giữ lịch sử đơn hàng)

---

## 3. GĐ1 — Thuộc tính động & tự sinh biến thể

### 3.1. Cơ chế

```
Thuộc tính 1: "Kiểu đầu"   → [Makita, Bosch, Hitachi]
Thuộc tính 2: "Dung lượng"  → [2.0Ah, 5.0Ah]
                                    ↓
                    TÍCH HỢP (cartesian product)
                                    ↓
              6 biến thể: Makita-2.0Ah, Makita-5.0Ah, Bosch-2.0Ah, ...
```

### 3.2. Schema mới

**Bảng `product_attributes`**
| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK → products | |
| `name` | string | Tên thuộc tính ("Kiểu đầu") |
| `position` | tinyint (1–3) | Vị trí hiển thị |

Unique: `(product_id, position)` — mỗi sản phẩm mỗi vị trí chỉ 1 thuộc tính.

**Bảng `product_attribute_values`**
| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `id` | bigint PK | |
| `product_attribute_id` | FK | |
| `value` | string | Giá trị ("Makita") |
| `sort_order` | int | |

Unique: `(product_attribute_id, value)`.

**Bảng `product_variants` — cột bổ sung**
| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `option_3_name` | string nullable | Thuộc tính thứ 3 |
| `option_3_value` | string nullable | |
| `attribute_signature` | string nullable | Chữ ký `"Makita\|2.0Ah\|"` |

`attribute_signature` cho phép đối chiếu nhanh biến thể khi tự sinh lại (thay vì so khớp từng `option_*` rời rạc).

### 3.3. Service `ProductAttributeService`

| Method | Tác dụng |
|---|---|
| `syncAttributes()` | Ghi lại thuộc tính + giá trị (tối đa 3 thuộc tính, 200 giá trị) |
| `regenerateVariants()` | Sinh lại biến thể từ thuộc tính |
| `combinations()` | Tính cartesian product |
| `signature()` | Tạo chữ ký `"v1\|v2\|v3"` |
| `generateSku()` | Sinh SKU unique cho biến thể mới |
| `syncSkuRecord()` | Đảm bảo có bản ghi `skus` (tồn kho) |

**Quy tắc quan trọng — không xoá biến thể:**
```php
// Biến thể không còn khớp thuộc tính → ẨN, KHÔNG xoá cứng
$stale = $product->variants()
    ->whereNotIn('id', $keptIds ?: [0])
    ->where('is_active', true)
    ->get();

foreach ($stale as $variant) {
    $variant->forceFill(['is_active' => false])->save();
}
```
Lý do: biến thể cũ có thể đã có đơn hàng, tồn kho, lịch sử — xoá cứng sẽ phá vỡ dữ liệu.

**Ghép dữ liệu admin nhập tay:**
Khi admin chỉnh giá/SKU của biến thể, hệ thống ghép theo **chữ ký option** (độc lập SKU) trước, sau đó mới thử khớp theo SKU. Nhờ vậy biến thể mới sinh ra vẫn nhận được giá/SKU admin đã nhập.

### 3.4. UI

**`ProductAttributesEditor.vue`** (mới) — 2 khối:

```
┌─ Thuộc tính sản phẩm ─────────────────────────────┐
│ [+ Thêm thuộc tính]          (tối đa 3)            │
│                                                   │
│ ┌ Kiểu đầu ─────────────── [Xoá] ┐               │
│ │ [Makita ×] [Bosch ×] [+ Giá trị]│               │
│ └─────────────────────────────────┘               │
└───────────────────────────────────────────────────┘

┌─ Biến thể (6) ────────────────────────────────────┐
│ Biến thể    │ SKU      │ Giá bán  │ Giá vốn │ ...  │
│ [tên]       │ [sku]    │ [price]  │ [cost]  │      │
└───────────────────────────────────────────────────┘
```

**Cách dùng:**
1. Tick "Quản lý biến thể & đơn vị tính" trong form sản phẩm
2. Thêm thuộc tính → nhập tên → thêm giá trị (Enter để xác nhận)
3. Bảng biến thể **tự động sinh** — chỉnh giá/SKU/tên trực tiếp
4. Lưu

### 3.5. Storefront — selector nhiều chiều

`ProductVariantSelector.vue` được viết lại hoàn toàn:

- **Trước:** 1 hàng nút phẳng, chỉ hiện `option_1_value` → khách không thấy "Dung lượng"
- **Sau:** mỗi thuộc tính 1 hàng nút, giá trị không tồn tại trong tổ hợp đã chọn bị **disable**

```
Kiểu đầu:  [Makita] [Bosch] [Hitachi]
Dung lượng: [2.0Ah] [5.0Ah]
```

Hỗ trợ tối đa 3 chiều (`option_1` × `option_2` × `option_3`).

---

## 4. GĐ2 — Đơn vị tính (UoM)

### 4.1. Cơ chế

```
Biến thể: "Cell 18650 Panasonic 3000mAh"  — giá gốc 79.000đ

Đơn vị tính:
├── viên    (base, rate=1)    →  79.000đ      (giá biến thể)
├── lô 20   (rate=20)         →  1.580.000đ  (tự tính: 79.000 × 20)
└── hộp 100 (rate=100)        →  7.900.000đ  (tự tính: 79.000 × 100)
```

### 4.2. Schema `product_units`

| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `id` | bigint PK | |
| `product_variant_id` | FK → product_variants | Đơn vị tính thuộc biến thể |
| `name` | string | "viên", "lô", "hộp" |
| `conversion_rate` | uint | Tỷ lệ quy đổi (1 = đơn vị cơ bản) |
| `is_base` | bool | Là đơn vị cơ bản? |
| `is_base_key` | uint GENERATED | `CASE WHEN is_base=1 THEN 1 ELSE NULL END` |
| `price` | decimal nullable | Giá riêng (NULL = tự tính) |
| `is_sellable` | bool | Cờ "bán hàng" |
| `barcode` | string nullable | Barcode riêng cho đơn vị |
| `sort_order` | int | |

**Về `is_base_key` — kỹ thuật quan trọng:**

Unique trên `(product_variant_id, is_base)` **không dùng được**, vì nhiều đơn vị quy đổi đều có `is_base = 0` → vi phạm unique.

Giải pháp: cột sinh `is_base_key` = `1` khi là base, `NULL` khi không. Unique index trên `(product_variant_id, is_base_key)` → mỗi biến thể tối đa 1 đơn vị cơ bản, vẫn cho phép nhiều đơn vị quy đổi (NULL không bị tính trong unique index — đúng trên cả MySQL 8.0 và SQLite).

### 4.3. Quy tắc nghiệp vụ

| Quy tắc | Nguồn |
|---|---|
| `conversion_rate` **không sửa được** sau khi tạo | Haravan |
| Chỉ 1 đơn vị cơ bản (`rate = 1`) | Haravan |
| Giá đơn vị quy đổi = giá cơ bản × `conversion_rate` | Haravan |
| Đơn vị có thể đặt giá riêng (ghi đè giá tự tính) | Mở rộng |
| Mỗi đơn vị có cờ `is_sellable` | Haravan |

### 4.4. UI

`ProductUnitsEditor.vue` (mới) — bảng đơn vị tính:

```
Tên đơn vị  │ Tỷ lệ      │ Giá                  │ Bán hàng │ 
[viên    ]  │ [1] (khoá) │ [      ] Tự tính: 79.000đ │ [x]     │ 
[lô 20   ]  │ [20]       │ [      ] Tự tính: 1.580.000đ │ [x]     │ 
```

- Đơn vị đã tồn tại → **khoá** ô tỷ lệ (đúng quy tắc Haravan), hiện tooltip "Không sửa được"
- Đơn vị mới → nhập tự do
- Giá trống → hiển thị giá tự tính bên dưới

Truy cập từ 2 nơi:
1. Nút "N đơn vị" trong bảng biến thể (form sản phẩm)
2. Màn "Chi tiết biến thể"

### 4.5. Tự sinh đơn vị cơ sở

Khi `regenerateVariants()` sinh **biến thể mới** (admin thêm giá trị thuộc tính), biến thể đó chưa có đơn vị tính → storefront không hiển thị được phần chọn đơn vị và giá quy đổi.

Xử lý trong `ProductAttributeService::createDefaultUnit()`:

| Trường hợp | Hành vi |
|---|---|
| Biến thể **mới** | Tự tạo 1 đơn vị cơ sở: `conversion_rate = 1`, `is_base = true`, tên đoán theo từ khoá tên sản phẩm (viên / cây / bộ / mạch / cái), `barcode = NULL` |
| Biến thể **đã tồn tại** | Giữ nguyên cấu hình đơn vị admin đã làm — không ghi đè, không xoá |

Dữ liệu mẫu cũng được bổ sung bằng `ProductUnitSeeder` (mục 10) → **69 đơn vị tính** cho 23 biến thể.

---

## 5. GĐ3 — Lịch sử, nhân bản, thao tác hàng loạt

### 5.1. Lịch sử cập nhật biến thể

**Schema `product_variant_histories`**
| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `id` | bigint PK | |
| `product_variant_id` | FK | |
| `product_id` | FK | |
| `changed_by` | FK → users nullable | Admin thực hiện |
| `changed_fields` | json | `["price","sale_price"]` |
| `before` | json nullable | Snapshot trước |
| `after` | json nullable | Snapshot sau |
| `summary` | string(500) | Mô tả tiếng Việt |

**Các trường được theo dõi:**
`price`, `sale_price`, `cost_price`, `sku`, `name`, `is_active`, `image`

**Summary tự động (tiếng Việt):**
```
Giá bán: 249.000đ → 259.000đ; Trạng thái: đang bán → ẩn
```

Ghi lịch sử ở 3 nơi: cập nhật biến thể đơn lẻ, cập nhật qua form sản phẩm, điều chỉnh giá hàng loạt.

### 5.2. Màn "Chi tiết biến thể"

Route mới: `/admin/products/:id/variants/:variantId`

```
┌────────────────────────────────────────────────────────┐
│ ← Quay lại danh sách / Pin máy khoan 21V               │
│ SKU: PIN-KHOAN-21V-50-MAK    [Đang bán] [Kiểu đầu: Makita] │
│                                        Giá bán 799.000đ  │
└────────────────────────────────────────────────────────┘

┌─ Thông tin bán hàng ────────┐  ┌─ Thẻ kho biến thể ───┐
│ Tên biến thể [           ]   │  │ Số tồn          100   │
│ Giá bán  │ Giá so sánh │ Giá vốn │  │ Số đặt           0   │
│ [      ] │ [         ] │ [    ]  │  │ Số tồn cuối   100   │
│ [x] Đang bán trên website  │  │ Số khả dụng cuối 100   │
│ [  Lưu thay đổi  ]          │  │ Tồn an toàn       0   │
└──────────────────────────────┘  └───────────────────────┘

┌─ Đơn vị tính ────────────────────────────────────────┐
│ [bảng đơn vị]  [  Lưu đơn vị tính  ]                  │
└────────────────────────────────────────────────────────┘

┌─ Lịch sử cập nhật ───────────────────────────────────┐
│ Giá bán: 749.000đ → 799.000đ          23/11/2026 10:30  │
│ [Giá bán] [Giá so sánh]              Quản trị PIN247   │
└────────────────────────────────────────────────────────┘
```

**Thẻ kho** hiển thị đủ 6 chỉ số như Haravan: Số tồn, Số đặt, Số tồn cuối, Số khả dụng cuối, Tồn an toàn, Trạng thái sync.

### 5.3. Nhân bản sản phẩm

`POST /api/admin/products/{id}/duplicate`

**Được copy:** tên (+" (bản sao)"), thương hiệu, mô tả, thông số, giá, giá vốn, ảnh, SEO, **toàn bộ thuộc tính**, **toàn bộ biến thể**, **toàn bộ đơn vị tính**.

**Sinh mới:** SKU (`ABC` → `ABC-COPY` → `ABC-COPY-2`), slug, tồn kho = 0, trạng thái = ẩn.

Bản sao mặc định **không bán** (`is_active = false`) để admin review trước khi publish — giống Haravan (Haravan yêu cầu chỉnh lại SEO sau khi nhân bản).

### 5.4. Thao tác hàng loạt

**Sản phẩm** — `POST /api/admin/products-bulk`
| Action | Tác dụng |
|---|---|
| `activate` / `deactivate` | Hiển thị / ẩn |
| `feature` / `unfeature` | Nổi bật |
| `mark_new` / `unmark_new` | Hàng mới |
| `move_category` | Chuyển danh mục |
| `delete` | Xoá |

**Biến thể** — `POST /api/admin/variants-bulk`
| Action | Tác dụng |
|---|---|
| `activate` / `deactivate` | Bật / tắt |
| `adjust_price` | Điều chỉnh giá (tuyệt đối hoặc `%`) |

Thao tác `adjust_price` cũng ghi lịch sử cho từng biến thể.

---

## 6. Lỗi có sẵn được phát hiện & sửa

### 6.1. 🔴 Lỗi 1 — Admin hiển thị "0 biến thể"

**Triệu chứng:** Danh sách sản phẩm admin hiện "0 biến thể" dù DB có 3 biến thể.

**Nguyên nhân:** `ProductResource::toArray()` chỉ serialize `variants` khi `$includeRelations = true`, điều kiện là:
```php
$request->is('api/admin/products/*')   // ← có dấu *  → KHÔNG match route list
```
Route danh sách là `api/admin/products` (không có phần sau) → `includeRelations = false` → key `variants` không có trong JSON → Vue đọc `row.variants?.length` = `undefined` → `|| 0` → hiện "0 biến thể".

**Sửa:**
```php
$includeRelations = $request->boolean('include', false)
    || $request->is('api/products/*')
    || $request->is('api/admin/products')      // ← thêm route list
    || $request->is('api/admin/products/*');
```
Kèm sửa Vue dùng `row.variants_count` (đã có sẵn trong resource, không phụ thuộc cờ).

### 6.2. 🔴 Lỗi 2 — `SoftDeletes` thiếu trên 3 model

**Triệu chứng:** Xoá sản phẩm/biến thể/SKU **xoá hẳn**, không soft-delete.

**Nguyên nhân:** Bảng `products`, `product_variants`, `skus` đều có cột `deleted_at` (do migration khai báo `$table->softDeletes()`), nhưng 3 Model **không khai báo `use SoftDeletes;`** → Eloquent bỏ qua cột `deleted_at`, xoá là xoá vật lý.

Hậu quả: mất vĩnh viễn lịch sử đơn hàng, tồn kho, biến thể đã bán.

**Sửa:** thêm `use Illuminate\Database\Eloquent\SoftDeletes;` + `use SoftDeletes;` vào `Product`, `ProductVariant`, `Sku`.

### 6.3. 🟡 Lỗi 3 — Storefront bỏ qua `option_2`

**Triệu chứng:** Sản phẩm có 2 thuộc tính ("Kiểu đầu" × "Dung lượng") nhưng khách chỉ thấy "Makita", không thấy "2.0Ah".

**Nguyên nhân:** `ProductVariantSelector.vue` chỉ render `variant.option_1_value || variant.name`.

**Sửa:** viết lại selector thành bản nhiều chiều (mục 3.5).

### 6.4. 🟡 Lỗi 4 — Storefront hiện biến thể đã ẩn

**Triệu chứng:** Biến thể bị admin tắt (`is_active = false`) vẫn xuất hiện trên website.

**Nguyên nhân:** `ProductController@show` load toàn bộ `variants` không lọc trạng thái.

**Sửa:**
```php
'variants' => fn ($q) => $q->where('is_active', true),
```

### 6.5. 🟡 Lỗi 5 — Admin không tạo/sửa được biến thể từ UI

**Triệu chứng:** Backend đã hỗ trợ `variants` trong API nhưng form admin không có ô nhập, không gửi `variants` trong payload.

**Sửa:** xây dựng mới `ProductAttributesEditor.vue` + tích hợp vào `ProductsPage.vue` (mục 3.4).

### 6.6. 🔴 Lỗi 6 — Deploy import SQL sai (bảng mới rỗng trên production)

**Triệu chứng:** CI deploy báo thành công, smoke test 22/22 pass, nhưng trên production:
- Bảng `product_attributes`, `product_units`, `product_variant_histories` **tồn tại nhưng 0 dòng**
- Log import: `Đã chạy 36 câu lệnh. Có 51 câu lệnh lỗi` với
  `Table 'xxx' already exists` và `Duplicate entry '1' for key 'PRIMARY'`
- E2E test fail 2/10 (`Đồng bộ tồn kho` và `Đăng xuất` → 401)

**Nguyên nhân gốc:** `deploy/deploy-import.php` dùng regex để tách câu lệnh SQL:

```php
$statements = preg_split('/;\s*[\r\n]+/', $sql);
```

Regex này **yêu cầu `;` phải nằm cuối dòng**. Nhưng `mysqldump` xuất:

```sql
-- Table structure for table `bigseller_connections`
DROP TABLE IF EXISTS `bigseller_connections`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
```

Dấu `;` của `DROP TABLE` nằm **giữa dòng** → regex cắt sai chỗ → `DROP TABLE` bị dính vào một khối bắt đầu bằng `--` → script bỏ qua cả khối (coi là comment) → **không DROP được bảng cũ** → `CREATE TABLE` báo *"already exists"* → `INSERT` báo *"Duplicate entry"* → dữ liệu import sai.

Sau khi sửa: **36/36 bảng có `DROP TABLE`** (trước: **0**).

**Sửa:** thay regex bằng vòng lặp quét ký tự, cắt tại `;` chỉ khi:
- Không nằm trong khối `/* ... */` (giữ nguyên `/*! ... */` vì chứa lệnh thật của MySQL)
- Không nằm trong chuỗi literal `'...'`

Sau đó bỏ các dòng comment thuần (`--`, `#`) nhưng **giữ phần lệnh nằm sau chúng** (chính là `DROP TABLE`). Bổ sung fallback khi gặp *"already exists"*: `DROP TABLE IF EXISTS` rồi thử lại.

**Kiểm chứng:**

| | Trước | Sau |
|---|---|---|
| Số câu lệnh tách ra | 369 (sai) | 368 (đúng) |
| `DROP TABLE` thực thi | **0** | **36** |
| `CREATE TABLE` | 36 (lỗi 1050) | 36 (OK) |
| Lỗi import | 51 | **0** |
| `product_attributes` | 0 dòng | 27 dòng |

Thêm 2 file hỗ trợ:
- `deploy/deploy-db-check.php` — script kiểm tra nhanh DB production (tự xoá sau khi chạy)
- `scripts/e2e-haravan-test.sh` — E2E test đầy đủ 3 giai đoạn Haravan trên production

### 6.7. 🟡 Lỗi 7 — Thiếu đơn vị tính cho dữ liệu cũ

**Triệu chứng:** `product_units` rỗng sau khi deploy → storefront không hiển thị được phần chọn đơn vị tính, giá quy đổi không hoạt động.

**Nguyên nhân:** Migration tạo bảng nhưng không có seed dữ liệu cho các biến thể đã tồn tại.

**Sửa:** thêm `database/seeders/ProductUnitSeeder.php`:
- Đơn vị cơ sở đặt theo loại sản phẩm (viên / cây / bộ / mạch / cái)
- Tự thêm 2 đơn vị quy đổi: `lô 10` (×10) và `hộp 12` (×12)
- Giá quy đổi = giá cơ sở × hệ số quy đổi
- `barcode = NULL` (theo yêu cầu "không cần barcode")
- Idempotent: chỉ tạo cho biến thể chưa có đơn vị nào

Kết quả: **69 đơn vị tính** cho 23 biến thể.

---

## 7. API mới

| Method | Endpoint | Mô tả |
|---|---|---|
| `POST` | `/api/admin/products` | Tạo sản phẩm (hỗ trợ `attributes`, `variants`, `units`) |
| `PUT` | `/api/admin/products/{id}` | Cập nhật (tự sinh lại biến thể) |
| `POST` | `/api/admin/products/{id}/duplicate` | **Nhân bản sản phẩm** |
| `GET` | `/api/admin/products/{id}/variants/{vid}` | **Chi tiết biến thể** (kèm thẻ kho, đơn vị, lịch sử) |
| `PUT` | `/api/admin/products/{id}/variants/{vid}` | **Cập nhật biến thể** (ghi lịch sử) |
| `PUT` | `/api/admin/products/{id}/variants/{vid}/units` | **Cập nhật đơn vị tính** |
| `GET` | `/api/admin/products/{id}/variants/{vid}/history` | **Lịch sử cập nhật** |
| `POST` | `/api/admin/products-bulk` | **Thao tác hàng loạt sản phẩm** |
| `POST` | `/api/admin/variants-bulk` | **Thao tác hàng loạt biến thể** |

Tất cả đều yêu cầu `auth:sanctum` + `is_admin`.

---

## 8. File thay đổi

### Backend — Mới (11 file)
```
app/Models/ProductAttribute.php
app/Models/ProductAttributeValue.php
app/Models/ProductUnit.php
app/Models/ProductVariantHistory.php
app/Services/ProductAttributeService.php
app/Services/ProductUnitService.php
app/Services/ProductVariantHistoryService.php
app/Http/Resources/ProductVariantResource.php
app/Http/Controllers/Admin/ProductBulkController.php
database/migrations/2026_11_23_010000_create_product_attributes_tables.php
database/migrations/2026_11_23_020000_create_product_units_table.php
database/migrations/2026_11_23_030000_create_product_variant_histories_table.php
database/migrations/2026_11_23_040000_backfill_product_attributes.php
database/seeders/ProductAttributeBackfillSeeder.php
database/seeders/ProductUnitSeeder.php
```

### Backend — Sửa (11 file)
```
app/Models/Product.php               + SoftDeletes, + quan hệ attributes()
app/Models/ProductVariant.php        + SoftDeletes, + option_3, + quan hệ units()/histories()
app/Models/Sku.php                   + SoftDeletes
app/Services/ProductService.php      + attributes/units/history/duplicate
app/Http/Controllers/Admin/ProductController.php  + 6 endpoint, + rules chung
app/Http/Controllers/ProductController.php         + lọc variant active, + load units
app/Http/Resources/ProductResource.php + attributes, units, option_3, fix includeRelations
routes/api.php                        + 7 route
```

### Frontend — Mới (3 file)
```
resources/js/components/admin/ProductAttributesEditor.vue
resources/js/components/admin/ProductUnitsEditor.vue
resources/js/pages/admin/ProductVariantPage.vue
```

### Frontend — Sửa (4 file)
```
resources/js/pages/admin/ProductsPage.vue     + thuộc tính, biến thể, đơn vị, bulk, nhân bản
resources/js/components/ProductVariantSelector.vue   viết lại — selector nhiều chiều
resources/js/router/index.js                 + route chi tiết biến thể
```

### Test — Mới (2 file, 21 test)
```
tests/Feature/ProductVariantManagementTest.php   16 test
tests/Feature/ProductVariantWorkflowTest.php      5 test
```

### Deploy — Sửa (2 file)
```
deploy/deploy-import.php    + sửa regex tách câu lệnh SQL (mục 6.6)
deploy/deploy-db-check.php  + script kiểm tra DB production (mới)
scripts/e2e-haravan-test.sh + E2E test 3 giai đoạn Haravan trên production (mới)
```

---

## 9. Kiểm thử

| Hạng mục | Kết quả |
|---|---|
| Test mới (GĐ1) | Tích hợp 3 thuộc tính → 8 biến thể; giới hạn 3 thuộc tính; regenerate giữ biến thể cũ |
| Test mới (GĐ2) | Quy đổi & giá tự tính; giá riêng ghi đè; khoá `conversion_rate`; xoá đơn vị |
| Test mới (GĐ3) | Ghi lịch sử; đồng bộ giá → SKU; nhân bản; bulk sản phẩm; bulk điều chỉnh giá |
| Regression | Sửa lỗi "0 biến thể"; phân quyền guest 401 |
| Workflow tích hợp | Tạo → sửa giá → thêm đơn vị → nhân bản → hiển thị storefront |
| **Toàn bộ suite** | **124 passed (560 assertions)** — 0 failed |
| Import SQL (mô phỏng DB trống) | 368/368 câu lệnh OK, 36/36 bảng tạo mới, **0 lỗi** |
| CI pipeline | Smoke test **22/22**, E2E test **10/10** (trước: 8/10) |
| E2E production (`scripts/e2e-haravan-test.sh`) | Tạo SP → sinh 4 biến thể Cartesian → 3 đơn vị tính → lịch sử → nhân bản → bulk → storefront: **tất cả pass** |

### Kết quả verify trên production (`https://tunganhahihi.io.vn`)

| Hạng mục | Kết quả |
|---|---|
| Bảng `product_attributes` | **27 dòng** (trước: 0) |
| Bảng `product_attribute_values` | **35 dòng** (trước: 0) |
| Bảng `product_units` | **69 dòng** (trước: 0) |
| Sản phẩm admin hiện "0 biến thể" | **0** (trước: có lỗi) |
| Storefront hiển thị biến thể + đơn vị tính | ✅ kèm giá quy đổi đúng |

---

## 10. Dữ liệu đã backfill

Migration `2026_11_23_040000` gom `option_1/option_2` của dữ liệu cũ thành thuộc tính:

| Chỉ số | Trước | Sau |
|---|---|---|
| `product_attributes` | 0 | **27** |
| `product_attribute_values` | 0 | **35** |
| `product_variants` có `attribute_signature` | 0 | **23** |

Ví dụ:
```
Pin máy khoan không dây 12V 2.0Ah thay thế
├── Kiểu đầu   (pos 1) → Makita, Bosch, Hitachi
└── Dung lượng (pos 2) → 2.0Ah
```

Migration idempotent — chạy lại không sinh trùng.

### Đơn vị tính (seed)

`ProductUnitSeeder` bổ sung đơn vị tính cho mọi biến thể đã tồn tại:

| Chỉ số | Trước | Sau |
|---|---|---|
| `product_units` | 0 | **69** |
| Biến thể có đơn vị cơ sở (`is_base = 1`, `conversion_rate = 1`) | 0 | **23** |

Mỗi biến thể có 3 đơn vị: đơn vị cơ sở (theo loại sản phẩm) + `lô 10` (×10) + `hộp 12` (×12).
Giá quy đổi tính tự động: `giá cơ sở × hệ số quy đổi`. `barcode` để NULL.

---

## 11. Hướng dẫn sử dụng

### Tạo sản phẩm có biến thể
1. Admin → Sản phẩm → **+ Thêm sản phẩm**
2. Nhập tên, giá bán
3. Tick **"Quản lý biến thể & đơn vị tính"**
4. **+ Thêm thuộc tính** → nhập tên (VD: "Kiểu đầu") → **+ Giá trị** → gõ & Enter
5. Lặp lại cho thuộc tính thứ 2
6. Bảng biến thể **tự sinh** — chỉnh giá/SKU/tên
7. **Lưu**

### Thêm đơn vị tính
1. Trong bảng biến thể, bấm nút **"N đơn vị"**
2. **+ Thêm đơn vị** → nhập tên, tỷ lệ quy đổi
3. Đơn vị đầu tiên là **đơn vị cơ bản** (giá = giá biến thể)
4. Đơn vị sau: nhập tỷ lệ → giá tự tính
5. **Lưu đơn vị**

### Xem chi tiết biến thể
Danh sách sản phẩm → nút **"Biến thể"** → màn chi tiết (thẻ kho + đơn vị + lịch sử)

### Nhân bản
Danh sách sản phẩm → nút **"Nhân bản"** → xác nhận

### Thao tác hàng loạt
Tick nhiều sản phẩm → thanh công cụ xuất hiện phía trên bảng

---

## 12. Ghi chú & hạn chế

| Hạn chế | Ghi chú |
|---|---|
| **Barcode** | Không triển khai (theo yêu cầu). Cột `barcode` **đã có sẵn** trong `product_units` để sẵn sàng cho giai đoạn sau. |
| **Đơn vị tính chưa áp dụng vào giỏ hàng** | Đơn vị tính hiển thị trên UI & API, nhưng luồng checkout vẫn dùng SKU cơ bản. Cần GĐ4 nếu muốn bán theo đơn vị quy đổi. |
| **Tỷ lệ quy đổi không sửa được** | Đúng theo Haravan. Muốn đổi → xoá & tạo lại đơn vị. |
| **Biến thể bị ẩn vẫn còn trong DB** | Có chủ đích (giữ lịch sử đơn hàng). Cần xoá hẳn → dùng API xoá riêng. |
| **Cartesian product không giới hạn sâu** | Đã cắt ở 500 biến thể (giống Haravan) và 200 giá trị/thuộc tính. |
| **`is_base_key` dùng cột sinh** | MariaDB 10.4 & MySQL 8.0 đều hỗ trợ `storedAs`. Đã test trên cả hai. |

---

## 13. Tài liệu tham chiếu

- [Biến thể và thuộc tính của sản phẩm — Haravan](https://help.haravan.com/docs/products/create-product/thuoc-tinh-va-bien-the-cua-san-pham/)
- [Hướng dẫn quản lý sản phẩm theo đơn vị tính — Haravan](https://help.haravan.com/docs/products/create-product/huong-dan-quan-ly-san-pham-theo-don-vi-tinh/)
- [Quản lý tất cả sản phẩm — Haravan](https://help.haravan.com/docs/products/management/trang-danh-sach-san-pham/)

---

*Báo cáo được tạo tự động ngày 23/11/2026.*
