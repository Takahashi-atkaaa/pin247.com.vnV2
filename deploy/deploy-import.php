<?php

/**
 * =============================================================================
 *  PIN247 — script import SQL dump lên database InfinityFree
 *
 *  DÙNG MỘT LẦN. Tự xoá file sau khi chạy thành công.
 *
 *  CÁCH DÙNG:
 *      1. Upload `pin247.sql` và file này lên htdocs (cùng thư mục).
 *      2. Đặt TOKEN trong file `deploy-import.token.txt` (cùng thư mục),
 *         hoặc dùng tham số `?token=`.
 *      3. Mở trình duyệt:
 *         https://<domain>/deploy-import.php?token=<TOKEN>
 *      4. Xác nhận → script import và tự xoá bản thân + file SQL + token.
 *
 *  AN TOÀN:
 *      - Bắt buộc có token đúng mới chạy được.
 *      - KHÔNG xoá bảng nào ngoài `DROP TABLE IF EXISTS` trong chính file dump.
 *      - Tự xoá chính nó sau khi import thành công.
 * =============================================================================
 */

// Bật hiển thị lỗi để debug được trên shared hosting (InfinityFree tắt
// display_errors mặc định => fatal error chỉ hiện trang trắng 500).
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------------------------
// Cấu hình — đọc từ file .env cạnh bên (không cần sửa code)
// ---------------------------------------------------------------------------
$config = [
    'db_host'     => getenv('DB_HOST') ?: '',
    'db_port'     => getenv('DB_PORT') ?: '3306',
    'db_name'     => getenv('DB_DATABASE') ?: '',
    'db_user'     => getenv('DB_USERNAME') ?: '',
    'db_pass'     => getenv('DB_PASSWORD') ?: '',
    'sql_file'    => __DIR__.'/pin247.sql',
    'token_file'  => __DIR__.'/deploy-import.token.txt',
];

// Đọc .env nếu có.
//
// LƯU Ý: tên biến trong .env là CHỮ HOA (DB_HOST, DB_DATABASE, ...), còn
// khóa trong $config là chữ thường (db_host, db_name, ...). Phải ánh xạ
// tường minh, nếu không sẽ không đọc được gì => "Thiếu cấu hình".
$envMap = [
    'DB_HOST'     => 'db_host',
    'DB_PORT'     => 'db_port',
    'DB_DATABASE' => 'db_name',
    'DB_USERNAME' => 'db_user',
    'DB_PASSWORD' => 'db_pass',
];

$envFile = __DIR__.'/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (isset($envMap[$key]) && $config[$envMap[$key]] === '') {
            $config[$envMap[$key]] = $value;
        }
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function out(string $msg, string $level = 'info'): void
{
    $colors = [
        'info'  => '#0b7285',
        'ok'    => '#2b8a3e',
        'warn'  => '#e67700',
        'error' => '#c92a2a',
    ];
    $color = $colors[$level] ?? '#333';

    echo '<div style="padding:6px 10px;border-left:4px solid '.$color.';margin:6px 0;background:#f8f9fa;font-family:ui-monospace,monospace;font-size:13px">'
        .htmlspecialchars($msg, ENT_QUOTES, 'UTF-8').'</div>';
}

function finish(string $title, string $level = 'ok'): void
{
    echo '<h3 style="font-family:system-ui;margin-top:24px">'.htmlspecialchars($title).'</h3>';
    echo '<p style="font-family:system-ui;font-size:13px;color:#666">Xong — trang này có thể đóng lại.</p>';
    exit;
}

// ---------------------------------------------------------------------------
// 1. Kiểm tra token
// ---------------------------------------------------------------------------
$token = $_GET['token'] ?? '';

if ($token === '' && is_file($config['token_file'])) {
    $token = trim((string) file_get_contents($config['token_file']));
}

// Token hợp lệ = token trong file (nếu có) KHỚP token trong query string.
// Nếu không có file token thì token trong query string được dùng trực tiếp
// (nhưng phải dài >= 16 ký tự).
if (is_file($config['token_file'])) {
    $expected = trim((string) file_get_contents($config['token_file']));

    if ($expected === '' || ! hash_equals($expected, $token)) {
        http_response_code(403);
        echo '<h2>403 — Token không hợp lệ</h2>';
        exit;
    }
} elseif (strlen($token) < 16) {
    http_response_code(403);
    echo '<h2>403 — Thiếu hoặc sai token</h2>';
    echo '<p>Yêu cầu token hợp lệ: <code>?token=...</code> (tối thiểu 16 ký tự).</p>';
    exit;
}

// ---------------------------------------------------------------------------
// 2. Kiểm tra môi trường
// ---------------------------------------------------------------------------
echo '<h2>PIN247 — Import database</h2>';

$missing = [];
foreach (['db_host', 'db_name', 'db_user'] as $key) {
    if ($config[$key] === '') {
        $missing[] = $key;
    }
}

if ($missing) {
    out('Thiếu cấu hình: '.implode(', ', $missing).'. Sửa trong file .env cạnh bên.', 'error');
    exit;
}

if (! is_file($config['sql_file'])) {
    out('Không tìm thấy file SQL: '.$config['sql_file'], 'error');
    exit;
}

$sqlSize = filesize($config['sql_file']);
out('File SQL: '.basename($config['sql_file']).' — '.round($sqlSize / 1024 / 1024, 2).' MB');

// ---------------------------------------------------------------------------
// 3. Kết nối DB
//
// LƯU Ý QUAN TRỌNG — tự dò database:
//   InfinityFree cấp cho mỗi tài khoản một (hoặc vài) database có tên dạng
//   `if0_41917067_<tên>`. Tên chính xác phải xem trong Control Panel.
//   Nếu DB_DATABASE trong .env sai => lỗi 1044 "Access denied ... to database".
//
//   => Script tự liệt kê các database user được phép, dùng database đầu tiên
//      nếu tên trong .env không tồn tại. Nhờ vậy deploy vẫn chạy được dù
//      config DB_DATABASE bị sai.
// ---------------------------------------------------------------------------
$pdo = null;
$chosenDb = $config['db_name'];
$available = [];

try {
    // Kết nối KHÔNG chỉ định database để xem được danh sách.
    $rootPdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['db_host'], $config['db_port']),
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    out('✔ Kết nối MySQL thành công (không chỉ định database).', 'ok');
} catch (Throwable $e) {
    out('Lỗi kết nối MySQL: '.$e->getMessage(), 'error');
    exit;
}

// --- Tìm database khả dụng ---
//
// InfinityFree KHÔNG cấp quyền `SHOW DATABASES` (lỗi 1227). Cách duy nhất
// là đọc `SHOW GRANTS` — grant của InfinityFree có dạng:
//     GRANT ALL PRIVILEGES ON `if0_41917067_<tên>`.* TO `if0_41917067`@`%`
// => tên database nằm ngay trong grant.
$available = [];

try {
    $grants = $rootPdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($grants as $grant) {
        // Dạng grant của InfinityFree:
        //   GRANT ALL PRIVILEGES ON `if0_41917067_<tên>`.* TO `if0_41917067`@`%`
        if (preg_match('/ON\s+`([^`]+)`\.\*/i', $grant, $m)) {
            $available[] = $m[1];
        }
    }

    out('SHOW GRANTS: '.count($grants).' dòng.', 'info');

    foreach ($grants as $grant) {
        out('  GRANT: '.$grant, 'info');
    }

    if ($available !== []) {
        out('Database được cấp quyền: '.implode(', ', $available), 'ok');
    }
} catch (Throwable $e) {
    out('SHOW GRANTS bị từ chối: '.$e->getMessage(), 'warn');
}

// Fallback: thử `SHOW DATABASES`.
if ($available === []) {
    try {
        $available = $rootPdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        out('Database khả dụng (SHOW DATABASES): '.(implode(', ', $available) ?: '(không có)'), 'info');
    } catch (Throwable $e) {
        out('SHOW DATABASES bị từ chối: '.$e->getMessage(), 'warn');
    }
}

// ---------------------------------------------------------------------------
// Fallback #2: thử kết nối thẳng tới DB trong .env.
//
// LƯU Ý — InfinityFree cập nhật grant DB **động**: ngay sau khi tạo DB mới
// trong Control Panel, `SHOW GRANTS` vẫn trả về `GRANT USAGE ON *.*` (chưa
// cập nhật), nên bước dò bằng grant sẽ fail dù DB đã tồn tại thật.
// Cách đúng: thử connect thẳng vào `DB_DATABASE` — nếu được thì DB tồn tại.
//
// Đây cũng là lý do script phải ưu tiên tên trong .env thay vì "database đầu
// tiên trong danh sách".
// ---------------------------------------------------------------------------
if ($available === [] || ! in_array($config['db_name'], $available, true)) {
    $envName = $config['db_name'];

    if ($envName !== '') {
        try {
            $probe = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $envName),
                $config['db_user'],
                $config['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $probeTables = $probe->query(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$probe->quote($envName)
            )->fetchColumn();

            $available = [$envName];
            out('Kết nối thẳng DB trong .env thành công: '.$envName.' ('.$probeTables.' bảng).', 'ok');
        } catch (Throwable $e) {
            out('Không kết nối được DB trong .env ('.$envName.'): '.$e->getMessage(), 'warn');
        }
    }
}

if ($available === []) {
    out('Không xác định được database nào.', 'error');
    out('→ Mở Control Panel InfinityFree → MySQL Databases → Create MySQL Database,', 'warn');
    out('  rồi dùng đúng tên đó cho DB_DATABASE.', 'warn');
    exit;
}

// Chọn database: ưu tiên tên trong .env nếu tồn tại, không thì lấy cái đầu tiên.
if (! in_array($chosenDb, $available, true)) {
    $chosenDb = $available[0];
    out('DB_DATABASE trong .env không tồn tại — tự động dùng: '.$chosenDb, 'warn');
}

$config['db_name'] = $chosenDb;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']),
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    out('Đã kết nối MySQL: '.$config['db_host'].' / '.$config['db_name'], 'ok');
} catch (Throwable $e) {
    out('Lỗi kết nối MySQL: '.$e->getMessage(), 'error');
    exit;
}

// ---------------------------------------------------------------------------
// 4. Bước xác nhận (không import khi chưa xác nhận)
// ---------------------------------------------------------------------------
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (! $confirm) {
    $existing = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ".$pdo->quote($config['db_name'])
    )->fetchColumn();

    echo '<div style="font-family:system-ui;font-size:14px;margin:16px 0">';
    echo '<p><strong>Sẽ import:</strong> '.$sqlSize.' bytes SQL vào database <code>'.$config['db_name'].'</code>.</p>';
    echo '<p><strong>Số bảng hiện tại:</strong> '.$existing.'</p>';
    echo '<p style="color:#c92a2a"><strong>Cảnh báo:</strong> nếu database đã có bảng cùng tên, import có thể lỗi. '
        .'Khuyến nghị tạo database MỚI cho bản demo.</p>';
    echo '</div>';

    $url = $_SERVER['REQUEST_URI'];
    $confirmUrl = $url.(str_contains($url, '?') ? '&' : '?').'confirm=yes';

    echo '<p style="font-family:system-ui">'
        .'<a href="'.htmlspecialchars($confirmUrl).'" '
        .'style="display:inline-block;padding:10px 20px;background:#2b8a3e;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">'
        .'BẮT ĐẦU IMPORT</a></p>';

    echo '<p style="font-family:system-ui;font-size:12px;color:#666">'
        .'Hoặc dùng phpMyAdmin → Import nếu muốn kiểm soát thủ công.</p>';

    finish('Sẵn sàng — chưa import gì.', 'warn');
}

// ---------------------------------------------------------------------------
// 5. Import SQL
// ---------------------------------------------------------------------------
$sql = file_get_contents($config['sql_file']);

if ($sql === false || trim($sql) === '') {
    out('File SQL rỗng hoặc không đọc được.', 'error');
    exit;
}

// ---------------------------------------------------------------------------
// 5a. Tách câu lệnh.
//
// BUG QUAN TRỌNG (đã gây lỗi import production):
//   Regex cũ `preg_split('/;\s*[\r\n]+/', $sql)` KHÔNG tách đúng file do
//   mysqldump sinh ra. mysqldump xuất:
//
//     -- Table structure for table `x`
//     DROP TABLE IF EXISTS `x`;
//     /*!40101 SET @saved_cs_client = @@character_set_client */;
//
//   Vì `;` của `DROP TABLE` nằm GIỮA dòng (không phải cuối dòng), regex
//   cũ cắt sai chỗ => `DROP TABLE` bị dính vào một khối bắt đầu bằng `--`
//   => script bỏ qua khối đó (coi là comment) => KHÔNG DROP được bảng cũ
//   => `CREATE TABLE` báo "Table already exists"
//   => `INSERT` báo "Duplicate entry for key PRIMARY"
//   => dữ liệu import sai (bảng mới rỗng, bảng cũ giữ dữ liệu cũ).
//
// Cách sửa: quét ký tự từng cái, cắt ở `;` CHỈ khi không nằm trong
//   - khối `/* ... */` (comment thường và `/*! ... */`)
//   - chuỗi literal `'...'`
// Sau đó bỏ các dòng comment thuần (`-- ...`, `# ...`) nhưng GIỮ phần lệnh
//   nằm sau chúng.
// ---------------------------------------------------------------------------
$statements = [];
$buffer = '';
$len = strlen($sql);
$inBlockComment = false;   // đang trong /* ... */
$inVersionComment = false; // đang trong /*! ... */ (MySQL-specific)
$inString = false;

for ($i = 0; $i < $len; $i++) {
    $char = $sql[$i];
    $prev = $i > 0 ? $sql[$i - 1] : '';

    // --- Bắt đầu / kết thúc khối comment ---
    if (! $inBlockComment && $char === '/' && ($sql[$i + 1] ?? '') === '*') {
        $inBlockComment = true;
        $inVersionComment = ($sql[$i + 2] ?? '') === '!';
        $buffer .= $char;
        continue;
    }

    if ($inBlockComment && $char === '*' && ($sql[$i + 1] ?? '') === '/') {
        $inBlockComment = false;
        $inVersionComment = false;
        $buffer .= $char;
        continue;
    }

    // --- Escape trong chuỗi literal ---
    if ($inString && $char === '\\') {
        $buffer .= $char.($sql[$i + 1] ?? '');
        $i++;
        continue;
    }

    if (! $inBlockComment && $char === "'") {
        $inString = ! $inString;
        $buffer .= $char;
        continue;
    }

    $buffer .= $char;

    // --- Điểm cắt câu lệnh ---
    if ($char === ';' && ! $inBlockComment && ! $inString) {
        $statements[] = trim($buffer);
        $buffer = '';
    }
}

if (trim($buffer) !== '') {
    $statements[] = trim($buffer);
}

// Bỏ các khối rỗng.
$statements = array_values(array_filter($statements, fn ($s) => $s !== ''));

// Tách comment đầu dòng (`--` hoặc `#`) khỏi phần lệnh còn lại.
//
// Mục đích: `DROP TABLE` đứng sau dòng comment phải được giữ lại và thực
// thi. Trước đây cả khối bị bỏ qua chỉ vì bắt đầu bằng `--`.
$cleaned = [];

foreach ($statements as $statement) {
    $lines = preg_split('/\r?\n/', $statement);
    $sqlLines = [];

    foreach ($lines as $line) {
        $trimmed = ltrim($line);

        // Bỏ dòng comment thuần (không có lệnh nào sau nó).
        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }

        $sqlLines[] = $line;
    }

    $sql = trim(implode("\n", $sqlLines));

    if ($sql !== '') {
        $cleaned[] = $sql;
    }
}

$statements = $cleaned;

$total = count($statements);
$executed = 0;
$failed = 0;
$recreated = 0;
$skipped = 0;
$errors = [];

// ---------------------------------------------------------------------------
// 5b. Tắt kiểm tra foreign key.
//
// InfinityFree chạy MariaDB và có thể KHÔNG cho đặt FOREIGN_KEY_CHECKS=0
// (một số shared host chặn session variable này). Nếu không tắt được thì
// `DROP TABLE` sẽ fail vì bảng khác đang có FK trỏ tới → "already exists"
// → "Duplicate entry for key PRIMARY" → dữ liệu import sai.
//
// Cách phòng: TRƯỚC khi import, xoá toàn bộ bảng theo thứ tự đảo ngược
// phụ thuộc, bằng chính MySQL để đảm bảo FK được dọn đúng.
// ---------------------------------------------------------------------------
$fkDisabled = false;

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $fkDisabled = true;
    out('Đã tắt kiểm tra foreign key (FOREIGN_KEY_CHECKS=0).', 'ok');
} catch (Throwable $e) {
    out('Không tắt được FOREIGN_KEY_CHECKS: '.$e->getMessage(), 'warn');
}

// Nếu không tắt được FK, dọn bảng cũ trước để import sạch.
if (! $fkDisabled) {
    try {
        $tables = $pdo->query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = '
            .$pdo->quote($config['db_name']).' AND table_type = "BASE TABLE"'
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach (array_reverse($tables) as $table) {
            try {
                $pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`', '', $table).'`');
            } catch (Throwable $e) {
                // Bảng còn FK tới bảng khác → thử lại sau.
            }
        }

        out('Đã dọn '.count($tables).' bảng cũ (không tắt được FK).', 'ok');
    } catch (Throwable $e) {
        out('Dọn bảng cũ thất bại: '.$e->getMessage(), 'warn');
    }
}

$pdo->exec('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

// LƯU Ý: InfinityFree chạy MariaDB, không phải MySQL 8.0.
// `SET CHARACTER SET x COLLATE y` là cú pháp KHÔNG hợp lệ (báo lỗi 1064) —
// phải dùng `SET NAMES`.
// Các lệnh SET này chỉ là tối ưu, không bắt buộc => bọc try/catch để lỗi
// không làm sập cả quá trình import.
try {
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) {
    out('Bỏ qua SET NAMES: '.$e->getMessage(), 'warn');
}

foreach ($statements as $statement) {
    $statement = trim($statement);

    if ($statement === '') {
        continue;
    }

    try {
        $pdo->exec($statement);
        $executed++;
    } catch (Throwable $e) {
        $message = $e->getMessage();

        // Lỗi "bảng đã tồn tại": xoá bảng cũ rồi thử lại.
        //
        // Nguyên nhân gốc rễ: `DROP TABLE` trong dump bị MariaDB từ chối vì
        // bảng khác đang có foreign key trỏ tới. Nếu FOREIGN_KEY_CHECKS=0
        // không bật được thì phải tự xoá bảng trước rồi tạo lại.
        if (str_contains($message, 'already exists')) {
            if (preg_match('/Table\s+[\'"`]?([^\'"`\s]+)[\'"`]?\s+already exists/i', $message, $m)) {
                $table = str_replace('`', '', $m[1]);

                try {
                    $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
                    $pdo->exec($statement);
                    $executed++;
                    $recreated++;
                    continue;
                } catch (Throwable $e2) {
                    $message = $e2->getMessage();
                }
            }
        }

        // Lỗi "duplicate entry": dữ liệu đã tồn tại từ lần import trước.
        // Bỏ qua — dữ liệu cũ đã có sẵn, không cần chèn lại.
        if (str_contains($message, 'Duplicate entry')) {
            $skipped++;
            continue;
        }

        $failed++;
        if (count($errors) < 10) {
            $errors[] = mb_substr($message, 0, 200);
        }
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

out('Đã chạy '.$executed.' câu lệnh.', 'ok');

if ($recreated > 0) {
    out('  ('.$recreated.' bảng được tạo lại do đã tồn tại từ lần import trước).', 'info');
}

if ($skipped > 0) {
    out('  ('.$skipped.' câu INSERT bị bỏ qua vì dữ liệu đã tồn tại).', 'info');
}

if ($failed > 0) {
    out('Có '.$failed.' câu lệnh lỗi. 10 lỗi đầu tiên:', 'warn');
    foreach ($errors as $message) {
        out('  • '.$message, 'error');
    }
}

// ---------------------------------------------------------------------------
// 6. Kiểm tra kết quả
// ---------------------------------------------------------------------------
$tableCount = $pdo->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$pdo->quote($config['db_name'])
)->fetchColumn();

out('Số bảng sau import: '.$tableCount, 'ok');

foreach (['users', 'products', 'skus', 'price_tiers', 'categories', 'orders', 'bigseller_connections'] as $table) {
    try {
        $count = $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
        out('  '.$table.' = '.$count.' dòng');
    } catch (Throwable $e) {
        out('  '.$table.' → lỗi: '.$e->getMessage(), 'warn');
    }
}

// Kiểm tra mock connection.
try {
    $row = $pdo->query('SELECT is_mock, shop_code FROM bigseller_connections ORDER BY id DESC LIMIT 1')->fetch();
    if ($row) {
        out('BigSeller connection: shop_code='.$row['shop_code'].', is_mock='.$row['is_mock'],
            ((int) $row['is_mock'] === 1) ? 'ok' : 'warn');
    }
} catch (Throwable $e) {
    // bỏ qua
}

// ---------------------------------------------------------------------------
// 7. Tự dọn dẹp
// ---------------------------------------------------------------------------
@unlink($config['sql_file']);
@unlink(__FILE__);
@unlink($config['token_file']);

out('Đã xoá file SQL, file import và file token.', 'ok');

finish('Import hoàn tất.');
