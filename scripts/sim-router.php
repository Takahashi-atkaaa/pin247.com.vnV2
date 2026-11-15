<?php

/**
 * Router cho PHP built-in server — MÔ PHỎNG hành vi .htaccess của InfinityFree.
 *
 * Mục tiêu: kiểm tra gói deploy (deploy/pin247-deploy.zip) mà KHÔNG cần
 * cài Apache, nhưng vẫn cho kết quả giống hosting thật.
 *
 *   php -S 127.0.0.1:8099 -t /tmp/pin-sim/htdocs scripts/sim-router.php
 *
 * Mô phỏng theo đúng thứ tự phase của Apache 2.4:
 *   1. access_control (FilesMatch trong .htaccess)  -> 403
 *   2. fixup / mod_rewrite (RewriteRule)             -> 404 / serve file
 *   3. front controller                               -> Laravel
 *
 * LƯU Ý: trong Apache, `<Files>` / `<FilesMatch>` (Require) được áp dụng ở
 * phase access_control, TRƯỚC phase fixup (mod_rewrite). Vì vậy file bị
 * chặn bởi FilesMatch trả 403, còn thư mục bị chặn bởi RewriteRule trả 404.
 */

$root = rtrim(getenv('PIN_SIM_ROOT') ?: (dirname(__DIR__).'/htdocs'), '/');
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$uri  = '/' . ltrim($uri, '/');

/**
 * Gửi file tĩnh với Content-Type đúng.
 */
function send_static(string $file): bool
{
    if (! is_file($file)) {
        return false;
    }

    static $types = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'mjs'  => 'application/javascript',
        'json' => 'application/json',
        'map'  => 'application/json',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'  => 'font/ttf',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'html' => 'text/html',
    ];

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: '.filesize($file));
    readfile($file);

    return true;
}

function deny(int $code, string $body): bool
{
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $body;

    return true;
}

// ---------------------------------------------------------------------------
// 1. access_control — FilesMatch (Require all denied) => 403
// ---------------------------------------------------------------------------
$basename = basename($uri);

// Dot-file (file ẩn): .env, .gitignore, .htaccess...
if (str_starts_with($basename, '.')) {
    return deny(403, 'Forbidden');
}

// File nhạy cảm theo danh sách trong .htaccess.
if (preg_match('#^(composer\.(json|lock)|package(-lock)?\.json|artisan|phpunit\.xml)$#', $basename)) {
    return deny(403, 'Forbidden');
}

if (preg_match('#\.(md|sql|log|key|zip)$#', $basename)) {
    return deny(403, 'Forbidden');
}

// public/.htaccess chặn mọi file PHP trong thư mục public/.
if (preg_match('#^/public/#', $uri) && preg_match('#\.(php|phtml|php[0-9])$#', $basename)) {
    return deny(403, 'Forbidden');
}

// ---------------------------------------------------------------------------
// 2. fixup — RewriteRule
// ---------------------------------------------------------------------------
// Chặn thư mục nhạy cảm.
if (preg_match('#^/(app|bootstrap|config|database|resources|routes|tests|vendor|node_modules)(/|$)#', $uri)) {
    return deny(404, 'Not Found');
}

// storage: framework / logs / app/private.
if (preg_match('#^/storage/(framework|logs|app/private)(/|$)#', $uri)) {
    return deny(404, 'Not Found');
}

// File export CSV tạm trong storage/app.
if (preg_match('#^/storage/app/[^/]*\.csv$#', $uri)) {
    return deny(404, 'Not Found');
}

// public/ (trừ public/build/) — khác với build/ đã được rewrite ở dưới.
if (preg_match('#^/public(/|$)#', $uri)) {
    return deny(404, 'Not Found');
}

// Rewrite: /build/... -> public/build/...
if (str_starts_with($uri, '/build/')) {
    $file = $root . '/public/build/' . substr($uri, 7);

    if (is_file($file)) {
        return send_static($file);
    }

    return deny(404, 'Not Found');
}

// Rewrite: /storage/... -> storage/app/public/...
if (str_starts_with($uri, '/storage/')) {
    $file = $root . '/storage/app/public/' . substr($uri, 9);

    if (is_file($file)) {
        return send_static($file);
    }

    return deny(404, 'Not Found');
}

// File thật nằm trực tiếp trong webroot (vd. /favicon.ico).
$file = $root . $uri;
if ($uri !== '/' && is_file($file) && ! preg_match('#\.(php|phtml)$#', $basename)) {
    return send_static($file);
}

// ---------------------------------------------------------------------------
// 3. Front controller -> public/index.php (qua shim index.php ở gốc)
// ---------------------------------------------------------------------------
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

require $root . '/index.php';

return true;
