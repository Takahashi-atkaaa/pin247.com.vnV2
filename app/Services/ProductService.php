<?php

namespace App\Services;

use App\Enums\ProductSource;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quản lý sản phẩm & SKU.
 */
class ProductService
{
    /**
     * Tạo sản phẩm kèm biến thể & SKU.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Product::generateSlug($data['name']),
                'sku' => $data['sku'] ?? null,
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'] ?? null,
                'specifications' => $data['specifications'] ?? null,
                'price' => $data['price'] ?? 0,
                'sale_price' => $data['sale_price'] ?? null,
                'cost_price' => $data['cost_price'] ?? null,
                'image' => $data['image'] ?? null,
                'source' => $data['source'] ?? ProductSource::Manual,
                'has_variants' => ! empty($data['variants']),
                'is_active' => $data['is_active'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
                'is_new' => $data['is_new'] ?? false,
            ]);

            $this->syncVariants($product, (array) ($data['variants'] ?? []));

            return $product->fresh(['variants', 'skus', 'images', 'category']);
        });
    }

    /**
     * Cập nhật sản phẩm.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            if (isset($data['name'])) {
                $data['slug'] = $data['slug'] ?? Product::generateSlug($data['name'], $product->id);
            }

            $product->fill(array_intersect_key($data, array_flip([
                'category_id', 'name', 'slug', 'sku', 'brand', 'description',
                'specifications', 'price', 'sale_price', 'cost_price', 'image',
                'is_active', 'is_featured', 'is_new',
            ])));

            $product->save();

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, (array) $data['variants']);
                $product->forceFill(['has_variants' => ! empty($data['variants'])])->save();
            }

            return $product->fresh(['variants', 'skus', 'images', 'category']);
        });
    }

    /**
     * Đồng bộ biến thể & SKU của sản phẩm.
     *
     * SKU đã được xác nhận KHÔNG bị tự thay đổi: nếu variant cùng SKU tồn tại
     * thì cập nhật; nếu SKU mới thì tạo.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    protected function syncVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            $sku = trim((string) ($variantData['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            // Tìm variant theo SKU (không theo id) để giữ nguyên SKU đã xác nhận.
            $variant = ProductVariant::where('sku', $sku)->first();

            if ($variant === null) {
                $variant = new ProductVariant(['sku' => $sku]);
            }

            $variant->fill([
                'product_id' => $product->id,
                'name' => $variantData['name'] ?? $sku,
                'option_1_name' => $variantData['option_1_name'] ?? null,
                'option_1_value' => $variantData['option_1_value'] ?? null,
                'option_2_name' => $variantData['option_2_name'] ?? null,
                'option_2_value' => $variantData['option_2_value'] ?? null,
                'price' => $variantData['price'] ?? $product->price,
                'sale_price' => $variantData['sale_price'] ?? null,
                'cost_price' => $variantData['cost_price'] ?? $product->cost_price,
                'image' => $variantData['image'] ?? null,
                'sort_order' => $variantData['sort_order'] ?? 0,
                'is_active' => $variantData['is_active'] ?? true,
            ]);

            $variant->save();

            // Đảm bảo có bản ghi SKU (tồn kho) cho biến thể.
            $skuRecord = Sku::where('sku', $sku)->first();

            if ($skuRecord === null) {
                Sku::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'sku' => $sku,
                    'name' => $variant->name,
                    'source' => $product->source,
                    'available_stock' => 0,
                    'safety_stock' => (int) config('bigseller.safety_stock', 0),
                    'price' => $variant->effectivePrice(),
                    'cost_price' => $variant->cost_price,
                ]);
            } else {
                $skuRecord->forceFill([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'name' => $variant->name,
                    'price' => $variant->effectivePrice(),
                ])->save();
            }
        }
    }

    /**
     * Xóa sản phẩm (soft delete).
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->variants()->each(fn ($v) => $v->delete());
            $product->delete();
        });
    }

    /**
     * Upload ảnh sản phẩm.
     */
    public function uploadImage(Product $product, \Illuminate\Http\UploadedFile $file, ?string $altText = null): string
    {
        $path = $file->store('products/'.$product->id, 'public');

        $product->images()->create([
            'path' => $path,
            'alt_text' => $altText ?? $product->name,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
            'is_primary' => ! $product->images()->exists(),
        ]);

        if (blank($product->image)) {
            $product->forceFill(['image' => $path])->save();
        }

        return $path;
    }

    /**
     * Xóa ảnh.
     */
    public function deleteImage(Product $product, int $imageId): void
    {
        $image = $product->images()->findOrFail($imageId);

        Storage::disk('public')->delete($image->path);

        $image->delete();
    }

    /**
     * Tạo danh mục.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCategory(array $data): Category
    {
        return Category::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ]);
    }

    /**
     * Cập nhật danh mục.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $category->fill(array_intersect_key($data, array_flip([
            'name', 'slug', 'description', 'icon', 'parent_id', 'sort_order',
            'image', 'is_active', 'meta_title', 'meta_description',
        ])));

        $category->save();

        return $category;
    }

    /**
     * Xóa danh mục (chỉ khi không còn sản phẩm).
     */
    public function deleteCategory(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new BusinessRuleException(
                'Không thể xóa danh mục "'.$category->name.'" vì còn sản phẩm thuộc danh mục.'
            );
        }

        $category->delete();
    }
}
