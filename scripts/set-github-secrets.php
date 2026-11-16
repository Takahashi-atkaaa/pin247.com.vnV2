#!/usr/bin/env php
<?php

/**
 * =============================================================================
 *  PIN247 — đặt GitHub Actions Secrets qua REST API
 *
 *  CÁCH DÙNG:
 *      php scripts/set-github-secrets.php
 *
 *  YÊU CẦU:
 *      - PAT (Personal Access Token) scope `repo`.
 *        Lấy từ keychain:  security find-internet-password -s github.com -w
 *        hoặc export GITHUB_TOKEN=<pat>
 *      - deploy/ftp-credentials.txt  (FTP host/user/pass/dir)
 *      - deploy/.env.production.local (APP_KEY, APP_URL, DB_*)
 *      - deploy/deploy-import.token.txt (DEPLOY_TOKEN) — sinh nếu chưa có
 *
 *  KHÔNG BAO GIỜ in giá trị secret ra stdout.
 * =============================================================================
 */

const REPO = 'Takahashi-atkaaa/pin247.com.vnV2';
const API_BASE = 'https://api.github.com/repos/'.REPO.'/actions/secrets';

$root = dirname(__DIR__);
$deployDir = $root.'/deploy';

$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$nc = "\033[0m";

/**
 * Đọc file key=value.
 */
function readKv(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $data = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $data[trim($key)] = trim($value);
    }

    return $data;
}

/**
 * Đọc file .env.
 */
function readEnv(string $path): array
{
    return readKv($path);
}

/**
 * Gọi GitHub REST API.
 *
 * @return array{status:int, body:string}
 */
function api(string $method, string $url, string $token, ?array $payload = null): array
{
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer '.$token,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: pin247-deploy-setup',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $body = curl_exec($ch);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return ['status' => 0, 'body' => 'CURL error: '.$error];
    }

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}

// ---------------------------------------------------------------------------
// 1. Lấy PAT
// ---------------------------------------------------------------------------
$token = getenv('GITHUB_TOKEN') ?: '';

if ($token === '') {
    $cmd = 'security find-internet-password -s github.com -w 2>/dev/null';
    $token = (string) shell_exec($cmd);
    $token = trim($token);
}

if ($token === '') {
    fwrite(STDERR, "Không tìm thấy PAT.\n"
        ."  → export GITHUB_TOKEN=<pat>  (scope: repo)\n"
        ."  → hoặc lưu trong keychain: security add-internet-password -s github.com -a <user> -w <pat>\n");

    exit(1);
}

// ---------------------------------------------------------------------------
// 2. Lấy public key của repo
// ---------------------------------------------------------------------------
echo "\n============================================================\n";
echo " PIN247 — đặt GitHub Actions Secrets\n";
echo "============================================================\n";

$response = api('GET', API_BASE.'/public-key', $token);

if ($response['status'] !== 200) {
    fwrite(STDERR, "Không lấy được public key: HTTP {$response['status']}\n{$response['body']}\n");

    exit(1);
}

$keyData = json_decode($response['body'], true);
$keyId = $keyData['key_id'];
$publicKey = base64_decode($keyData['key']);

echo "  Repo   : ".REPO."\n";
echo "  Key ID : {$keyId}\n\n";

// ---------------------------------------------------------------------------
// 3. Gom giá trị secret
// ---------------------------------------------------------------------------
$ftp = readKv($deployDir.'/ftp-credentials.txt');
$env = readEnv($deployDir.'/.env.production.local');

// DEPLOY_TOKEN — dùng file có sẵn, nếu chưa có thì sinh mới.
$tokenFile = $deployDir.'/deploy-import.token.txt';
$deployToken = '';

if (is_file($tokenFile)) {
    $deployToken = trim((string) file_get_contents($tokenFile));
}

if ($deployToken === '') {
    $deployToken = bin2hex(random_bytes(16));
    file_put_contents($tokenFile, $deployToken);
    chmod($tokenFile, 0600);
    echo "  ℹ️  Đã sinh deploy token mới: deploy/deploy-import.token.txt\n\n";
}

$secrets = [
    'FTP_HOST'     => $ftp['ftp_host'] ?? 'ftpupload.net',
    'FTP_USER'     => $ftp['ftp_user'] ?? '',
    'FTP_PASS'     => $ftp['ftp_pass'] ?? '',
    'FTP_DIR'      => $ftp['remote_dir'] ?? '/htdocs',
    'DEPLOY_TOKEN' => $deployToken,
    'APP_KEY'      => $env['APP_KEY'] ?? '',
    'APP_URL'      => $env['APP_URL'] ?? '',
    'DB_HOST'      => $env['DB_HOST'] ?? '',
    'DB_DATABASE'  => $env['DB_DATABASE'] ?? '',
    'DB_USERNAME'  => $env['DB_USERNAME'] ?? '',
    'DB_PASSWORD'  => $env['DB_PASSWORD'] ?? '',
];

// ---------------------------------------------------------------------------
// 4. Set từng secret
// ---------------------------------------------------------------------------
if (! function_exists('sodium_crypto_box_seal')) {
    fwrite(STDERR, "Thiếu PHP extension sodium.\n");

    exit(1);
}

echo "  Đặt secrets:\n";

$ok = true;
$missing = [];

foreach ($secrets as $name => $value) {
    if ($value === '') {
        echo "  {$red}✗{$nc} {$name} — BỎ QUA (thiếu giá trị)\n";
        $missing[] = $name;
        $ok = false;

        continue;
    }

    // Bọc secret bằng libsodium sealed box (chuẩn GitHub Actions Secrets API).
    $sealed = sodium_crypto_box_seal($value, $publicKey);

    $response = api('PUT', API_BASE.'/'.$name, $token, [
        'encrypted_value' => base64_encode($sealed),
        'key_id'           => $keyId,
    ]);

    if (in_array($response['status'], [201, 204], true)) {
        echo "  {$green}✓{$nc} {$name}\n";
    } else {
        echo "  {$red}✗{$nc} {$name} — HTTP {$response['status']}: ".substr($response['body'], 0, 200)."\n";
        $ok = false;
    }
}

echo "\n";

if ($ok) {
    echo " {$green}✔ Đã đặt đủ GitHub Actions Secrets{$nc}\n\n";
    echo "  Chạy workflow:\n";
    echo "    https://github.com/".REPO."/actions/workflows/deploy-infinityfree.yml\n\n";
    echo "  Hoặc push commit mới để tự kích hoạt.\n";

    exit(0);
}

if ($missing !== []) {
    echo " {$yellow}△ Thiếu giá trị cho: ".implode(', ', $missing)."{$nc}\n";
    echo "  → Tạo deploy/ftp-credentials.txt và deploy/.env.production.local\n";
}

exit(1);
