<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi vi phạm quy tắc nghiệp vụ (được trả về client dưới dạng 422).
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
