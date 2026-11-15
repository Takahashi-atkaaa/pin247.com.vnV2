<?php

namespace App\Services\BigSeller\Data;

/**
 * Kết quả tạo đơn hàng thủ công trên BigSeller.
 */
class ManualOrderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $bigSellerOrderCode = null,
        public readonly ?string $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        // Đường dẫn field được đọc từ tài liệu chính thức của BigSeller.
        // Nếu tài liệu dùng tên khác, chỉ cần sửa tại đây — một điểm duy nhất.
        $data = $response['data'] ?? [];
        $code = $data['order_code'] ?? $data['code'] ?? $data['order_no'] ?? null;

        return new self(
            success: (bool) ($response['success'] ?? false),
            bigSellerOrderCode: $code !== null ? (string) $code : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            errorCode: isset($response['error_code']) ? (string) $response['error_code'] : null,
            errorMessage: isset($response['error_msg']) ? (string) $response['error_msg'] : null,
            raw: $response,
        );
    }

    public static function failure(string $message, ?string $errorCode = null, array $raw = []): self
    {
        return new self(
            success: false,
            errorCode: $errorCode,
            errorMessage: $message,
            raw: $raw,
        );
    }
}
