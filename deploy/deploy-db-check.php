<?php
/**
 * =============================================================================
 *  PIN247 — script kiểm tra DB (DEBUG, dùng 1 lần)
 *
 *  Upload lên htdocs cùng file `deploy-import.token.txt` rồi gọi:
 *      https://<domain>/deploy-db-check.php?token=<TOKEN>&confirm=yes
 *
 *  Tự xoá sau khi chạy.
 * =============================================================================
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

function out(string $msg, string $level = 'info'): void
{
    $colors = ['info' => '#0b7285', 'ok' => '#2b8a3e', 'warn' => '#e67700', 'error' => '#c92a2a'];
    $color = $colors[$level] ?? '#333';

    echo '<div style="padding:6px 10px;border-left:4px solid '.$color.';margin:6px 0;background:#f8f9fa;font-family:ui-monospace,monospace;font-size:13px">'
        .htmlspecialchars($msg, ENT_QUOTES, 'UTF-8').'</div>';
}

// --- Token ---
$token = $_GET['token'] ?? '';
$tokenFile = __DIR__.'/deploy-import.token.txt';

if (is_file($tokenFile)) {
    $expected = trim((string) file_get_contents($tokenFile));

    if ($expected === '' || ! hash_equals($expected, $token)) {
        http_response_code(403);
        echo '<h2>403 — Token không hợp lệ</h2>';
        exit;
    }
} elseif (strlen($token) < 16) {
    http_response_code(403);
    echo '<h2>403 — Thiếu token</h2>';
    exit;
}

$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (! $confirm) {
    echo '<p><a href="?token='.htmlspecialchars($token).'&confirm=yes" '
        .'style="display:inline-block;padding:10px 20px;background:#2b8a3e;color:#fff;text-decoration:none;border-radius:6px">CHẠY</a></p>';
    exit;
}

// --- Config từ .env ---
$envMap = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
$cfg = [];

foreach (file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
        continue;
    }

    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v, " \t\n\r\0\x0B\"'");

    if (in_array($k, $envMap, true)) {
        $cfg[$k] = $v;
    }
}

$dbName = $cfg['DB_DATABASE'] ?? '';
$port = $cfg['DB_PORT'] ?? '3306';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['DB_HOST'], $port, $dbName),
        $cfg['DB_USERNAME'],
        $cfg['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    out('Đã kết nối: '.$cfg['DB_HOST'].' / '.$dbName, 'ok');
} catch (Throwable $e) {
    out('Lỗi kết nối: '.$e->getMessage(), 'error');
    exit;
}

out('version: '.$pdo->query('SELECT VERSION()')->fetchColumn(), 'info');

echo '<h3>Bảng</h3>';

foreach ([
    'personal_access_tokens', 'products', 'product_variants',
    'product_attributes', 'product_attribute_values', 'product_units',
    'product_variant_histories',
] as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        out(sprintf('%-30s %6d rows', $table, $count), 'ok');
    } catch (Throwable $e) {
        out(sprintf('%-30s ERROR: %s', $table, substr($e->getMessage(), 0, 90)), 'error');
    }
}

echo '<h3>personal_access_tokens</h3>';

foreach ($pdo->query('SELECT id, name, tokenable_id, created_at FROM personal_access_tokens ORDER BY id') as $row) {
    out(sprintf('id=%s name=%s tokenable_id=%s created=%s',
        $row['id'], $row['name'], $row['tokenable_id'], $row['created_at']), 'info');
}

echo '<h3>products (has_variants)</h3>';

foreach ($pdo->query('SELECT id, name, has_variants FROM products ORDER BY id LIMIT 8') as $row) {
    out(sprintf('id=%s has_variants=%s %s', $row['id'], $row['has_variants'], substr((string) $row['name'], 0, 46)), 'info');
}

@unlink(__FILE__);

echo '<p style="font-family:system-ui">Xong — trang này có thể đóng lại.</p>';
