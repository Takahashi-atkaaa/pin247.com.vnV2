<?php

namespace App\Enums;

/**
 * Loại tài liệu CSV/Excel hỗ trợ cho khởi tạo danh mục + ánh xạ SKU
 * khi BigSeller chưa cấp quyền API sản phẩm.
 */
enum ImportType: string
{
    case Categories = 'CATEGORIES';
    case Products = 'PRODUCTS';
    case SkuMapping = 'SKU_MAPPING';
    case Inventory = 'INVENTORY';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Header CSV chuẩn cho từng loại import.
     *
     * @return array<int, string>
     */
    public function expectedHeaders(): array
    {
        return match ($this) {
            self::Categories => ['name', 'slug', 'description', 'parent_slug'],
            self::Products => [
                'sku', 'name', 'category_slug', 'brand', 'description',
                'price', 'sale_price', 'cost_price', 'image', 'specifications',
            ],
            self::SkuMapping => ['sku', 'bigseller_sku', 'bigseller_variant_id', 'bigseller_product_id'],
            self::Inventory => ['sku', 'warehouse_code', 'available', 'reserved', 'on_hand'],
        };
    }
}
