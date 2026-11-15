<?php

namespace App\Services;

use App\Exceptions\BigSellerConfigurationException;
use App\Models\BigSellerSyncLog;
use App\Services\BigSeller\Contracts\BigSellerClient;
use App\Services\BigSeller\BigSellerAuthService;

/**
 * Facade cho toàn bộ tích hợp BigSeller.
 *
 * Đây là service mà các controller/job khác nên gọi.
 */
class BigSellerService
{
    public function __construct(
        protected BigSellerClient $client,
        protected BigSellerAuthService $auth,
    ) {
    }

    public function client(): BigSellerClient
    {
        return $this->client;
    }

    public function auth(): BigSellerAuthService
    {
        return $this->auth;
    }

    /**
     * Đang dùng mock hay API thật.
     */
    public function isMockMode(): bool
    {
        return config('bigseller.mode', 'mock') !== 'live';
    }

    /**
     * Kiểm tra kết nối & quyền API.
     *
     * @return array{
     *     success: bool, message: string, details: array,
     *     mode: string, is_mock: bool, capabilities: array<int, string>,
     *     configured_endpoints: array<int, string>
     * }
     */
    public function status(): array
    {
        $capabilities = $this->auth->grantedCapabilities();

        $configuredEndpoints = [];

        foreach (config('bigseller.endpoints', []) as $key => $path) {
            if (filled($path)) {
                $configuredEndpoints[] = $key;
            }
        }

        if ($this->isMockMode()) {
            return [
                'success' => true,
                'message' => 'Đang chạy chế độ MOCK — dữ liệu không đến từ BigSeller thật.',
                'details' => [],
                'mode' => 'mock',
                'is_mock' => true,
                'capabilities' => $capabilities,
                'configured_endpoints' => $configuredEndpoints,
            ];
        }

        if (blank(config('bigseller.base_url'))) {
            return [
                'success' => false,
                'message' => 'Chưa cấu hình BIGSELLER_BASE_URL. Cần lấy từ tài liệu API chính thức của BigSeller.',
                'details' => [],
                'mode' => 'live',
                'is_mock' => false,
                'capabilities' => $capabilities,
                'configured_endpoints' => $configuredEndpoints,
            ];
        }

        $result = $this->client->checkConnection();

        return [
            ...$result,
            'mode' => 'live',
            'is_mock' => false,
            'capabilities' => $capabilities,
            'configured_endpoints' => $configuredEndpoints,
        ];
    }

    /**
     * Ném lỗi nếu tài khoản chưa được cấp quyền capability.
     */
    public function requireCapability(string $capability): void
    {
        $this->auth->requireCapability($capability);
    }

    /**
     * Danh sách capability mà tài khoản được cấp.
     *
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return $this->auth->grantedCapabilities();
    }

    /**
     * Lấy log đồng bộ gần nhất.
     */
    public function recentLogs(int $limit = 50, ?string $operation = null)
    {
        return BigSellerSyncLog::query()
            ->when($operation, fn ($q) => $q->where('operation', $operation))
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
