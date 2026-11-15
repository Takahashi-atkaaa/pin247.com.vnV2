<?php

namespace App\Services\BigSeller;

use App\Exceptions\BigSellerConfigurationException;
use App\Models\BigSellerConnection;
use Illuminate\Support\Facades\Crypt;

/**
 * Quản lý xác thực với BigSeller Open API.
 *
 * QUY TẮC:
 * - Không tự bịa cơ chế chữ ký. Cơ chế đọc từ cấu hình/tài liệu chính thức.
 * - Token/app_key được lưu mã hoá (encrypted cast) và KHÔNG bao giờ hiển thị
 *   ra giao diện hoặc API cho Admin.
 */
class BigSellerAuthService
{
    /**
     * Lấy connection đang hoạt động (đầu tiên theo thứ tự).
     */
    public function activeConnection(): ?BigSellerConnection
    {
        return BigSellerConnection::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Chuỗi xác thực sẽ gửi kèm request.
     *
     * Cơ chế chữ ký cụ thể phải được xác nhận từ tài liệu API chính thức.
     * Hiện tại đọc từ connection: app_id + access_token.
     *
     * @return array<string, string>
     */
    public function authHeaders(?BigSellerConnection $connection = null): array
    {
        $connection ??= $this->activeConnection();

        if ($connection === null) {
            throw BigSellerConfigurationException::missingConfig('kết nối BigSeller');
        }

        if (blank($connection->access_token)) {
            throw BigSellerConfigurationException::missingConfig('access_token');
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$connection->access_token,
        ];

        if (filled($connection->app_id)) {
            $headers['X-App-Id'] = $connection->app_id;
        }

        if (filled($connection->app_key)) {
            $headers['X-App-Key'] = $connection->app_key;
        }

        return $headers;
    }

    /**
     * Token đã hết hạn chưa.
     */
    public function isTokenExpired(?BigSellerConnection $connection = null): bool
    {
        $connection ??= $this->activeConnection();

        return $connection?->isTokenExpired() ?? true;
    }

    /**
     * Kiểm tra quyền API của tài khoản.
     *
     * @return array<int, string>
     */
    public function grantedCapabilities(?BigSellerConnection $connection = null): array
    {
        $connection ??= $this->activeConnection();

        if ($connection === null) {
            return [];
        }

        return $connection->capabilities ?? [];
    }

    /**
     * Tài khoản có được cấp quyền capability cụ thể hay không.
     */
    public function hasCapability(string $capability, ?BigSellerConnection $connection = null): bool
    {
        return in_array($capability, $this->grantedCapabilities($connection), true);
    }

    /**
     * Yêu cầu capability, nếu chưa có thì ném exception rõ ràng.
     */
    public function requireCapability(string $capability, ?BigSellerConnection $connection = null): void
    {
        if (! $this->hasCapability($capability, $connection)) {
            throw BigSellerConfigurationException::missingCapability($capability);
        }
    }

    /**
     * Ghi lại trạng thái kiểm tra kết nối lên DB.
     */
    public function recordCheck(?BigSellerConnection $connection, bool $success, ?string $error = null): void
    {
        if ($connection === null) {
            return;
        }

        $connection->forceFill([
            'last_checked_at' => now(),
            'last_success_at' => $success ? now() : $connection->last_success_at,
            'last_error' => $success ? null : mb_substr((string) $error, 0, 500),
        ])->save();
    }
}
