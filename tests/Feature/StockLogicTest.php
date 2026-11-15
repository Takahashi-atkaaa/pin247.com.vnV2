<?php

namespace Tests\Feature;

use App\Enums\SyncState;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_sellable_quantity_subtracts_safety_and_local_reservation(): void
    {
        $sku = $this->makeSku('SKU-LOGIC', stock: 100, safetyStock: 10);

        $this->assertSame(90, $sku->sellableQuantity());

        $sku->forceFill(['local_reserved_stock' => 25])->save();

        $this->assertSame(65, $sku->fresh()->sellableQuantity());
    }

    public function test_sellable_quantity_never_goes_below_zero(): void
    {
        $sku = $this->makeSku('SKU-ZERO', stock: 5, safetyStock: 10);

        $this->assertSame(0, $sku->sellableQuantity());
        $this->assertFalse($sku->isInStock());
    }

    public function test_is_in_stock_respects_safety_stock(): void
    {
        $sku = $this->makeSku('SKU-EDGE', stock: 10, safetyStock: 10);

        $this->assertFalse($sku->isInStock(), 'Tồn = safety => không được bán.');

        $sku->forceFill(['available_stock' => 11])->save();
        $this->assertTrue($sku->fresh()->isInStock());
    }

    public function test_sku_business_code_is_unique(): void
    {
        $this->makeSku('SKU-UNIQUE');

        $this->expectException(\Illuminate\Database\QueryException::class);

        Sku::create([
            'sku' => 'SKU-UNIQUE',
            'name' => 'Trùng',
            'available_stock' => 1,
            'on_hand_stock' => 1,
            'reserved_stock' => 0,
            'locked_stock' => 0,
            'shipped_stock' => 0,
            'safety_stock' => 0,
            'price' => 1000,
            'sync_state' => SyncState::Pending,
        ]);
    }
}
