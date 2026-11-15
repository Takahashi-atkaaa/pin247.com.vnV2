<?php

namespace App\Services\BigSeller\Data;

/**
 * Kho hàng từ BigSeller.
 */
class WarehouseData
{
    public function __construct(
        public readonly string $code,
        public readonly ?string $name = null,
        public readonly ?string $id = null,
        public readonly ?string $address = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            code: (string) ($row['code'] ?? $row['warehouse_code'] ?? ''),
            name: $row['name'] ?? $row['warehouse_name'] ?? null,
            id: isset($row['id']) ? (string) $row['id'] : (isset($row['warehouse_id']) ? (string) $row['warehouse_id'] : null),
            address: $row['address'] ?? null,
            raw: $row,
        );
    }
}
