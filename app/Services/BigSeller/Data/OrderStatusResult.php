<?php

namespace App\Services\BigSeller\Data;

/**
 * Kết quả tra cứu / hủy đơn hàng BigSeller.
 */
class OrderStatusResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $bigSellerOrderCode = null,
        public readonly ?string $rawStatus = null,
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
        $data = $response['data'] ?? [];

        return new self(
            success: (bool) ($response['success'] ?? false),
            bigSellerOrderCode: isset($data['order_code']) ? (string) $data['order_code'] : null,
            rawStatus: isset($data['status']) ? (string) $data['status'] : null,
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
