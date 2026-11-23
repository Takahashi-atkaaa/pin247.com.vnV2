<?php

namespace Tests\Feature;

use App\Models\PriceTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang sửa bậc giá cần endpoint đọc chi tiết 1 bậc giá.
 */
class PriceTierShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_price_tier_detail(): void
    {
        $admin = $this->adminUser();
        $sku = $this->makeSku('TIER-SHOW-001');

        $tier = PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 10,
            'max_qty' => 99,
            'unit_price' => 50000,
            'label' => 'Lô 10-99',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/price-list/tiers/{$tier->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tier->id)
            ->assertJsonPath('data.sku', 'TIER-SHOW-001')
            ->assertJsonPath('data.min_qty', 10)
            ->assertJsonPath('data.max_qty', 99)
            ->assertJsonPath('data.unit_price', 50000);
    }

    public function test_guest_cannot_view_price_tier_detail(): void
    {
        $sku = $this->makeSku('TIER-SHOW-002');

        $tier = PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => null,
            'unit_price' => 50000,
        ]);

        $this->getJson("/api/admin/price-list/tiers/{$tier->id}")
            ->assertUnauthorized();
    }
}
