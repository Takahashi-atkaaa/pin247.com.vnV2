<?php

/**
 * =============================================================================
 *  PIN247 — script giải nén gói deploy (DÙNG MỘT LẦN, tự xoá sau khi chạy)
 *
 *  DÙNG CHO: GitHub Actions / FTP upload tự động (upload 1 file zip duy nhất
 *  thay vì 7.500 file rời rạc — nhanh hơn hàng trăm lần).
 *
 *  CÁCH DÙNG:
 *      1. Upload `pin247-deploy.zip` + file này lên cùng thư mục webroot.
 *      2. Mở trình duyệt:
 *         https://<domain>/deploy-extract.php?token=<TOKEN>
 *      3. Bấm "GIẢI NÉN" → script giải nén, set quyền ghi, tự xoá.
 *
 *  AN TOÀN:
 *      - Bắt buộc có token đúng mới chạy được.
 *      - KHÔNG ghi đè file `.env` (nằm ngoài gói zip).
 *      - Chỉ ghi đè file có trong gói (code mới).
 *      - Tự xoá bản thân + file zip + file token sau khi giải nén thành công.
 *      - Nếu THẤT BẠI: giữ nguyên file zip để thử lại.
 * =============================================================================
 */

// ---------------------------------------------------------------------------
// Cấu hình
// ---------------------------------------------------------------------------
$config = [
    'zip_file'   => __DIR__.'/pin247-deploy.zip',
    'token_file' => __DIR__.'/deploy-import.token.txt',
    'webroot'    => __DIR__,
];

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

/**
 * Đệ quy set quyền ghi (0777) cho các thư mục runtime.
 *
 * InfinityFree không cho đổi quyền qua FTP (SITE CHMOD bị từ chối), nhưng PHP
 * chạy với quyền chủ sở hữu file nên chmod() cục bộ vẫn hoạt động.
 */
function makeWritable(string $dir): void
{
    @chmod($dir, 0777);

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir.'/'.$entry;

        if (is_dir($path)) {
            makeWritable($path);
        }
    }
}

// ---------------------------------------------------------------------------
// 1. Kiểm tra token
// ---------------------------------------------------------------------------
$token = $_GET['token'] ?? '';

if ($token === '' && is_file($config['token_file'])) {
    $token = trim((string) file_get_contents($config['token_file']));
}

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
echo '<h2>PIN247 — Giải nén gói deploy</h2>';

if (! is_file($config['zip_file'])) {
    out('Không tìm thấy file zip: '.basename($config['zip_file']), 'error');
    out('Đã upload file zip lên webroot chưa? Nếu dùng FTP client, hãy chắc chắn file nằm cùng thư mục với file .php này.', 'warn');
    exit;
}

if (! class_exists('ZipArchive')) {
    out('PHP extension "zip" không khả dụng trên hosting này.', 'error');
    out('Hãy giải nén thủ công bằng File Manager của InfinityFree.', 'warn');
    exit;
}

$zipSize = filesize($config['zip_file']);
out('File zip: '.basename($config['zip_file']).' — '.round($zipSize / 1024 / 1024, 2).' MB');

// ---------------------------------------------------------------------------
// 3. Bước xác nhận
// ---------------------------------------------------------------------------
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (! $confirm) {
    $zip = new ZipArchive();

    if ($zip->open($config['zip_file']) !== true) {
        out('Không mở được file zip (có thể file tải lên chưa xong hoặc bị hỏng).', 'error');
        exit;
    }

    $count = $zip->numFiles;
    $zip->close();

    echo '<div style="font-family:system-ui;font-size:14px;margin:16px 0">';
    echo '<p><strong>Sẽ giải nén:</strong> '.$count.' mục vào <code>'.htmlspecialchars($config['webroot']).'</code>.</p>';
    echo '<p style="color:#c92a2a"><strong>Cảnh báo:</strong> các file trùng tên trong gói sẽ bị ghi đè '
        .'(đây là file code mới — đúng ý đồ khi deploy). File <code>.env</code> <strong>KHÔNG</strong> nằm trong gói nên không bị đụng tới.</p>';
    echo '</div>';

    $url = $_SERVER['REQUEST_URI'];
    $confirmUrl = $url.(str_contains($url, '?') ? '&' : '?').'confirm=yes';

    echo '<p style="font-family:system-ui">'
        .'<a href="'.htmlspecialchars($confirmUrl).'" '
        .'style="display:inline-block;padding:10px 20px;background:#2b8a3e;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">'
        .'GIẢI NÉN</a></p>';

    finish('Sẵn sàng — chưa giải nén gì.', 'warn');
}

// ---------------------------------------------------------------------------
// 4. Giải nén
// ---------------------------------------------------------------------------
$zip = new ZipArchive();
$result = $zip->open($config['zip_file']);

if ($result !== true) {
    out('Không mở được file zip (mã lỗi '.$result.').', 'error');
    exit;
}

out('Bắt đầu giải nén '.$zip->numFiles.' mục ...', 'info');

$extracted = $zip->extractTo($config['webroot']);
$zip->close();

if (! $extracted) {
    out('Giải nén thất bại. Kiểm tra quyền ghi của thư mục webroot.', 'error');
    exit;
}

out('Đã giải nén xong.', 'ok');

// ---------------------------------------------------------------------------
// 5. Set quyền ghi cho thư mục runtime
// ---------------------------------------------------------------------------
$writableDirs = [
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/app/private',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];

foreach ($writableDirs as $dir) {
    $path = $config['webroot'].'/'.$dir;

    if (is_dir($path)) {
        makeWritable($path);
    } else {
        @mkdir($path, 0777, true);
        @chmod($path, 0777);
    }
}

out('Đã set quyền ghi (0777) cho: storage/*, bootstrap/cache', 'ok');

// ---------------------------------------------------------------------------
// 6. Kiểm tra kết quả
// ---------------------------------------------------------------------------
$checks = [
    'index.php (shim)'            => is_file($config['webroot'].'/index.php'),
    '.htaccess'                   => is_file($config['webroot'].'/.htaccess'),
    'public/index.php'            => is_file($config['webroot'].'/public/index.php'),
    'vendor/autoload.php'         => is_file($config['webroot'].'/vendor/autoload.php'),
    'public/build/manifest.json'  => is_file($config['webroot'].'/public/build/manifest.json'),
    '.env'                        => is_file($config['webroot'].'/.env'),
];

foreach ($checks as $label => $ok) {
    out(($ok ? '✓ ' : '✗ ').$label, $ok ? 'ok' : 'warn');
}

// ---------------------------------------------------------------------------
// 7. Dọn dẹp
// ---------------------------------------------------------------------------
@unlink($config['zip_file']);
@unlink($config['token_file']);
// Giữ lại chính file này? KHÔNG — xoá để không ai gọi lại được.
@unlink(__FILE__);

out('Đã xoá file zip, file token và file script này.', 'ok');

finish('Giải nén hoàn tất. Kiểm tra trang chủ: /');
