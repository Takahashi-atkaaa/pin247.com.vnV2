<?php

namespace App\Services\BigSeller\Data;

/**
 * Một dòng tồn kho do BigSeller trả về.
 *
 * Các trường `reserved`, `on_hand`, `locked`, `shipped` là NULL khi BigSeller
 * KHÔNG cung cấp trường tương ứng trong response. Không tự suy luận.
 */
class InventoryItem
{
    public function __construct(
        public readonly string $sku,
        public readonly int $available,
        public readonly ?int $reserved = null,
        public readonly ?int $onHand = null,
        public readonly ?int $locked = null,
        public readonly ?int $shipped = null,
        public readonly ?string $warehouseCode = null,
        public readonly ?string $warehouseId = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Tạo từ mảng response thô của BigSeller.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row, ?string $warehouseCode = null, ?string $warehouseId = null): self
    {
        return new self(
            sku: (string) ($row['sku'] ?? $row['sku_code'] ?? ''),
            available: (int) ($row['available'] ?? $row['available_quantity'] ?? $row['stock'] ?? 0),
            reserved: self::intOrNull($row['reserved'] ?? $row['reserved_quantity'] ?? null),
            onHand: self::intOrNull($row['on_hand'] ?? $row['on_hand_quantity'] ?? $row['total_quantity'] ?? null),
            locked: self::intOrNull($row['locked'] ?? $row['locked_quantity'] ?? null),
            shipped: self::intOrNull($row['shipped'] ?? $row['shipped_quantity'] ?? null),
            warehouseCode: $warehouseCode ?? (isset($row['warehouse_code']) ? (string) $row['warehouse_code'] : null),
            warehouseId: $warehouseId ?? (isset($row['warehouse_id']) ? (string) $row['warehouse_id'] : null),
            raw: $row,
        );
    }

    private static function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'available' => $this->available,
            'reserved' => $this->reserved,
            'on_hand' => $this->onHand,
            'locked' => $this->locked,
            'shipped' => $this->shipped,
            'warehouse_code' => $this->warehouseCode,
            'warehouse_id' => $this->warehouseId,
        ];
    }
}
