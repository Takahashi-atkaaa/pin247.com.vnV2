<?php

namespace App\Services;

use App\Enums\ProductSource;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductUnit;
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
    public function __construct(
        protected ProductAttributeService $attributeService,
        protected ProductUnitService $unitService,
        protected ProductVariantHistoryService $historyService,
    ) {
    }

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

            // GĐ1 — Thuộc tính động → tự sinh biến thể.
            if (array_key_exists('attributes', $data)) {
                $this->attributeService->syncAttributes($product, (array) $data['attributes']);

                $this->attributeService->regenerateVariants($product, (array) ($data['variants'] ?? []));
            } else {
                $this->syncVariants($product, (array) ($data['variants'] ?? []));
            }

            return $product->fresh(['variants.skus', 'variants.units', 'skus', 'images', 'category', 'attributes.values']);
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

            if (array_key_exists('attributes', $data)) {
                // GĐ1 — Thuộc tính động → tự sinh biến thể.
                $this->attributeService->syncAttributes($product, (array) $data['attributes']);

                $this->attributeService->regenerateVariants($product, (array) ($data['variants'] ?? []));
            } elseif (array_key_exists('variants', $data)) {
                $this->syncVariants($product, (array) $data['variants']);
                $product->forceFill(['has_variants' => ! empty($data['variants'])])->save();
            }

            // GĐ2 — Đơn vị tính của từng biến thể.
            if (array_key_exists('units', $data)) {
                $this->syncVariantUnits($product, (array) $data['units']);
            }

            return $product->fresh(['variants.skus', 'variants.units', 'skus', 'images', 'category', 'attributes.values']);
        });
    }

    /**
     * Đồng bộ đơn vị tính theo biến thể.
     *
     * Payload: ['units' => [variant_sku => [unit, unit, ...]]]
     *
     * @param  array<string, array<int, array<string, mixed>>>  $unitsBySku
     */
    protected function syncVariantUnits(Product $product, array $unitsBySku): void
    {
        $product->loadMissing('variants');

        foreach ($product->variants as $variant) {
            $key = (string) ($variant->sku);

            if (! array_key_exists($key, $unitsBySku)) {
                continue;
            }

            $this->unitService->syncUnits($variant, (array) $unitsBySku[$key]);
        }
    }

    /**
     * Nhân bản sản phẩm — clone cơ chế Haravan.
     *
     * Copy toàn bộ cấu trúc: thuộc tính, biến thể, ảnh, mô tả, thông số.
     * SKU mới được sinh tự động (hậu tố `-COPY`, `-COPY-2`, ...) để không đụng
     * SKU gốc. Tồn kho về 0 vì bản sao là sản phẩm mới.
     */
    public function duplicate(Product $product, ?int $changedBy = null): Product
    {
        return DB::transaction(function () use ($product, $changedBy) {
            $source = $product->loadMissing([
                'variants.units',
                'attributes.values',
                'images',
                'skus',
            ]);

            $copy = Product::create([
                'category_id' => $source->category_id,
                'name' => $source->name.' (bản sao)',
                'slug' => Product::generateSlug($source->name.' bản sao'),
                'sku' => null,
                'brand' => $source->brand,
                'description' => $source->description,
                'specifications' => $source->specifications,
                'price' => $source->price,
                'sale_price' => $source->sale_price,
                'cost_price' => $source->cost_price,
                'image' => $source->image,
                'source' => $source->source,
                'has_variants' => $source->has_variants,
                'is_active' => false,
                'is_featured' => false,
                'is_new' => false,
                'meta_title' => $source->meta_title,
                'meta_description' => $source->meta_description,
            ]);

            // Copy thuộc tính.
            foreach ($source->attributes as $attribute) {
                $newAttribute = $copy->attributes()->create([
                    'name' => $attribute->name,
                    'position' => $attribute->position,
                ]);

                foreach ($attribute->values as $value) {
                    $newAttribute->values()->create([
                        'value' => $value->value,
                        'sort_order' => $value->sort_order,
                    ]);
                }
            }

            // Copy biến thể (SKU mới, tồn kho = 0).
            foreach ($source->variants as $variant) {
                $newSku = $this->generateDuplicateSku($variant->sku);

                $newVariant = $copy->variants()->create([
                    'name' => $variant->name,
                    'sku' => $newSku,
                    'option_1_name' => $variant->option_1_name,
                    'option_1_value' => $variant->option_1_value,
                    'option_2_name' => $variant->option_2_name,
                    'option_2_value' => $variant->option_2_value,
                    'option_3_name' => $variant->option_3_name,
                    'option_3_value' => $variant->option_3_value,
                    'attribute_signature' => $variant->attribute_signature,
                    'price' => $variant->price,
                    'sale_price' => $variant->sale_price,
                    'cost_price' => $variant->cost_price,
                    'image' => $variant->image,
                    'sort_order' => $variant->sort_order,
                    'is_active' => $variant->is_active,
                ]);

                // Copy SKU (tồn kho = 0, chưa đồng bộ BigSeller).
                Sku::create([
                    'product_id' => $copy->id,
                    'product_variant_id' => $newVariant->id,
                    'sku' => $newSku,
                    'name' => $variant->name,
                    'source' => $copy->source,
                    'available_stock' => 0,
                    'safety_stock' => (int) config('bigseller.safety_stock', 0),
                    'price' => $variant->effectivePrice(),
                    'cost_price' => $variant->cost_price,
                ]);

                // GĐ2 — Copy đơn vị tính (giữ nguyên tỷ lệ quy đổi).
                foreach ($variant->units as $unit) {
                    $newVariant->units()->create([
                        'name' => $unit->name,
                        'conversion_rate' => $unit->conversion_rate,
                        'is_base' => $unit->is_base,
                        'price' => $unit->price,
                        'is_sellable' => $unit->is_sellable,
                        'barcode' => $unit->barcode,
                        'sort_order' => $unit->sort_order,
                    ]);
                }
            }

            // Copy ảnh (bản ghi, không copy file vật lý — tránh nhân bản storage).
            foreach ($source->images as $image) {
                $copy->images()->create([
                    'path' => $image->path,
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                ]);
            }

            $this->historyService->record(
                $copy->variants()->first() ?? new ProductVariant(['product_id' => $copy->id]),
                [],
                ['is_active' => false],
                $changedBy,
            );

            return $copy->fresh([
                'variants.skus',
                'variants.units',
                'skus',
                'images',
                'category',
                'attributes.values',
            ]);
        });
    }

    /**
     * Sinh SKU cho bản sao: "ABC" → "ABC-COPY" → "ABC-COPY-2".
     */
    protected function generateDuplicateSku(string $source): string
    {
        $base = Str::limit($source.'-COPY', 100, '');
        $candidate = $base;
        $suffix = 1;

        while (ProductVariant::withTrashed()->where('sku', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
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

            $isNew = $variant === null;

            if ($isNew) {
                $variant = new ProductVariant(['sku' => $sku]);
            }

            // Snapshot trước khi đổi (để ghi lịch sử — GĐ3).
            $before = $variant->exists ? $variant->only(ProductVariantHistoryService::TRACKED_FIELDS) : [];

            $variant->fill([
                'product_id' => $product->id,
                'name' => $variantData['name'] ?? $sku,
                'option_1_name' => $variantData['option_1_name'] ?? null,
                'option_1_value' => $variantData['option_1_value'] ?? null,
                'option_2_name' => $variantData['option_2_name'] ?? null,
                'option_2_value' => $variantData['option_2_value'] ?? null,
                'option_3_name' => $variantData['option_3_name'] ?? null,
                'option_3_value' => $variantData['option_3_value'] ?? null,
                'price' => $variantData['price'] ?? $product->price,
                'sale_price' => $variantData['sale_price'] ?? null,
                'cost_price' => $variantData['cost_price'] ?? $product->cost_price,
                'image' => $variantData['image'] ?? null,
                'sort_order' => $variantData['sort_order'] ?? 0,
                'is_active' => $variantData['is_active'] ?? true,
            ]);

            $variant->save();

            // GĐ3 — Ghi lịch sử cập nhật biến thể.
            if (! $isNew) {
                $this->historyService->record(
                    $variant,
                    $before,
                    $variant->only(ProductVariantHistoryService::TRACKED_FIELDS),
                    $variantData['changed_by'] ?? null,
                );
            }

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
     * Cập nhật MỘT biến thể (dùng tại màn "Chi tiết biến thể").
     *
     * Tự động ghi lịch sử thay đổi (GĐ3) và đồng bộ giá xuống bảng SKU.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateVariant(ProductVariant $variant, array $data, ?int $changedBy = null): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data, $changedBy): ProductVariant {
            $before = $variant->only(ProductVariantHistoryService::TRACKED_FIELDS);

            $variant->fill(array_intersect_key($data, array_flip([
                'name', 'price', 'sale_price', 'cost_price', 'image', 'is_active', 'sort_order',
            ])));

            $variant->save();

            // Đồng bộ giá xuống SKU (nguồn chuẩn cho tồn kho).
            $skuRecord = Sku::where('sku', $variant->sku)->first();

            if ($skuRecord !== null) {
                $skuRecord->forceFill([
                    'name' => $variant->name,
                    'price' => $variant->effectivePrice(),
                    'cost_price' => $variant->cost_price,
                ])->save();
            }

            $this->historyService->record(
                $variant,
                $before,
                $variant->only(ProductVariantHistoryService::TRACKED_FIELDS),
                $changedBy,
            );

            return $variant->fresh(['skus', 'units', 'histories']);
        });
    }

    /**
     * Đơn vị tính của một biến thể (đã tính giá theo tỷ lệ quy đổi).
     *
     * @return array<int, array<string, mixed>>
     */
    public function variantUnits(ProductVariant $variant): array
    {
        $variant->loadMissing('units');

        return $this->unitService->pricedUnits($variant);
    }

    /**
     * Cập nhật đơn vị tính của một biến thể.
     *
     * @param  array<int, array<string, mixed>>  $units
     */
    public function updateVariantUnits(ProductVariant $variant, array $units): void
    {
        $this->unitService->syncUnits($variant, $units);
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
