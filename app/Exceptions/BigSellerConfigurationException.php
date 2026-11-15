<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném ra khi BigSeller client chưa được cấu hình đủ (thiếu base_url, endpoint,
 * token, hoặc thiếu quyền capability).
 *
 * Đây là lỗi CẤU HÌNH — không phải lỗi nghiệp vụ. Việc bắt lỗi riêng giúp
 * Admin UI hiển thị đúng nguyên nhân thay vì báo "lỗi chung".
 */
class BigSellerConfigurationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $capability = null,
    ) {
        parent::__construct($message);
    }

    public static function missingConfig(string $what): self
    {
        return new self(
            "Chưa cấu hình BigSeller: {$what}. Vui lòng liên hệ quản trị viên cấu hình theo tài liệu API chính thức của BigSeller."
        );
    }

    public static function missingCapability(string $capability): self
    {
        return new self(
            "Tài khoản BigSeller chưa được cấp quyền API '{$capability}'. Vui lòng liên hệ BigSeller để đăng ký quyền truy cập.",
            $capability
        );
    }
}
