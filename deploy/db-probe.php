<?php

/**
 * =============================================================================
 *  PIN247 — probe MySQL (DÙNG MỘT LẦN, tự xoá)
 *
 *  Tìm tên database đúng của tài khoản InfinityFree.
 * =============================================================================
 */

$token = $_GET['token'] ?? '';

if (strlen($token) < 16) {
    http_response_code(403);
    echo '<h2>403 — Thiếu token</h2>';
    exit;
}

$tokenFile = __DIR__.'/deploy-import.token.txt';

if (is_file($tokenFile)) {
    $expected = trim((string) file_get_contents($tokenFile));

    if ($expected === '' || ! hash_equals($expected, $token)) {
        http_response_code(403);
        echo '<h2>403 — Token không hợp lệ</h2>';
        exit;
    }
}

$config = [
    'db_host' => getenv('DB_HOST') ?: '',
    'db_port' => getenv('DB_PORT') ?: '3306',
    'db_name' => getenv('DB_DATABASE') ?: '',
    'db_user' => getenv('DB_USERNAME') ?: '',
    'db_pass' => getenv('DB_PASSWORD') ?: '',
];

$envMap = [
    'DB_HOST' => 'db_host', 'DB_PORT' => 'db_port', 'DB_DATABASE' => 'db_name',
    'DB_USERNAME' => 'db_user', 'DB_PASSWORD' => 'db_pass',
];

if (is_file(__DIR__.'/.env')) {
    foreach (file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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

function out(string $msg, string $level = 'info'): void
{
    $colors = ['info' => '#0b7285', 'ok' => '#2b8a3e', 'warn' => '#e67700', 'error' => '#c92a2a'];
    $color = $colors[$level] ?? '#333';
    echo '<div style="padding:6px 10px;border-left:4px solid '.$color.';margin:6px 0;background:#f8f9fa;'
        .'font-family:ui-monospace,monospace;font-size:13px">'.htmlspecialchars($msg, ENT_QUOTES, 'UTF-8').'</div>';
}

echo '<h2>PIN247 — Probe MySQL (mở rộng)</h2>';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['db_host'], $config['db_port']),
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    out('✔ Kết nối MySQL OK', 'ok');
} catch (Throwable $e) {
    out('✗ '.$e->getMessage(), 'error');
    exit;
}

// --- 1. information_schema.schemata ---
try {
    $rows = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA")->fetchAll(PDO::FETCH_COLUMN);
    out('information_schema.SCHEMATA: '.(implode(', ', $rows) ?: '(rỗng)'), 'ok');
} catch (Throwable $e) {
    out('information_schema.SCHEMATA bị từ chối: '.$e->getMessage(), 'warn');
}

// --- 2. Thông tin user/grant ---
foreach ([
    'SELECT CURRENT_USER() AS u',
    'SELECT DATABASE() AS d',
    'SELECT USER() AS u',
    'SHOW GRANTS FOR CURRENT_USER',
    'SELECT VERSION() AS v',
] as $sql) {
    try {
        $res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        out($sql.' => '.json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'info');
    } catch (Throwable $e) {
        out($sql.' => LỖI: '.$e->getMessage(), 'warn');
    }
}

// --- 3. Dò tên database ---
$prefix = $config['db_user'];
$suffixes = [
    '', '_pin247', '_pin247_shop', '_pin247_demo', '_pin247_v2', '_tunganh', '_tunganhahihi',
    '_shop', '_website', '_web', '_db', '_database', '_data', '_mysql', '_sql', '_test',
    '_laravel', '_app', '_site', '_demo', '_wordpress', '_wp', '_api',
];

out('Đang dò tên database theo prefix `'.$prefix.'` ...', 'info');

$found = [];

foreach ($suffixes as $suffix) {
    $name = $prefix.$suffix;

    try {
        $pdo->exec('USE `'.str_replace('`', '', $name).'`');
        $count = $pdo->query('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        out('✔ TÌM THẤY: `'.$name.'` — số bảng: '.$count, 'ok');
        $found[] = $name;
    } catch (Throwable $e) {
        $msg = $e->getMessage();

        // 1049 = Unknown database => chắc chắn không tồn tại.
        if (str_contains($msg, '1049')) {
            continue;
        }

        out('? `'.$name.'` => '.$msg, 'warn');
    }
}

if ($found === []) {
    out('Không dò được tên database nào. Xem Control Panel InfinityFree → MySQL Databases.', 'warn');
}

// Tự xoá.
@unlink(__FILE__);

echo '<p style="font-family:system-ui">Xong — đã xoá script.</p>';
