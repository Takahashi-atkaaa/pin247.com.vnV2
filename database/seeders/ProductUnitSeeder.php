<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Seed đơn vị tính (Unit of Measure) cho các biến thể đã tồn tại.
 *
 * GĐ2 yêu cầu mỗi biến thể có ít nhất 01 đơn vị cơ sở (`is_base = 1`,
 * `conversion_rate = 1`). Nếu không có, storefront không hiển thị được
 * "chọn đơn vị tính" và giá quy đổi không hoạt động.
 *
 * Quy tắc:
 *   - Đơn vị cơ sở tên theo loại sản phẩm (pin / dây / bộ / mạch...).
 *   - Tự động thêm 1 đơn vị quy đổi phổ biến (lô 10 / hộp 12...).
 *   - Giá quy đổi = giá cơ sở × hệ số quy đổi.
 *   - `barcode` để NULL — yêu cầu "không cần barcode".
 *
 * Chạy an toàn nhiều lần (idempotent): chỉ tạo khi biến thể chưa có đơn vị.
 */
class ProductUnitSeeder extends Seeder
{
    /**
     * Đơn vị cơ sở gợi ý theo từ khoá trong tên sản phẩm.
     *
     * @var array<string, string>
     */
    protected array $baseUnitHints = [
        'pin' => 'viên',
        'cell' => 'viên',
        '18650' => 'viên',
        '21700' => 'viên',
        'dây' => 'cây',
        'day' => 'cây',
        'cáp' => 'cây',
        'bộ' => 'bộ',
        'bo' => 'bộ',
        'mạch' => 'mạch',
        'mach' => 'mạch',
        'sạc' => 'bộ',
        'sac' => 'bộ',
        'đầu' => 'cỡ',
        'dau' => 'cỡ',
        'kìm' => 'cây',
        'kim' => 'cây',
        'vít' => 'cây',
        'vit' => 'cây',
        'khay' => 'khay',
        'hộp' => 'hộp',
        'hop' => 'hộp',
    ];

    /**
     * Đơn vị quy đổi gợi ý: [tên, hệ số quy đổi].
     *
     * @var array<int, array{0: string, 1: int}>
     */
    protected array $convertedUnits = [
        ['lô 10', 10],
        ['hộp 12', 12],
    ];

    public function run(): void
    {
        Product::query()
            ->where('has_variants', true)
            ->with('variants')
            ->chunkById(50, function ($products): void {
                foreach ($products as $product) {
                    foreach ($product->variants as $variant) {
                        $this->seedUnits($product, $variant);
                    }
                }
            });
    }

    protected function seedUnits(Product $product, ProductVariant $variant): void
    {
        // Đã có đơn vị rồi → không đụng (idempotent).
        if ($variant->units()->exists()) {
            return;
        }

        $baseName = $this->guessBaseUnit($product->name ?? '');
        $basePrice = (float) ($variant->price ?: $product->price);

        // 1. Đơn vị cơ sở — hệ số quy đổi luôn = 1.
        ProductUnit::create([
            'product_variant_id' => $variant->id,
            'name' => $baseName,
            'conversion_rate' => 1,
            'is_base' => true,
            'price' => $basePrice,
            'is_sellable' => true,
            'barcode' => null,
        ]);

        // 2. Đơn vị quy đổi — giá = giá cơ sở × hệ số quy đổi.
        foreach ($this->convertedUnits as [$name, $rate]) {
            ProductUnit::create([
                'product_variant_id' => $variant->id,
                'name' => $name,
                'conversion_rate' => $rate,
                'is_base' => false,
                'price' => round($basePrice * $rate, 2),
                'is_sellable' => true,
                'barcode' => null,
            ]);
        }
    }

    /**
     * Đoán tên đơn vị cơ sở theo từ khoá trong tên sản phẩm.
     */
    protected function guessBaseUnit(string $productName): string
    {
        $name = mb_strtolower($productName);

        foreach ($this->baseUnitHints as $keyword => $unit) {
            if (str_contains($name, $keyword)) {
                return $unit;
            }
        }

        return 'cái';
    }
}
