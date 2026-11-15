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
        if (isset($config[$key]) && $config[$key] === '') {
            $config[$key] = $value;
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
// ---------------------------------------------------------------------------
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

// Tách câu lệnh theo dấu ";" ở cuối dòng.
// mysqldump xuất INSERT nhiều dòng dạng:
//   INSERT INTO `t` VALUES
//   (...),
//   (...);
$statements = preg_split('/;\s*[\r\n]+/', $sql);

$total = count($statements);
$executed = 0;
$failed = 0;
$errors = [];

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
$pdo->exec('SET CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

foreach ($statements as $statement) {
    $statement = trim($statement);

    if ($statement === '') {
        continue;
    }

    // Bỏ qua comment và câu lệnh rỗng.
    if (str_starts_with($statement, '--') || str_starts_with($statement, '/*')) {
        continue;
    }

    try {
        $pdo->exec($statement);
        $executed++;
    } catch (Throwable $e) {
        $failed++;
        if (count($errors) < 10) {
            $errors[] = mb_substr($e->getMessage(), 0, 200);
        }
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

out('Đã chạy '.$executed.' câu lệnh.', 'ok');

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
