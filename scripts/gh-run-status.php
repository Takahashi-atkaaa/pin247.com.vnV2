#!/usr/bin/env php
<?php

/**
 * =============================================================================
 *  PIN247 — theo dõi trạng thái GitHub Actions run + tải log
 *
 *  CÁCH DÙNG:
 *      php scripts/gh-run-status.php <run-id> [--logs]
 *      php scripts/gh-run-status.php --latest [--logs]
 * =============================================================================
 */

const REPO = 'Takahashi-atkaaa/pin247.com.vnV2';
const API = 'https://api.github.com/repos/'.REPO.'/actions';

$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$blue = "\033[0;34m";
$nc = "\033[0m";

/**
 * Lấy PAT: env trước, sau đó qua git credential helper (nhanh & không treo
 * như `security find-internet-password`).
 */
function getToken(): string
{
    $token = getenv('GITHUB_TOKEN') ?: '';

    if ($token !== '') {
        return trim($token);
    }

    $cmd = 'printf "protocol=https\nhost=github.com\n\n" | git credential-osxkeychain get 2>/dev/null';
    $output = (string) shell_exec($cmd);

    if (preg_match('/^password=(.+)$/m', $output, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function api(string $url, string $token): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer '.$token,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: pin247-deploy-setup',
        ],
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}

// ---------------------------------------------------------------------------
$args = array_slice($argv, 1);
$wantLogs = in_array('--logs', $args, true);
$args = array_values(array_filter($args, fn ($a) => ! str_starts_with($a, '--')));

$token = getToken();

if ($token === '') {
    fwrite(STDERR, "Không lấy được PAT.\n");

    exit(1);
}

// --latest: tìm run gần nhất.
if (($args[0] ?? '') === '--latest' || $args === []) {
    $response = api(API.'/runs?per_page=5', $token);
    $data = json_decode($response['body'], true);

    if ($response['status'] !== 200 || empty($data['workflow_runs'])) {
        fwrite(STDERR, "Không lấy được danh sách run (HTTP {$response['status']}): ".substr($response['body'], 0, 300)."\n");

        exit(1);
    }

    echo "Các run gần đây:\n";

    foreach ($data['workflow_runs'] as $run) {
        printf("  %-28s %-12s %-10s %s  %s\n",
            $run['name'], $run['status'], $run['conclusion'] ?? '-',
            substr($run['head_sha'], 0, 7), $run['html_url']);
    }

    $runId = $data['workflow_runs'][0]['id'];
    echo "\nDùng run: {$runId}\n";
} else {
    $runId = $args[0];
}

// ---------------------------------------------------------------------------
// Thông tin run
// ---------------------------------------------------------------------------
$response = api(API."/runs/{$runId}", $token);
$run = json_decode($response['body'], true);

if ($response['status'] !== 200) {
    fwrite(STDERR, "Không lấy được run (HTTP {$response['status']}): ".substr($response['body'], 0, 300)."\n");

    exit(1);
}

echo "\n";
echo "============================================================\n";
echo " Run #{$runId}\n";
echo "============================================================\n";
echo "  Workflow : {$run['name']}\n";
echo "  Commit   : ".substr($run['head_sha'], 0, 7)." — {$run['head_commit']['message']}\n";
echo "  Trạng thái: {$blue}{$run['status']}{$nc}";
echo ($run['conclusion'] ? " / {$run['conclusion']}" : "")."\n";
echo "  URL      : {$run['html_url']}\n\n";

// ---------------------------------------------------------------------------
// Jobs + steps
// ---------------------------------------------------------------------------
$response = api(API."/runs/{$runId}/jobs?per_page=50", $token);
$jobs = json_decode($response['body'], true);

foreach ($jobs['jobs'] ?? [] as $job) {
    $conclusion = $job['conclusion'] ?? 'running';
    $icon = match ($conclusion) {
        'success' => "{$green}✔{$nc}",
        'failure' => "{$red}✗{$nc}",
        'cancelled' => "{$yellow}⊘{$nc}",
        default   => "{$blue}●{$nc}",
    };

    echo "  {$icon} Job: {$job['name']}  ({$job['status']}/{$conclusion})\n";

    foreach ($job['steps'] ?? [] as $step) {
        $stepConclusion = $step['conclusion'] ?? $step['status'];
        $stepIcon = match ($stepConclusion) {
            'success' => "{$green}✔{$nc}",
            'failure' => "{$red}✗{$nc}",
            'cancelled' => "{$yellow}⊘{$nc}",
            'skipped' => "{$yellow}—{$nc}",
            default   => "{$blue}●{$nc}",
        };

        printf("      %s %s\n", $stepIcon, $step['name']);
    }
}

// ---------------------------------------------------------------------------
// Log
// ---------------------------------------------------------------------------
if ($wantLogs) {
    echo "\n";
    echo "============================================================\n";
    echo " LOGS\n";
    echo "============================================================\n";

    $ch = curl_init(API."/runs/{$runId}/logs");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer '.$token,
            'Accept: application/vnd.github+json',
            'User-Agent: pin247-deploy-setup',
        ],
    ]);

    $zip = curl_exec($ch);
    curl_close($ch);

    if (! is_string($zip) || strlen($zip) < 100) {
        echo "Không tải được log.\n";
    } else {
        $tmp = sys_get_temp_dir().'/gh-logs-'.$runId.'.zip';
        file_put_contents($tmp, $zip);

        @mkdir(sys_get_temp_dir().'/gh-logs-'.$runId, 0777, true);
        exec("unzip -o -q ".escapeshellarg($tmp)." -d ".escapeshellarg(sys_get_temp_dir().'/gh-logs-'.$runId));

        foreach (glob(sys_get_temp_dir().'/gh-logs-'.$runId.'/*') as $file) {
            if (! is_file($file)) {
                continue;
            }

            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $relevant = array_filter($lines, fn ($l) => preg_match(
                '/(❌|✗|Error|error:|FAILED|Fatal|Warning:|Giải nén|Import|Smoke|E2E|Kết quả|✅|❌|📦|⏭️|🌐|\bGET\b|\bPOST\b|curl:|not found|404|403|401|500)/',
                $l
            ));

            if ($relevant === []) {
                continue;
            }

            echo "\n--- ".basename($file)." ---\n";
            echo implode("\n", array_slice($relevant, -120))."\n";
        }

        @unlink($tmp);
    }
}

echo "\n";
