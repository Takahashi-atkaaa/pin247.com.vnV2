<?php

namespace Database\Seeders;

use App\Models\PriceTier;
use App\Models\Sku;
use Illuminate\Database\Seeder;

/**
 * Sinh bậc giá mặc định cho toàn bộ SKU hiện có.
 *
 * Quy tắc: giá gốc (Lẻ) → giảm dần theo số lượng.
 *   Lẻ      : giá hiện tại của SKU
 *   10 - 49  : -5%
 *   50 - 199 : -8%
 *   200+     : -12%
 */
class PriceTierSeeder extends Seeder
{
    /**
     * @var array<int, array{min_qty: int, max_qty: ?int, discount: float, label: string}>
     */
    protected array $tiers = [
        ['min_qty' => 1, 'max_qty' => 9, 'discount' => 0.00, 'label' => 'Lẻ'],
        ['min_qty' => 10, 'max_qty' => 49, 'discount' => 0.05, 'label' => 'Lô 10-49'],
        ['min_qty' => 50, 'max_qty' => 199, 'discount' => 0.08, 'label' => 'Lô 50-199'],
        ['min_qty' => 200, 'max_qty' => null, 'discount' => 0.12, 'label' => 'Lô 200+'],
    ];

    public function run(): void
    {
        $skus = Sku::query()->with('product')->get();

        if ($skus->isEmpty()) {
            $this->command?->warn('Chưa có SKU nào. Hãy chạy ProductSeeder trước.');

            return;
        }

        $created = 0;

        foreach ($skus as $sku) {
            $basePrice = (float) ($sku->variant?->effectivePrice()
                ?? $sku->product?->effectivePrice()
                ?? $sku->price);

            if ($basePrice <= 0) {
                continue;
            }

            foreach ($this->tiers as $tier) {
                $price = round($basePrice * (1 - $tier['discount']), -2); // làm tròn nghìn

                if ($price <= 0) {
                    $price = $basePrice;
                }

                $exists = PriceTier::where('sku_id', $sku->id)
                    ->where('min_qty', $tier['min_qty'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                PriceTier::create([
                    'sku_id' => $sku->id,
                    'min_qty' => $tier['min_qty'],
                    'max_qty' => $tier['max_qty'],
                    'unit_price' => $price,
                    'label' => $tier['label'],
                    'sort_order' => $tier['min_qty'],
                ]);

                $created++;
            }
        }

        $this->command?->info("Đã tạo {$created} bậc giá cho {$skus->count()} SKU.");
    }
}
