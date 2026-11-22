<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thao tác hàng loạt trên danh sách sản phẩm — clone cơ chế Haravan.
 *
 * Haravan cho phép tick nhiều sản phẩm rồi: thêm/xoá nhãn, đăng lên kênh,
 * ẩn khỏi kênh, xoá hàng loạt.
 *
 * Ở đây hỗ trợ các thao tác áp dụng được cho mô hình hiện tại:
 *   - Bật/tắt hiển thị (is_active)
 *   - Đánh dấu nổi bật / hàng mới
 *   - Chuyển danh mục
 *   - Xoá hàng loạt
 */
class ProductBulkController extends Controller
{
    /**
     * Thao tác hàng loạt trên sản phẩm.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
            'action' => ['required', 'string', 'in:activate,deactivate,feature,unfeature,mark_new,unmark_new,move_category,delete'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        if ($action === 'move_category' && empty($validated['category_id'])) {
            return response()->json([
                'message' => 'Vui lòng chọn danh mục để chuyển sản phẩm.',
            ], 422);
        }

        $products = Product::whereIn('id', $ids)->get();

        $affected = 0;

        foreach ($products as $product) {
            match ($action) {
                'activate' => $product->forceFill(['is_active' => true])->save(),
                'deactivate' => $product->forceFill(['is_active' => false])->save(),
                'feature' => $product->forceFill(['is_featured' => true])->save(),
                'unfeature' => $product->forceFill(['is_featured' => false])->save(),
                'mark_new' => $product->forceFill(['is_new' => true])->save(),
                'unmark_new' => $product->forceFill(['is_new' => false])->save(),
                'move_category' => $product->forceFill(['category_id' => $validated['category_id']])->save(),
                'delete' => $this->deleteProduct($product),
                default => null,
            };

            $affected++;
        }

        $messages = [
            'activate' => 'Đã hiển thị %d sản phẩm.',
            'deactivate' => 'Đã ẩn %d sản phẩm.',
            'feature' => 'Đã đánh dấu nổi bật %d sản phẩm.',
            'unfeature' => 'Đã bỏ nổi bật %d sản phẩm.',
            'mark_new' => 'Đã đánh dấu hàng mới %d sản phẩm.',
            'unmark_new' => 'Đã bỏ đánh dấu hàng mới %d sản phẩm.',
            'move_category' => 'Đã chuyển %d sản phẩm sang danh mục mới.',
            'delete' => 'Đã xoá %d sản phẩm.',
        ];

        return response()->json([
            'message' => sprintf($messages[$action], $affected),
            'data' => ['affected' => $affected],
        ]);
    }

    /**
     * Thao tác hàng loạt trên BIẾN THỂ (đổi giá, bật/tắt theo nhóm).
     */
    public function updateVariants(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['integer', 'exists:product_variants,id'],
            'action' => ['required', 'string', 'in:activate,deactivate,adjust_price'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'percent' => ['nullable', 'numeric', 'min:-90', 'max:900'],
        ]);

        $action = $validated['action'];

        if ($action === 'adjust_price' && ! isset($validated['price']) && ! isset($validated['percent'])) {
            return response()->json([
                'message' => 'Vui lòng nhập giá mới hoặc phần trăm điều chỉnh.',
            ], 422);
        }

        $variants = ProductVariant::whereIn('id', $validated['variant_ids'])->get();

        $affected = 0;

        foreach ($variants as $variant) {
            $before = $variant->only(ProductVariantHistoryService::TRACKED_FIELDS);

            match ($action) {
                'activate' => $variant->forceFill(['is_active' => true])->save(),
                'deactivate' => $variant->forceFill(['is_active' => false])->save(),
                'adjust_price' => $this->adjustPrice($variant, $validated),
                default => null,
            };

            if ($action === 'adjust_price') {
                $variant->histories()->create([
                    'product_id' => $variant->product_id,
                    'changed_by' => $request->user()?->id,
                    'changed_fields' => ['price'],
                    'before' => ['price' => $before['price']],
                    'after' => ['price' => $variant->price],
                    'summary' => 'Giá bán (hàng loạt): '.number_format((float) $before['price'], 0, ',', '.').'đ → '
                        .number_format((float) $variant->price, 0, ',', '.').'đ',
                ]);
            }

            $affected++;
        }

        $messages = [
            'activate' => 'Đã bật %d biến thể.',
            'deactivate' => 'Đã tắt %d biến thể.',
            'adjust_price' => 'Đã điều chỉnh giá %d biến thể.',
        ];

        return response()->json([
            'message' => sprintf($messages[$action], $affected),
            'data' => ['affected' => $affected],
        ]);
    }

    /**
     * Điều chỉnh giá theo giá trị tuyệt đối hoặc phần trăm.
     */
    protected function adjustPrice(ProductVariant $variant, array $validated): void
    {
        if (isset($validated['price'])) {
            $variant->forceFill(['price' => (float) $validated['price']])->save();

            return;
        }

        $percent = (float) ($validated['percent'] ?? 0);
        $newPrice = max(0, round((float) $variant->price * (1 + $percent / 100), 2));

        $variant->forceFill(['price' => $newPrice])->save();
    }

    /**
     * Xoá sản phẩm kèm biến thể.
     */
    protected function deleteProduct(Product $product): void
    {
        $product->variants()->each(fn ($variant) => $variant->delete());
        $product->delete();
    }
}
