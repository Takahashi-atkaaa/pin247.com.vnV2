<?php

/**
 * PIN247 — probe tên database khả dụng trên InfinityFree.
 * Dùng một lần, tự xoá sau khi chạy.
 */

$host = getenv('DB_HOST') ?: 'sql210.infinityfree.com';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'if0_41917067';
$pass = getenv('DB_PASSWORD') ?: '';

foreach (file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v, " \t\n\r\0\x0B\"'");
    $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_USERNAME' => 'user', 'DB_PASSWORD' => 'pass'];
    if (isset($map[$k]) && ${$map[$k]} === '') {
        ${$map[$k]} = $v;
    }
}

$candidates = [
    'if0_41917067_pin247',
    'if0_41917067_pin247_shop',
    'if0_41917067_shop',
    'if0_41917067_duanma',
    'if0_41917067_dm',
];

echo "<pre style=\"font-family:ui-monospace,monospace;font-size:13px\">";
echo "host={$host} user={$user}\n\n";

foreach ($candidates as $db) {
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $tables = $pdo->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$pdo->quote($db)
        )->fetchColumn();

        $names = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $names[] = $t;
        }

        echo "✔ KẾT NỐI THÀNH CÔNG: {$db} — {$tables} bảng\n";
        echo '   ' . implode(', ', array_slice($names, 0, 40)) . "\n\n";
    } catch (Throwable $e) {
        echo "✗ {$db} → " . $e->getMessage() . "\n";
    }
}

echo '</pre>';

@unlink(__FILE__);
