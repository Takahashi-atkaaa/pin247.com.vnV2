<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Danh sách sản phẩm + tìm kiếm + lọc + sắp xếp.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with(['category', 'skus', 'variants.skus']);

        // --- Tìm kiếm: theo tên, SKU, mã sản phẩm ---
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('skus', fn ($sk) => $sk->where('sku', 'like', "%{$search}%"))
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$search}%"));
            });
        }

        // --- Lọc theo danh mục ---
        if ($categorySlug = $request->query('category')) {
            $category = Category::where('slug', $categorySlug)->first();

            if ($category) {
                $categoryIds = $category->children()->pluck('id')->push($category->id);
                $query->whereIn('category_id', $categoryIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // --- Lọc theo thương hiệu ---
        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }

        // --- Lọc theo giá ---
        if ($request->filled('min_price')) {
            $query->where(function ($q) use ($request): void {
                $q->where('price', '>=', (float) $request->query('min_price'))
                    ->orWhere('sale_price', '>=', (float) $request->query('min_price'));
            });
        }

        if ($request->filled('max_price')) {
            $query->where(function ($q) use ($request): void {
                $q->where('price', '<=', (float) $request->query('max_price'))
                    ->orWhere('sale_price', '<=', (float) $request->query('max_price'));
            });
        }

        // --- Lọc theo thuộc tính biến thể ---
        if ($attributes = $request->query('attributes')) {
            $attributes = is_array($attributes) ? $attributes : [$attributes];

            foreach ($attributes as $attribute) {
                if (! is_string($attribute) || $attribute === '') {
                    continue;
                }

                $query->whereHas('variants', function ($q) use ($attribute): void {
                    [$name, $value] = array_pad(explode(':', $attribute, 2), 2, null);

                    $q->where(function ($inner) use ($name, $value): void {
                        if ($value !== null && $value !== '') {
                            $inner->where('option_1_name', $name)
                                ->where('option_1_value', $value);
                        } else {
                            $inner->where('option_1_name', $name);
                        }
                    });
                });
            }
        }

        // --- Lọc theo tồn kho có sẵn ---
        if ($request->boolean('in_stock')) {
            $query->whereHas('skus', fn ($q) => $q->where('available_stock', '>', 0));
        }

        // --- Lọc theo cờ hiển thị ---
        if ($request->boolean('is_featured')) {
            $query->where('is_featured', true);
        }

        if ($request->boolean('is_new')) {
            $query->where('is_new', true);
        }

        // --- Sắp xếp ---
        $sort = $request->query('sort', 'newest');

        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) desc'),
            'best_selling' => $query->orderByDesc('sales_count'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->latest('id'),
        };

        $perPage = min(60, max(1, (int) $request->query('per_page', 12)));

        return ProductResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Chi tiết sản phẩm.
     */
    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'category',
                // Chỉ hiển thị biến thể đang bán trên website.
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.skus',
                'variants.units',
                'skus',
                'images',
            ])
            ->firstOrFail();

        // Sản phẩm liên quan: cùng danh mục, khác sản phẩm hiện tại.
        $related = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when(
                $product->category_id,
                fn ($q) => $q->where('category_id', $product->category_id)
            )
            ->with(['category', 'skus'])
            ->limit(8)
            ->get();

        return (new ProductResource($product))->additional([
            'related_products' => ProductResource::collection($related),
        ]);
    }

    /**
     * Danh sách danh mục cho menu.
     */
    public function categories()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'children' => $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ]),
            ]);

        return response()->json(['data' => $categories]);
    }

    /**
     * Tra cứu tồn kho khả dụng theo SKU (dùng cho giỏ hàng).
     */
    public function stock(Request $request)
    {
        $skus = (array) $request->query('skus', []);

        $stocks = Sku::query()
            ->whereIn('sku', $skus)
            ->get()
            ->mapWithKeys(fn ($sku) => [
                $sku->sku => [
                    'available' => (int) $sku->available_stock,
                    'sellable' => $sku->sellableQuantity(),
                    'in_stock' => $sku->isInStock(),
                ],
            ]);

        return response()->json(['data' => $stocks]);
    }
}
