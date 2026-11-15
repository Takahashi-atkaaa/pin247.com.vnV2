<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
    ) {
    }

    /**
     * Danh sách sản phẩm (Admin) — có thêm phân biệt nguồn.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with(['category', 'skus', 'variants.skus']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('skus', fn ($sk) => $sk->where('sku', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('source')) {
            $query->where('source', $request->query('source'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->boolean('is_active')) {
            $query->where('is_active', true);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        return ProductResource::collection($query->latest('id')->paginate($perPage)->withQueryString());
    }

    /**
     * Tạo sản phẩm.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.option_1_name' => ['nullable', 'string', 'max:100'],
            'variants.*.option_1_value' => ['nullable', 'string', 'max:100'],
            'variants.*.option_2_name' => ['nullable', 'string', 'max:100'],
            'variants.*.option_2_value' => ['nullable', 'string', 'max:100'],
        ]);

        $product = $this->productService->create($validated);

        return response()->json([
            'message' => 'Tạo sản phẩm thành công.',
            'data' => new ProductResource($product),
        ], 201);
    }

    /**
     * Chi tiết sản phẩm (Admin).
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'variants.skus', 'skus', 'images']);

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Cập nhật sản phẩm.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $product = $this->productService->update($product, $validated);

        return response()->json([
            'message' => 'Cập nhật sản phẩm thành công.',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Xóa sản phẩm.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json([
            'message' => 'Đã xóa sản phẩm.',
        ]);
    }

    /**
     * Upload ảnh.
     */
    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $this->productService->uploadImage(
            $product,
            $request->file('image'),
            $request->input('alt_text'),
        );

        return response()->json([
            'message' => 'Upload ảnh thành công.',
            'data' => ['path' => $path, 'url' => asset('storage/'.$path)],
        ], 201);
    }

    /**
     * Xóa ảnh.
     */
    public function destroyImage(Product $product, int $image): JsonResponse
    {
        $this->productService->deleteImage($product, $image);

        return response()->json(['message' => 'Đã xóa ảnh.']);
    }

    /**
     * Danh mục.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->with(['children'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'sort_order' => $category->sort_order,
                'is_active' => (bool) $category->is_active,
                'products_count' => $category->products()->count(),
                'children' => $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'is_active' => (bool) $child->is_active,
                ]),
            ]);

        return response()->json(['data' => $categories]);
    }

    /**
     * Tạo danh mục.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = $this->productService->createCategory($validated);

        return response()->json([
            'message' => 'Tạo danh mục thành công.',
            'data' => $category,
        ], 201);
    }

    /**
     * Cập nhật danh mục.
     */
    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = $this->productService->updateCategory($category, $validated);

        return response()->json([
            'message' => 'Cập nhật danh mục thành công.',
            'data' => $category,
        ]);
    }

    /**
     * Xóa danh mục.
     */
    public function destroyCategory(Category $category): JsonResponse
    {
        try {
            $this->productService->deleteCategory($category);
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã xóa danh mục.']);
    }
}
