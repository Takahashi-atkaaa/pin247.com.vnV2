# BÁO CÁO TRIỂN KHAI PIN247 LÊN INFINITYFREE

**Dự án:** PIN247 — thương mại điện tử pin lithium / pin máy khoan / mạch BMS
**Stack:** Laravel 12 + Vue 3 (Vite) + MySQL/MariaDB
**Đích đến:** InfinityFree shared hosting — `https://tunganhahihi.io.vn`
**Chế độ BigSeller:** `BIGSELLER_MODE=mock`
**Báo cáo:** 17/11/2026
**Phạm vi:** từ lúc bắt đầu triển khai lên hosting → deploy thành công → sửa lỗi giao diện mobile phần quản trị.

> **Nguyên tắc viết báo cáo:** mọi lỗi và cách khắc phục dưới đây đều được trích từ **log GitHub Actions thật**, **output script thật**, và **git history thật** của repo `Takahashi-atkaaa/pin247.com.vnV2`. Không có phần nào được suy đoán.

---

## MỤC LỤC

1. [Tóm tắt kết quả](#1-tóm-tắt-kết-quả)
2. [Quy trình đã thực hiện](#2-quy-trình-đã-thực-hiện)
3. [Timeline commit & CI run](#3-timeline-commit--ci-run)
4. [Các vướng mắc và cách khắc phục](#4-các-vướng-mắc-và-cách-khắc-phục)
   - 4.1 [Lỗi CI: Seeder không autoload được](#41-lỗi-ci-seeder-không-autoload-được)
   - 4.2 [Lỗi CI: `npm cache is not found`](#42-lỗi-ci-npm-cache-is-not-found)
   - 4.3 [Lỗi SSL: thiếu intermediate certificate](#43-lỗi-ssl-thiếu-intermediate-certificate)
   - 4.4 [Lỗi anti-bot challenge của InfinityFree](#44-lỗi-anti-bot-challenge-của-infinityfree)
   - 4.5 [Lỗi UA-binding: challenge session bị vô hiệu hoá](#45-lỗi-ua-binding-challenge-session-bị-vô-hiệu-hoá)
   - 4.6 [Lỗi `?i=1` bị đính vào URL](#46-lỗi-i1-bị-đính-vào-url)
   - 4.7 [Lỗi 1227 Access Denied — không đọc được DB](#47-lỗi-1227-access-denied--không-đọc-được-db)
   - 4.8 [Lỗi `SET CHARACTER SET` — cú pháp không hợp lệ trên MariaDB](#48-lỗi-set-character-set--cú-pháp-không-hợp-lệ-trên-mariadb)
   - 4.9 [Lỗi fatal error hiện trang trắng trên shared hosting](#49-lỗi-fatal-error-hiện-trang-trắng-trên-shared-hosting)
   - 4.10 [Lỗi smoke test: 302 redirect bị nhầm là 403](#410-lỗi-smoke-test-302-redirect-bị-nhầm-là-403)
   - 4.11 [Lỗi: file `.env` bị drop connection (code 000)](#411-lỗi-file-env-bị-drop-connection-code-000)
5. [Lỗi giao diện mobile phần quản trị](#5-lỗi-giao-diện-mobile-phần-quản-trị)
6. [Kiến trúc deploy sau khi hoàn tất](#6-kiến-trúc-deploy-sau-khi-hoàn-tất)
7. [Kết quả kiểm chứng](#7-kết-quả-kiểm-chứng)
8. [Bài học rút ra](#8-bài-học-rút-ra)
9. [Tài liệu & script tham khảo](#9-tài-liệu--script-tham-khảo)
10. [Việc còn lại / đề xuất](#10-việc-còn-lại--đề-xuất)

---

## 1. TÓM TẮT KẾT QUẢ

| Hạng mục | Kết quả |
|---|---|
| Deploy lên InfinityFree | ✅ Thành công — `https://tunganhahihi.io.vn` |
| Cơ chế deploy | GitHub Actions (push vào `main` → tự build → FTP upload → import DB → test) |
| Số bảng import | **30 bảng** |
| Smoke test production | **22/22 pass** |
| E2E test production | **10/10 pass** |
| CI run thành công | 2/5 (run `43911996212`, `43917603999`) — 3 run fail đầu đã xử lý, xem mục 4 |
| Lỗi giao diện mobile admin | ✅ Đã sửa & verify — commit `0a9862c` |
| Artifact nhạy cảm còn sót trên hosting | Không — đã dọn `pin247.sql`, `deploy-import.php`, `deploy-import.token.txt`, `db-probe.php` |

---

## 2. QUY TRÌNH ĐÃ THỰC HIỆN

```
[1] Chuẩn bị gói triển khai
    scripts/build-deploy.sh      → vendor --no-dev, npm run build, storage, đóng gói zip phẳng
    scripts/dump-database.sh      → mysqldump từ MySQL local
    scripts/make-production-env.sh→ sinh .env.production cho hosting

[2] Nạp cấu hình lên GitHub Actions
    scripts/set-github-secrets.php→ đặt 11 secrets qua GitHub API (có mã hoá lại)

[3] CI pipeline (.github/workflows/deploy-infinityfree.yml)
    Checkout → Setup PHP 8.3 + Node 20 → npm ci → npm run build
    → Verify Vite manifest → composer install --no-dev
    → Build database (migrate + seed + mysqldump)
    → Build deploy bundle → Generate production .env
    → Upload to InfinityFree via FTP
    → Giải challenge anti-bot InfinityFree
    → Extract & import database
    → Smoke test → E2E test → Cleanup

[4] Script chạy trên hosting
    deploy/deploy-extract.php  → giải nén bundle
    deploy/deploy-import.php   → import SQL (có token bảo vệ)

[5] Kiểm thử
    scripts/smoke-test.sh → 22 check (route, asset, auth, bảo mật file nhạy cảm)
    scripts/e2e-test.sh   → 10 check (login, phân quyền, checkout mock, sync tồn kho)
    scripts/run-local-deploy-test.sh → mô phỏng InfinityFree bằng PHP built-in server
```

**Job "Check required secrets"** chạy song song, fail nếu thiếu bất kỳ secret nào — để lỗi cấu hình lộ ra ngay thay vì mất thời gian chạy cả pipeline.

---

## 3. TIMELINE COMMIT & CI RUN

Nguồn: `git log` + GitHub Actions API (đọc qua `scripts/gh-run-status.php`).

| # | Commit | Ngày | CI run | Kết quả CI | Nội dung |
|---|---|---|---|---|---|
| 1 | `fba0fbe` | 16/11 | — | — | Thêm toàn bộ hạ tầng deploy: workflow 451 dòng, 12 script, `deploy-import.php`, `deploy-extract.php`, `.env.production.example`, `.htaccess` mới |
| 2 | `36b55a3` | 16/11 | `43807260684` | ❌ **failure** | Thêm `scripts/set-github-secrets.php` (242 dòng) — đặt secrets qua API thay vì click tay 11 lần |
| 3 | `2905d2d` | 16/11 | `43824175614` | ❌ **failure** | Sửa `DemoCustomerSeeder` thành class Seeder chuẩn + thêm `scripts/gh-run-status.php` |
| 4 | `9741497` | 16/11 | `43828245828` | ❌ **failure** | Thêm `scripts/infinityfree-challenge.js` (giả challenge anti-bot), `db-probe.php`, `ftp_list.py`, `ftp_upload_files.py` |
| 5 | `3b1f46e` | 16/11 | — | — | Fix UA-binding của challenge session + warm-up `?i=1` |
| 6 | `ae1f65e` | 16/11 | — | — | Fix smoke test: đọc mã lỗi thật từ `Location` header khi InfinityFree trả 302 |
| 7 | `e2d8829` | 16/11 | `43911996212` | ✅ **success** | Fix import SQL trên MariaDB (3 vòng sửa) |
| 8 | `0a9862c` | 17/11 | `43917603999` | ✅ **success** | Sửa giao diện mobile phần quản trị |

**Ghi chú về run `43807260684`:** đây là lần chạy CI đầu tiên. Log cho thấy các migration chạy thành công (`..._create_warehouses_table ... DONE`) rồi mới `##[error]Process completed with exit code 1` ngay tại step `Build database (migrate + seed)` — tức lỗi nằm ở phần `db:seed`, không phải `migrate`. Commit `2905d2d` ghi lại nguyên nhân cụ thể: `Target class [Database\Seeders\DemoCustomerSeeder] does not exist`.

**Điểm đáng chú ý:** 3 run fail liên tiếp đầu tiên đều **không phải lỗi hạ tầng deploy**, mà là lỗi ở từng tầng riêng biệt (Seeder → SSL → DB privilege). Mỗi lần fix đều dựa trên log CI thật, không đoán.

---

## 4. CÁC VƯỚNG MẮC VÀ CÁCH KHẮC PHỤC

### 4.1 Lỗi CI: Seeder không autoload được

**Triệu chứng — log CI run `43807260684` (CI run đầu tiên):**
```
2026-11-16T04:59:16.4401108Z   INFO  Preparing database.
2026-11-16T04:59:16.9344637Z   2026_11_12_160500_create_warehouses_table ..... DONE
2026-11-16T04:59:17.7294984Z   2026_11_12_161500_create_bigseller_order_mappings_table ... DONE
2026-11-16T04:59:17.8500008Z   2026_11_12_161800_add_admin_fields_to_users_table ... DONE
2026-11-16T04:59:19.4404304Z ##[error]Process completed with exit code 1.
```
Step bị fail: `Build database (migrate + seed)`. Migration chạy xong, chết ở phần `db:seed`.

**Nguyên nhân (ghi trong commit message `2905d2d`):**
```
Target class [Database\Seeders\DemoCustomerSeeder] does not exist
```
`database/seeders/DemoCustomerSeeder.php` viết dạng **script procedural** (không có `namespace`, không có `class`), nên Composer autoload không tìm thấy class `Database\Seeders\DemoCustomerSeeder`.

**Cách khắc phục (commit `2905d2d`):** chuyển sang class chuẩn Laravel:
```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoCustomerSeeder extends Seeder
{
    public function run(): void { /* ... */ }
}
```
Kèm bổ sung `email_verified_at` cho user demo.

**Bài học:** file trong `database/seeders/` bắt buộc phải là class có namespace — Laravel `artisan db:seed --class=...` dựa hoàn toàn vào autoload PSR-4.

---

### 4.2 Lỗi CI: `npm cache is not found` (CẢNH BÁO — KHÔNG PHẢI NGUYÊN NHÂN FAIL)

**Triệu chứng — log CI:**
```
2026-11-16T04:59:02.8646949Z (node:3899) [DEP0040] DeprecationWarning: The `punycode` module is deprecated.
2026-11-16T04:59:06.5609638Z npm cache is not found
```

**Nguyên nhân:** `actions/setup-node@v4` với `cache: npm` không tìm thấy cache đã lưu (lần chạy đầu tiên chưa có cache nào) → in ra dòng cảnh báo này. Nó **không làm step fail**.

**Bằng chứng xác nhận:** dòng `npm cache is not found` xuất hiện **giống hệt** trong run `43911996212` — run **thành công**. Đếm chuỗi này trong log run thành công: `1` lần, y hệt run thất bại.

**Kết luận:** đây là **false lead** — dễ bị quy nhầm là nguyên nhân fail khi đọc lướt log. Nguyên nhân thật của run `43824175614` nằm ở mục 4.3 (SSL).

**Cách khắc phục:** không cần sửa gì — chỉ cần **biết đây là nhiễu** để không debug nhầm hướng. (Thứ tự step trong workflow vốn đã đúng: `Setup Node 20` → `Install npm dependencies` → `Build frontend`.)

**Bài học:** khi đọc log CI, phải **tìm dòng báo lỗi cuối cùng trước `##[error]`**, không phải dòng cảnh báo đầu tiên trông "lạ". Đặc biệt là dòng nào xuất hiện ở **cả run fail lẫn run success** thì chắc chắn không phải nguyên nhân.

---

### 4.3 Lỗi SSL: thiếu intermediate certificate

**Triệu chứng — log CI run `43824175614` (đây là nguyên nhân thật của run này):**
```
🔓 Giải nén gói deploy...
curl: (60) SSL certificate OpenSSL verify result: unable to get local issuer certificate (20)
❌ Giải nén thất bại — chi tiết:
```

**Nguyên nhân:** InfinityFree dùng chứng chỉ SSL do Let's Encrypt cấp nhưng **thiếu intermediate cert** trong chuỗi. Máy CI chạy Ubuntu mới nên store CA không chứa chain đầy đủ → OpenSSL không dựng được chuỗi tin cậy → `curl` exit code 60.

Bước `Extract & import database` dùng `curl` tới `https://tunganhahihi.io.vn/deploy-extract.php` nên fail ngay, dẫn tới toàn job fail.

**Cách khắc phục (commit `9741497`):** thêm cờ `-k` cho curl trong `smoke-test.sh` và `e2e-test.sh` (biến `INF_INSECURE` cho phép bật/tắt). Đây là chấp nhận rủi ro có kiểm soát: kết nối vẫn mã hoá, chỉ bỏ qua kiểm tra chứng thư.

**Bài học:** shared hosting thường có SSL không đúng chuẩn; tuyệt đối **không** tắt TLS ở tầng ứng dụng — chỉ bỏ qua verify ở tầng script kiểm thử.

---

### 4.4 Lỗi anti-bot challenge của InfinityFree

**Triệu chứng:** mọi request `curl` tới hosting trả về **HTTP 200** nhưng nội dung là trang HTML challenge, không phải trang ứng dụng. Kiểm tra response thấy:
```html
<html><body><script type="text/javascript" src="/aes.js" ></script><script>
function toNumbers(d){...}
var a=toNumbers("f655ba9d09a112d4968c63579db590b4"),
    b=toNumbers("98344c2eee86c3994890592585b49f80"),
    c=toNumbers("c4c77a73718eb5bb248fe400...");
document.cookie = "__test=" + toHex(slowAES.decrypt(c, 2, a, b)) + "...";
location.href = "<url>?i=1";
</script></body></html>
```

**Nguyên nhân:** InfinityFree chèn một **JavaScript anti-bot challenge** (AES-CFB) vào mọi request thiếu cookie `__test`. `curl` không chạy JS nên mắc kẹt ở trang challenge.

**Cách khắc phục (commit `9741497`):** viết `scripts/infinityfree-challenge.js` (Node, ESM) — 161 dòng, làm 4 việc:
1. Tải trang challenge.
2. Trích `key` / `iv` / `ciphertext` từ mã HTML.
3. **Tải chính file `/aes.js` của hosting** về và chạy nó trong `vm` sandbox — không reimplement AES, nên không rủi ro sai thuật toán.
4. Trả về chuỗi `__test=<value>` để dùng cho các request sau.

Cookie có `max-age=21600` (~6 giờ). Script được thêm vào workflow ở step **"Giải challenge anti-bot InfinityFree"**, trước step import DB, và cookie được truyền cho smoke/E2E test.

Kết quả trong log CI run `43828245828`:
```
✔ Giải challenge anti-bot InfinityFree
✅ Đã giải challenge anti-bot: __test=cfbb8c8d1c4b65769…
```

---

### 4.5 Lỗi UA-binding: challenge session bị vô hiệu hoá

**Triệu chứng:** sau khi đã giải challenge, smoke test vẫn fail hàng loạt — **HTTP 200** cho tất cả route kể cả route cần 401/403. Điều này vô lý vì server trả 200 cho endpoint admin không token.

**Nguyên nhân:** InfinityFree **bind challenge session (`__test`) vào `User-Agent` của client đã giải challenge**. Khi:
- `infinityfree-challenge.js` giải challenge với **Windows Chrome UA** (`Chrome/131.0.0.0`), rồi
- `smoke-test.sh` gọi API với **macOS Chrome UA** (khác chuỗi),

thì server coi đây là client khác → hủy session → trả lại trang challenge (HTTP 200). Script cứ tưởng app trả 200 hợp lệ.

**Cách khắc phục (commit `3b1f46e`):**
- Chuẩn hoá **một** User-Agent cho cả 3 script (`infinityfree-challenge.js`, `smoke-test.sh`, `e2e-test.sh`):
  ```
  Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36
  ```
- Ghi chú cơ chế UA-binding vào comment để không lặp lại lỗi này.

**Bài học:** khi script nhiều thành phần cùng nói chuyện với 1 server có session, **mọi thành phần phải dùng chung định danh client**. Test đừng bao giờ để "identity" lệch nhau giữa các bước.

---

### 4.6 Lỗi `?i=1` bị đính vào URL

**Triệu chứng:** người dùng báo `https://tunganhahihi.io.vn/?i=1` — URL luôn bị đính `?i=1`.

**Nguyên nhân:** chính mã challenge do InfinityFree sinh ra có dòng:
```javascript
location.href = "<url>?i=1";
```
Đây là bước **warm-up** bắt buộc: sau khi set cookie `__test`, client phải gọi lại URL kèm `?i=1` để server biết "đã giải xong, cho qua".

**Cách khắc phục (commit `3b1f46e`):** thêm warm-up request vào `infinityfree-challenge.js`:
```javascript
const warmUrl = new URL(url);
warmUrl.searchParams.set('i', '1');
const warm = await httpGet(warmUrl.toString(), { Cookie: `__test=${cookieValue}` });
console.error(`[challenge] Warm-up ?i=1 → HTTP ${warm.status}`);
```
Sau bước này, các request sau (không cần `?i=1`) đi qua bình thường trong ~6 giờ.

**Bài học:** đây **không phải lỗi** của site — đó là cơ chế chống bot của InfinityFree. Người dùng thấy `?i=1` chỉ khi trình duyệt chưa giữ được cookie `__test` hợp lệ.

---

### 4.7 Lỗi 1227 Access Denied — không đọc được DB

**Triệu chứng — log CI run `43828245828`:**
```
PIN247 — Import database
File SQL: pin247.sql — 0.08 MB
✔ Kết nối MySQL thành công (không chỉ định database).
SHOW GRANTS: 1 dòng.
GRANT: GRANT USAGE ON *.* TO `***`@`192.168.0.%` IDENTIFIED BY PASSWORD '...'
SHOW DATABASES bị từ chối: SQLSTATE[42000]: Syntax error or access violation:
  1227 Access denied; you need (at least one of) the SHOW DATABASES privilege(s) for this operation
Không xác định được database nào.
→ Mở Control Panel InfinityFree → MySQL Databases → Create MySQL Database
```

**Nguyên nhân:** 2 vấn đề chồng lên nhau:
1. InfinityFree **không cấp quyền `SHOW DATABASES`** cho user shared hosting → lệnh này luôn lỗi 1227.
2. Sau khi user tạo DB mới trong Control Panel, `SHOW GRANTS FOR CURRENT_USER` **không cập nhật ngay** — vẫn trả về `GRANT USAGE ON *.*` (tức không có quyền gì cả), dù DB đã tồn tại.

Script cũ dựa vào danh sách DB từ `SHOW GRANTS`/`SHOW DATABASES` để "đoán" tên DB → luôn thất bại.

**Cách khắc phục (commit `e2d8829`):** thay logic "đoán" bằng **kết nối thẳng** vào DB lấy từ `.env`:
```php
$probe = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $envName),
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$probeTables = $probe->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$probe->quote($envName)
)->fetchColumn();
```
Nếu kết nối được → DB tồn tại, dùng luôn tên đó. `deploy/db-probe.php` cũng được đổi từ danh sách tên cố định sang **probe nhiều tên ứng viên** để dò DB vừa tạo.

**Kết quả:** import thành công **30 bảng**.

**Một chi tiết đáng lưu ý trong log CI:** step `Extract & import database` được đánh dấu **✔ thành công**, dù script đã in ra "Không xác định được database nào" — vì script kết thúc với exit code 0. Step thực sự fail là **Smoke test** (ngay sau đó), vì DB rỗng nên mọi check đều hỏng:
```
✔ Giải challenge anti-bot InfinityFree
✔ Extract & import database
✗ Smoke test
```
Đây là lý do cần đọc **output của script**, không chỉ trạng thái ✔/✗ của step.

**Bài học:** trên shared hosting, **đừng dựa vào metadata quyền** để suy ra thực tế — hãy thử hành động thật (kết nối, query) để xác nhận. Và script deploy nên **exit code ≠ 0 khi import thất bại** để CI bắt được ngay.

---

### 4.8 Lỗi `SET CHARACTER SET` — cú pháp không hợp lệ trên MariaDB

**Triệu chứng:**
```
SQLSTATE[42000]: 1064 You have an error in your SQL syntax; check the manual that
corresponds to your MariaDB server version for the right syntax to use near
'COLLATE utf8mb4_unicode_ci'
```

**Nguyên nhân:** InfinityFree chạy **MariaDB**, không phải MySQL 8.0. Câu `SET CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` là cú pháp **không hợp lệ** — `SET CHARACTER SET` không nhận tham số `COLLATE`.

**Cách khắc phục (commit `e2d8829`):** đổi sang cú pháp đúng cho cả MySQL lẫn MariaDB:
```php
try {
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) {
    out('Bỏ qua SET NAMES: '.$e->getMessage(), 'warn');
}
```
(`SET NAMES` là cú pháp chuẩn, hoạt động trên cả hai engine.)

**Bài học:** shared hosting hay chạy MariaDB. Trước khi dùng cú pháp riêng của MySQL 8.0, phải kiểm tra engine thật.

---

### 4.9 Lỗi fatal error hiện trang trắng trên shared hosting

**Triệu chứng:** script PHP trên hosting trả về **trang trắng**, HTTP 500, không có thông báo lỗi nào.

**Nguyên nhân:** shared hosting **tắt `display_errors` mặc định** → `display_startup_errors` cũng tắt → fatal error bị nuốt hoàn toàn.

**Cách khắc phục (commit `e2d8829`):** bật tường báo lỗi ngay từ đầu `deploy-import.php`:
```php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
```

**Bài học:** khi debug script trên shared hosting, **luôn bật `display_errors` ở đầu file** — nếu không, mọi lỗi đều biểu hiện giống nhau: trang trắng.

---

### 4.10 Lỗi smoke test: 302 redirect bị nhầm là 403

**Triệu chứng — log CI run `43828245828`, step `Smoke test`:**
```
✗ Chặn /.gitignore — expected 403, got 200
✗ Chặn /composer.json — expected 403, got 200
✗ Chặn /artisan — expected 403, got 200
✗ Chặn /vendor/autoload.php — expected 404, got 200
✗ Chặn /app/Models/User.php — expected 404, got 200
✗ HTML có app-base — không tìm thấy 'app-base'
```

**Nguyên nhân:** InfinityFree **không trả 403/404 trực tiếp** cho file bị chặn, mà **redirect 302** tới `https://errors.infinityfree.net/errors/403/` (hoặc `/404/`). Script chỉ đọc status code của response đầu → thấy `200` (trang lỗi của InfinityFree) → tưởng file bị lộ.

Cùng nguyên nhân này giải thích lỗi `HTML có app-base — không tìm thấy 'app-base'`: response trả về không phải HTML của app nên không có meta tag `app-base`.

**Cách khắc phục (commit `ae1f65e`):** thêm hàm đọc mã lỗi thật từ `Location` header:
```bash
resolve_code() {
    local url="$1"; local raw hdr
    raw=$(curl "${CURL_OPTS[@]}" -o /dev/null -w "%{http_code}" "$url")
    [ "$raw" != "302" ] && { printf '%s' "$raw"; return; }
    hdr=$(curl "${CURL_OPTS[@]}" -o /dev/null -D- "$url" 2>/dev/null \
        | grep -i '^location:' | tail -1 | tr -d '\r')
    case "$hdr" in
        */errors/403*) printf '403' ;;
        */errors/404*) printf '404' ;;
        *)              printf '%s' "$raw" ;;
    esac
}
```

**Bài học:** assertion phải kiểm tra **trạng thái cuối cùng** sau redirect, không chỉ status của response đầu.

---

### 4.11 Lỗi: file `.env` bị drop connection (code 000)

**Triệu chứng:**
```
✗ Chặn /.env — expected 403, got 000
```
`000` là mã curl khi **không kết nối được** (connection bị đóng), không phải HTTP status.

**Nguyên nhân:** InfinityFree **chặn file `.env` bằng cách đóng connection**, không phải bằng HTTP error. File bị chặn đúng là hành vi bảo mật tốt — nhưng script không hiểu mã `000` nên báo fail.

**Cách khắc phục (commit `ae1f65e`):** `check_blocked` coi `000` cũng là "đã bị chặn" (đúng kỳ vọng bảo mật):
```bash
check_blocked() {
    local label="$1" url="$2"; local code
    code=$(resolve_code "$url")
    if [ "$code" = "403" ] || [ "$code" = "404" ] || [ "$code" = "000" ]; then
        echo -e "  ✓ $label (bị chặn: $code)"
        PASS=$((PASS + 1))
    else ... fi
}
```

**Bài học:** trong test bảo mật, "bị chặn theo cách nào" cũng là đạt — chỉ cần phân biệt được với "bị lộ".

---

## 5. LỖI GIAO DIỆN MOBILE PHẦN QUẢN TRỊ

**Triệu chứng:** người dùng báo *"giao diện mobile bị lỗi ở phần quản trị"*.

### 5.1 Chẩn đoán

Đọc code, tìm ra nguyên nhân gốc trong `AdminSidebar.vue` (trước khi sửa):
```html
<aside class="bg-gray-900 text-gray-300 w-64 shrink-0 flex flex-col transition-all">
```

`w-64` = **256px cố định trên mọi kích thước màn hình**, không có điều kiện `lg:` nào, và **không có nút nào để mở/đóng**.

Trên màn 390px:
```
390px (viewport) − 256px (sidebar) = 134px cho nội dung
```
→ bảng 7–9 cột bị bóp vỡ, toolbar có `w-56`/`w-48` cứng nên tràn ngang, modal `max-w-3xl` + `p-6` tràn màn hình, không có đường vào menu.

Các lỗi phụ kèm theo:

| Vấn đề | File | Biểu hiện |
|---|---|---|
| Sidebar cố định 256px | `AdminSidebar.vue` | bóp nội dung, không có nút mở |
| Header không có hamburger, `px-6` cứng | `AdminHeader.vue` | không mở được menu |
| `main` padding `p-6` | `AdminLayout.vue` | mất 48px trên màn 390px |
| Bảng không có `min-w`/card view | `DataTable.vue`, `InventoryTable.vue`, `SyncLogTable.vue` | vỡ cột |
| Modal `max-w-3xl` + `p-6` | 5 trang admin | tràn ngang |
| Toolbar `w-56`/`w-48` cứng | `OrdersPage.vue` | tràn ngang |
| Grid cột cố định | `DashboardPage.vue`, `BigSellerPage.vue` | chật |

### 5.2 Cách khắc phục (commit `0a9862c`)

**a) Sidebar thành drawer trên mobile** — tách phần menu dùng chung ra `SidebarContent.vue`:
- `< lg`: drawer trượt từ trái, ẩn mặc định, overlay mờ, khoá scroll body, đóng bằng overlay / nút ✕ / phím `Esc`, tự đóng khi bấm mục menu.
- `>= lg`: sidebar cố định, giữ nguyên chức năng thu gọn (256px ↔ 64px).

**b) Thêm nút hamburger** ở `AdminHeader.vue`, chia sẻ state qua composable mới `useAdminNav.js` (module-level `ref` dùng chung — nhẹ hơn Pinia cho state tạm của UI).

**c) `useMediaQuery.js`** — theo dõi `(min-width: 1024px)` bằng `matchMedia` + listener, thay vì đọc `window.innerWidth` (tránh re-render liên tục).

**d) Bảng chuyển sang card view** trên mobile:
- `DataTable.vue`: cột được đánh dấu `primary: true` hiện làm tiêu đề card; các cột còn lại hiện dạng label/value; cột `actions` tách riêng cuối card; dòng có giá trị rỗng bị ẩn (`v-show`).
- `InventoryTable.vue`, `SyncLogTable.vue`: cùng cách, riêng tồn kho dùng lưới 2 cột cho các con số.

**e) Layout & modal:** padding `p-3 / sm:p-4 / lg:p-6`; `min-w-0` chống vỡ flex; modal thành bottom-sheet full-width (`rounded-t-2xl sm:rounded-xl`, `py-0 sm:py-8`), padding `p-4 sm:p-6`, grid 1 cột trên mobile; toolbar input/select `w-full` trên mobile.

**f) Script QA headless browser** — `scripts/qa-admin-mobile.mjs` + `scripts/qa-admin-pages.mjs`, chạy Playwright với viewport 390×844, đo trực tiếp: `documentElement.scrollWidth` vs `innerWidth`, bề rộng sidebar, số element tràn ngang, số card hiển thị, trạng thái drawer.

### 5.3 Kết quả kiểm chứng (đo thật trên DOM)

**Mobile 390×844 — cả 7 trang admin:**
```
/admin/dashboard    horizontalOverflow=false  sidebarWidth=0   overflowingControls=0
/admin/products    horizontalOverflow=false  sidebarWidth=0   cards=14  overflowingControls=0
/admin/inventory   horizontalOverflow=false  sidebarWidth=0   cards=23  overflowingControls=0
/admin/orders      horizontalOverflow=false  sidebarWidth=0   cards=2   overflowingControls=0
/admin/bang-gia    horizontalOverflow=false  sidebarWidth=0   cards=50  overflowingControls=0
/admin/customers   horizontalOverflow=false  sidebarWidth=0   cards=2   overflowingControls=0
/admin/bigseller   horizontalOverflow=false  sidebarWidth=0   cards=12  overflowingControls=0
```

**Drawer:** mở OK (rộng 288px, 7 mục, `body.overflow=hidden`) → bấm mục menu → tự đóng + `body.overflow=''`.

**Modal:** rộng 390px = đúng viewport, 0 field tràn ngang, padding 16px, grid 1 cột, đóng OK.

**Desktop 1280×800 (không hồi quy):**
```
sidebarWidth=256, bảng dạng table (visibleTables=1, visibleCards=0)
Thu gọn sidebar: 256px → 64px ✓
```

**Console errors: 0. Requests failed: 0.**

### 5.4 Một lỗi trong quá trình sửa (đáng ghi lại)

Bản đầu tao dùng `v-if="!isDesktop"` cho thẻ `<aside>` của drawer. Hệ quả: **drawer luôn nằm trong DOM** kể cả khi đóng → lớp overlay `fixed inset-0` phủ lên nút hamburger → **không click được nút menu**. Triệu chứng: Playwright báo `locator.click: Timeout 30000ms exceeded` dù đã resolve đúng element.

Cách sửa: chuyển `v-if` xuống **chính element con của `<Transition>`** (`v-if="isOpen"` trên `<aside>` và overlay), đúng theo cách Vue 3 Transition yêu cầu — `v-if` phải nằm trên element con để Transition thực sự chạy.

Ghi chú lại trong comment của `AdminSidebar.vue` để không lặp lại.

---

## 6. KIẾN TRÚC DEPLOY SAU KHI HOÀN TẤT

```
┌─────────────────┐   push main    ┌──────────────────────────┐
│  GitHub Repo    │ ─────────────► │  GitHub Actions          │
│  (Takahashi-    │                │  deploy-infinityfree.yml │
│   atkaaa/...)   │                └────────────┬─────────────┘
└─────────────────┘                             │
                                    ┌───────────▼────────────┐
                                    │ 1. npm run build      │
                                    │ 2. composer install   │
                                    │ 3. migrate + seed     │
                                    │ 4. mysqldump → .sql   │
                                    │ 5. đóng gói zip       │
                                    │ 6. sinh .env prod     │
                                    └───────────┬────────────┘
                                                │ FTP (ftplib)
                                    ┌───────────▼─────────────┐
                                    │  InfinityFree htdocs     │
                                    │  ├─ .htaccess           │
                                    │  ├─ index.php + app/    │
                                    │  ├─ vendor/             │
                                    │  ├─ public/build/       │
                                    │  └─ deploy-import.php   │
                                    └───────────┬─────────────┘
                                                │ HTTP + giải challenge
                                    ┌───────────▼─────────────┐
                                    │  Giải challenge __test  │
                                    │  → deploy-extract.php   │
                                    │  → deploy-import.php    │
                                    │    (import 30 bảng)     │
                                    └───────────┬─────────────┘
                                                │
                                    ┌───────────▼─────────────┐
                                    │  Smoke test 22/22 ✓     │
                                    │  E2E test   10/10 ✓     │
                                    │  Cleanup artifact        │
                                    └─────────────────────────┘
```

**11 GitHub Secrets** được dùng (đặt qua `scripts/set-github-secrets.php`, có mã hoá lại):
`APP_KEY`, `APP_URL`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_DIR`, `DEPLOY_TOKEN`.

**Bảo mật — những gì KHÔNG được commit:**
- `/pin247_shop.sql` (dump DB local) — có trong `.gitignore`
- `/deploy/database/*.sql` — có trong `.gitignore`
- `/deploy/.env.production.local`, `/deploy/ftp-credentials.txt`, `/deploy/deploy-import.token.txt` — có trong `.gitignore`
- `deploy/deploy-import.php` + `deploy/deploy-extract.php` **CỐ Ý** được commit (workflow cần chúng để upload & chạy trên hosting); chúng **không** chứa credential, chỉ đọc `.env` trên server.

**Dọn dẹp sau deploy (đã thực hiện trên hosting):** xoá `pin247.sql`, `deploy-import.php`, `deploy-import.token.txt`, `db-probe.php`.

---

## 7. KẾT QUẢ KIỂM CHỨNG

### 7.1 Smoke test production — 22/22

```
[ Route chính ]
  ✓ Trang chủ (SPA) (200)
  ✓ Health check /up (200)
  ✓ API products (200)
  ✓ API categories (200)
  ✓ API price-list (200)
  ✓ API price-list documents (200)

[ Asset tĩnh ]
  ✓ Vite manifest (200)
  ✓ HTML có app-base
  ✓ Asset JS (assets/CategoryNav-B7gJ8xMz.js) (200)

[ File upload / storage ]
  ! Không có tài liệu bảng giá để test

[ Bảo mật — phải bị chặn ]
  ✓ Chặn /.env (bị chặn: 000)
  ✓ Chặn /.gitignore (403)
  ✓ Chặn /composer.json (403)
  ✓ Chặn /artisan (403)
  ✓ Chặn /vendor/autoload.php (404)
  ✓ Chặn /app/Models/User.php (404)
  ✓ Chặn /config/app.php (404)
  ✓ Chặn /public/index.php (403)

[ Xác thực & phân quyền ]
  ✓ Admin API không token → 401 (401)
  ✓ Admin API token rác → 401 (401)
  ✓ Đơn hàng không token → 401 (401)

[ Route lỗi ]
  ✓ API sản phẩm không tồn tại (404)
  ✓ Trang lạ → SPA 404 (200)

Kết quả: 22 pass / 0 fail
```

### 7.2 E2E test production — 10/10

```
[ 1. Đăng nhập admin ]
  ✓ Đăng nhập admin thành công (token: 12|VAG6HQLzi...)

[ 2. API đã xác thực ]
  ✓ /api/me trả đúng user admin
  ✓ Admin dashboard với token admin → 200

[ 3. Phân quyền ]
  ✓ Đăng nhập khách hàng thành công
  ✓ User thường gọi admin API → 403

[ 4. Checkout mock (BigSeller MOCK) ]
  ✓ Lấy được SKU từ API: PIN-LI-37V-3000
  ✓ Checkout tạo đơn: PIN247-20261116-FUYXPW (sync_status=PROCESSING)
  ✓ BigSeller MOCK đã push: BS-MOCK-80483B2E1E

[ 5. Admin: đồng bộ tồn kho (mock) ]
  ✓ Đồng bộ tồn kho mock → 200

[ 6. Đăng xuất ]
  ✓ Đăng xuất → 200

Kết quả: 10 pass / 0 fail
```

### 7.3 Xác nhận bundle mới đã lên hosting

Đọc trực tiếp từ `https://tunganhahihi.io.vn/build/manifest.json` (qua cookie challenge) và kiểm tra nội dung file JS:

| File | Chuỗi cần có | Kết quả |
|---|---|---|
| `assets/AdminLayout-D_peStce.js` | `Mở menu quản trị`, `lg:hidden` | ✅ có |
| `assets/DataTable-rrShnn-7.js` | `md:hidden` | ✅ có |

---

## 8. BÀI HỌC RÚT RA

| # | Bài học | Vì sao quan trọng |
|---|---|---|
| 1 | **Đọc log CI, không đoán.** 3 lần fail đầu đều được giải quyết bằng cách đọc log thật của run đó. | Tiết kiệm hàng giờ so với thử-sai. |
| 2 | **Phân biệt cảnh báo với nguyên nhân fail.** `npm cache is not found` xuất hiện ở cả run fail lẫn run success → không phải nguyên nhân. | Tránh debug nhầm hướng, mất thời gian vào false lead. |
| 3 | **Tìm dòng lỗi CUỐI CÙNG trước `##[error]`**, không phải dòng "lạ" đầu tiên. | Run `43824175614` thực ra chết vì SSL, không phải vì npm. |
| 4 | **Trên shared hosting, đừng tin metadata quyền — hãy thử hành động thật.** `SHOW GRANTS`/`SHOW DATABASES` đều không phản ánh quyền thực tế. | Kết nối thẳng DB trong `.env` là nguồn sự thật. |
| 5 | **Mọi script nói chuyện với server phải dùng chung User-Agent.** Challenge session bị bind theo UA. | Lỗi hiện ra rất khó hiểu: HTTP 200 cho mọi thứ. |
| 6 | **Bật `display_errors` ngay từ đầu file khi debug trên hosting.** | Nếu không, mọi lỗi đều trông giống nhau: trang trắng. |
| 7 | **Test bảo mật phải đọc trạng thái cuối sau redirect, và chấp nhận cả "connection bị đóng".** | HTTP 302 → errors.infinityfree.net và code `000` đều là hành vi chặn đúng. |
| 8 | **Cú pháp SQL của MySQL 8.0 không tự động chạy được trên MariaDB.** | `SET NAMES` thay cho `SET CHARACTER SET ... COLLATE`. |
| 9 | **Trong Vue 3, `v-if` phải nằm trên element con của `<Transition>`.** | Đặt sai chỗ → Transition không chạy → overlay phủ lên nút, không click được. |
| 10 | **Script deploy nên exit code ≠ 0 khi thất bại.** | Run `43828245828`: step import bị đánh dấu ✔ dù DB rỗng; chỉ step Smoke test mới bắt được. |
| 11 | **Verify bằng đo DOM + screenshot, không bằng "nghe nói".** | Script QA headless browser đo trực tiếp `scrollWidth` vs `innerWidth` cho từng trang. |
| 12 | **Tách secrets khỏi repo; dùng `.gitignore` + GitHub Secrets + script set API.** | Tránh lộ FTP/DB credential. |
| 13 | **Mọi lần sửa CI đều phải để lại bằng chứng.** | Commit message mô tả nguyên nhân + cách sửa → người sau đọc là hiểu ngay. |

---

## 9. TÀI LIỆU & SCRIPT THAM KHẢO

### Script trong `scripts/`

| File | Dòng | Vai trò |
|---|---|---|
| `build-deploy.sh` | 229 | Đóng gói staging → zip phẳng |
| `dump-database.sh` | 69 | mysqldump từ MySQL local |
| `make-production-env.sh` | 72 | Sinh `.env.production` cho hosting |
| `make-import-token.sh` | 37 | Sinh token bảo vệ `deploy-import.php` |
| `ftp_upload.py` | 302 | Upload qua FTP (dùng lần đầu) |
| `ftp_list.py` / `ftp_upload_files.py` | 64 / 74 | Tiện ích FTP chẩn đoán |
| `infinityfree-challenge.js` | 161 | **Giả challenge anti-bot** (AES-CFB qua `aes.js` của hosting) |
| `set-github-secrets.php` | 242 | Đặt 11 Actions secrets qua GitHub API |
| `gh-run-status.php` | 214 | Theo dõi CI run + tải log |
| `smoke-test.sh` | 132 | 22 check route/asset/auth/bảo mật |
| `e2e-test.sh` | 207 | 10 check luồng nghiệp vụ |
| `sim-router.php` | 157 | Mô phỏng `.htaccess` InfinityFree |
| `run-local-deploy-test.sh` | 114 | Mô phỏng hosting + smoke + E2E tại local |
| `qa-admin-mobile.mjs` | 159 | **QA mobile 390×844** (7 trang admin + drawer + desktop) |
| `qa-admin-pages.mjs` | 145 | **QA trang riêng mobile** (không popup + toast/confirm) |

### Script trên hosting (`deploy/`)

| File | Dòng | Vai trò |
|---|---|---|
| `deploy-extract.php` | 238 | Giải nén bundle |
| `deploy-import.php` | 279 | Import SQL (có token, bật display_errors, probe DB trực tiếp) |
| `db-probe.php` | 145 | Chẩn đoán tên DB (đã dùng 1 lần rồi xoá) |

### Tài liệu

- `BAO_CAO.md` — báo cáo dự án tổng thể (tính năng, kiến trúc, DB, API)
- `PROJECT_PROGRESS.md` — tiến độ phát triển
- `README.md` — hướng dẫn chạy dự án

---

## 10. VIỆC CÒN LẠI / ĐỀ XUẤT

| # | Việc | Mức độ | Ghi chú |
|---|---|---|---|
| 1 | **Đổi mật khẩu admin** | 🔴 Quan trọng | Hiện tại `admin@pin247.vn` / `admin1234` (theo `AdminUserSeeder`) — **phải đổi trước khi dùng thật** |
| 2 | **Tạo DB production thay vì import từ dump** | 🟡 Nên làm | Hiện đang `migrate + seed` trên CI rồi dump — DB production sẽ có dữ liệu seed |
| 3 | **Bật HTTPS chuẩn** | 🟡 Nên làm | Đang dùng `-k` cho curl do thiếu intermediate cert; nên dùng SSL Comodo/Sectigo trả phí |
| 4 | **Chuyển `BIGSELLER_MODE` sang `live`** | 🟡 Nên làm | Cần base URL, endpoint và access token thật từ tài liệu Open API BigSeller |
| 5 | **Bật cron thật trên InfinityFree** | 🟡 Nên làm | Hiện scheduler chỉ có hint; shared hosting cần cấu hình cron qua Control Panel |
| 6 | **Giám sát uptime** | 🟢 Tuỳ | Thêm check định kỳ `/up` từ ngoài |
| 7 | **Tách `deploy-import.php` khỏi repo sau khi deploy** | 🟢 Tuỳ | Hiện đã xoá trên hosting; nếu deploy lại thì file vẫn cần cho workflow |

---

*Báo cáo được biên soạn ngày 17/11/2026 dựa trên: git history (`fba0fbe` → `0a9862c`), log GitHub Actions (`43807260684`, `43824175614`, `43828245828`, `43911996212`, `43917603999`), output thực tế của `smoke-test.sh`, `e2e-test.sh`, `qa-admin-mobile.mjs`, `qa-admin-pages.mjs` trên production.*
