<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Chi tiết sản phẩm (`api/products/{slug}`) và các endpoint admin
        // cần đầy đủ quan hệ (biến thể, SKU, ảnh, thông số).
        //
        // LƯU Ý: không dựa vào `routeIs()` vì các route chưa được đặt tên;
        // việc so khớp theo đường dẫn giữ được hành vi nhất quán và không
        // phụ thuộc vào việc đặt tên route.
        $includeRelations = $request->boolean('include', false)
            || $request->is('api/products/*')
            || $request->is('api/admin/products/*');

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'description' => $this->description,
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'effective_price' => $this->effectivePrice(),
            'is_on_sale' => $this->isOnSale(),
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'image' => $this->image,
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'has_variants' => (bool) $this->has_variants,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'is_new' => (bool) $this->is_new,
            'sales_count' => (int) $this->sales_count,
            'total_available_stock' => $this->totalAvailableStock(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
        ];

        // Số biến thể + biến thể mặc định: dùng cho nút "Thêm vào giỏ" nhanh
        // ở trang danh sách sản phẩm (nút chỉ hiển thị khi sản phẩm chỉ có
        // đúng 1 biến thể, hoặc không có biến thể nào).
        $data['variants_count'] = $this->variantsCount();
        $data['default_variant'] = $this->defaultVariantData();

        if ($includeRelations) {
            $data['specifications'] = $this->specifications;
            $data['meta_title'] = $this->meta_title;
            $data['meta_description'] = $this->meta_description;

            $data['variants'] = $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'option_1_name' => $variant->option_1_name,
                'option_1_value' => $variant->option_1_value,
                'option_2_name' => $variant->option_2_name,
                'option_2_value' => $variant->option_2_value,
                'price' => (float) $variant->price,
                'sale_price' => $variant->sale_price !== null ? (float) $variant->sale_price : null,
                'effective_price' => $variant->effectivePrice(),
                'image' => $variant->image,
                'is_active' => (bool) $variant->is_active,
                'available_stock' => (int) ($variant->skus->first()?->sellableQuantity() ?? 0),
                'sku_id' => $variant->skus->first()?->id,
            ]));

            $data['images'] = $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'url' => asset('storage/'.$image->path),
                'alt_text' => $image->alt_text,
                'is_primary' => (bool) $image->is_primary,
            ]));
        } else {
            // Danh sách: chỉ cần tồn kho tổng, không load quan hệ nặng.
            $data['specifications'] = null;
        }

        return $data;
    }

    /**
     * Số biến thể đang hoạt động của sản phẩm.
     */
    private function variantsCount(): int
    {
        if (! $this->relationLoaded('variants')) {
            return 0;
        }

        return (int) $this->variants->where('is_active', true)->count();
    }

    /**
     * Biến thể mặc định dùng cho thao tác "thêm vào giỏ" nhanh.
     *
     * - Sản phẩm có biến thể: lấy biến thể đầu tiên còn hàng.
     * - Sản phẩm không có biến thể: dùng SKU gốc của sản phẩm.
     */
    private function defaultVariantData(): ?array
    {
        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            $variant = $this->variants->firstWhere('is_active', true) ?? $this->variants->first();

            $sku = $variant->relationLoaded('skus') ? $variant->skus->first() : null;

            return [
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => (float) $variant->effectivePrice(),
                'image' => $variant->image ?? $this->image,
                'available_stock' => (int) ($sku?->sellableQuantity() ?? 0),
            ];
        }

        // Sản phẩm đơn giản: SKU nằm trực tiếp trên bảng products/skus.
        if (blank($this->sku)) {
            return null;
        }

        $sku = $this->relationLoaded('skus') ? $this->skus->first() : null;

        return [
            'sku' => $this->sku,
            'name' => null,
            'price' => (float) $this->effectivePrice(),
            'image' => $this->image,
            'available_stock' => (int) ($sku?->sellableQuantity() ?? $this->totalAvailableStock()),
        ];
    }
}
