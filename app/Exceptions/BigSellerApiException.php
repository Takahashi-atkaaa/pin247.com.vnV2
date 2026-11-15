<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi khi gọi API BigSeller thất bại (HTTP lỗi, timeout, mã lỗi nghiệp vụ).
 */
class BigSellerApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $statusCode = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function isTimeout(): bool
    {
        return $this->statusCode === null
            && (str_contains($this->getMessage(), 'timed out')
                || str_contains($this->getMessage(), 'timeout'));
    }

    /**
     * Lỗi này có an toàn để retry hay không.
     *
     * Timeout / lỗi mạng / 5xx: retry được (đã có idempotency key để chống trùng).
     * 4xx nghiệp vụ (SKU không tồn tại, dữ liệu sai): KHÔNG retry vô ích.
     */
    public function isRetryable(): bool
    {
        if ($this->isTimeout()) {
            return true;
        }

        if ($this->statusCode === null) {
            return true;
        }

        return $this->statusCode >= 500;
    }
}
