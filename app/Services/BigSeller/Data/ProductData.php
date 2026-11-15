<?php

namespace App\Services\BigSeller\Data;

/**
 * Dữ liệu sản phẩm/SKU từ BigSeller.
 *
 * CHỈ dùng khi tài khoản được cấp quyền API sản phẩm.
 */
class ProductData
{
    public function __construct(
        public readonly string $sku,
        public readonly ?string $name = null,
        public readonly ?string $image = null,
        public readonly ?float $price = null,
        public readonly ?int $bigSellerProductId = null,
        public readonly ?int $bigSellerVariantId = null,
        public readonly array $attributes = [],
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            sku: (string) ($row['sku'] ?? $row['sku_code'] ?? ''),
            name: $row['name'] ?? $row['title'] ?? null,
            image: $row['image'] ?? $row['image_url'] ?? null,
            price: isset($row['price']) ? (float) $row['price'] : null,
            bigSellerProductId: isset($row['product_id']) ? (int) $row['product_id'] : null,
            bigSellerVariantId: isset($row['variant_id']) ? (int) $row['variant_id'] : null,
            attributes: (array) ($row['attributes'] ?? $row['variants'] ?? []),
            raw: $row,
        );
    }
}
