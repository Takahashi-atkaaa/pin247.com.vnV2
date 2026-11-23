<?php

namespace Tests\Feature;

use App\Models\PriceListDocument;
use App\Models\PriceTier;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PriceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Trang bảng giá phải xem được KHÔNG cần đăng nhập.
     */
    public function test_price_list_is_publicly_accessible(): void
    {
        $sku = $this->makeSku('CELL-18650-TEST', price: 79000);

        PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => 9,
            'unit_price' => 79000,
            'label' => 'Lẻ',
        ]);

        PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 10,
            'max_qty' => null,
            'unit_price' => 74000,
            'label' => 'Lô 10+',
        ]);

        $response = $this->getJson('/api/price-list');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'CELL-18650-TEST')
            ->assertJsonCount(2, 'data.0.tiers')
            ->assertJsonPath('data.0.tiers.0.unit_price', 79000)
            ->assertJsonPath('data.0.tiers.1.min_qty', 10)
            ->assertJsonPath('data.0.min_order_qty', 10);
    }

    /**
     * Header cột động phải chứa các mốc bậc giá.
     */
    public function test_price_list_returns_tier_columns(): void
    {
        $sku = $this->makeSku('SKU-COLUMNS');

        PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => 9,
            'unit_price' => 100000,
        ]);

        $response = $this->getJson('/api/price-list');

        $response->assertOk()
            ->assertJsonPath('tier_columns.0.min_qty', 1)
            ->assertJsonPath('tier_columns.0.max_qty', 9);
    }

    /**
     * SKU không có bậc giá thì không xuất hiện trong bảng giá.
     */
    public function test_sku_without_tiers_is_excluded(): void
    {
        $this->makeSku('SKU-NO-TIER');

        $response = $this->getJson('/api/price-list');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * Lọc theo danh mục.
     */
    public function test_price_list_can_be_filtered_by_category(): void
    {
        $sku = $this->makeSku('SKU-FILTER');
        $sku->product->update(['category_id' => $sku->product->category->id]);

        PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => null,
            'unit_price' => 50000,
        ]);

        $this->getJson('/api/price-list?category=khong-ton-tai')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/price-list?category=test-cat')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Tìm kiếm theo SKU.
     */
    public function test_price_list_can_be_searched_by_sku(): void
    {
        $sku = $this->makeSku('SKU-SEARCH-01');

        PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => null,
            'unit_price' => 50000,
        ]);

        $this->getJson('/api/price-list?search=SKU-SEARCH-01')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/price-list?search=khong-co-that')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /* ------------------------------------------------------------------
     | Admin API
     * ------------------------------------------------------------------ */

    public function test_admin_price_list_requires_authentication(): void
    {
        $this->getJson('/api/admin/price-list')->assertUnauthorized();
    }

    public function test_admin_can_create_price_tier(): void
    {
        $sku = $this->makeSku('SKU-ADMIN');

        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/admin/price-list/tiers', [
                'sku' => 'SKU-ADMIN',
                'min_qty' => 10,
                'max_qty' => 49,
                'unit_price' => 74000,
                'label' => 'Lô 10-49',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.min_qty', 10)
            ->assertJsonPath('data.unit_price', 74000);

        $this->assertDatabaseHas('price_tiers', [
            'sku_id' => $sku->id,
            'min_qty' => 10,
            'unit_price' => 74000,
        ]);
    }

    public function test_admin_cannot_create_duplicate_tier_start(): void
    {
        $sku = $this->makeSku('SKU-DUP');

        PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 10,
            'max_qty' => 49,
            'unit_price' => 74000,
        ]);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/admin/price-list/tiers', [
                'sku' => 'SKU-DUP',
                'min_qty' => 10,
                'max_qty' => 99,
                'unit_price' => 70000,
            ])
            ->assertStatus(422);
    }

    public function test_admin_price_tier_rejects_unknown_sku(): void
    {
        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/admin/price-list/tiers', [
                'sku' => 'SKU-KHONG-CO',
                'min_qty' => 1,
                'unit_price' => 1000,
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_delete_price_tier(): void
    {
        $sku = $this->makeSku('SKU-DEL');

        $tier = PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => null,
            'unit_price' => 50000,
        ]);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->deleteJson("/api/admin/price-list/tiers/{$tier->id}")
            ->assertOk();

        $this->assertDatabaseMissing('price_tiers', ['id' => $tier->id]);
    }

    /**
     * Import CSV: dòng hợp lệ được ghi, dòng lỗi được báo cáo nhưng KHÔNG
     * chặn cả file.
     */
    public function test_admin_can_import_price_tiers_from_csv(): void
    {
        $this->makeSku('SKU-IMPORT-01');
        $this->makeSku('SKU-IMPORT-02');

        $csv = "sku,min_qty,max_qty,unit_price,label\n"
            ."SKU-IMPORT-01,1,9,79000,Lẻ\n"
            ."SKU-IMPORT-01,10,,74000,Lô 10+\n"
            ."SKU-KHONG-TON-TAI,1,9,50000,Lẻ\n";

        $file = UploadedFile::fake()->createWithContent('bang-gia.csv', $csv);

        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->post('/api/admin/price-list/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonCount(1, 'data.errors');

        $this->assertDatabaseHas('price_tiers', ['sku_id' => Sku::where('sku', 'SKU-IMPORT-01')->value('id'), 'min_qty' => 1]);
        $this->assertDatabaseHas('price_tiers', ['sku_id' => Sku::where('sku', 'SKU-IMPORT-01')->value('id'), 'min_qty' => 10]);
    }

    /**
     * Import lại cùng một bậc => cập nhật chứ không tạo mới.
     */
    public function test_import_updates_existing_tier(): void
    {
        $sku = $this->makeSku('SKU-UPDATE-01');

        PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => 9,
            'unit_price' => 79000,
        ]);

        $csv = "sku,min_qty,max_qty,unit_price,label\n"
            ."SKU-UPDATE-01,1,9,69000,Lẻ giảm\n";

        $file = UploadedFile::fake()->createWithContent('bang-gia.csv', $csv);

        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->post('/api/admin/price-list/import', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('price_tiers', [
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'unit_price' => 69000,
        ]);
    }

    /* ------------------------------------------------------------------
     | File bảng giá
     * ------------------------------------------------------------------ */

    public function test_admin_can_upload_price_list_document(): void
    {
        $file = UploadedFile::fake()->create('bang-gia.pdf', 120, 'application/pdf');

        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->post('/api/admin/price-list/documents', [
                'title' => 'Bảng giá 12/2026',
                'file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Bảng giá 12/2026');

        $this->assertDatabaseHas('price_list_documents', ['title' => 'Bảng giá 12/2026']);
    }

    public function test_admin_upload_rejects_disallowed_extension(): void
    {
        $file = UploadedFile::fake()->create('malware.php', 10, 'application/x-php');

        $this->actingAs($this->adminUser(), 'sanctum')
            ->post('/api/admin/price-list/documents', [
                'title' => 'File độc',
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_only_published_documents_are_visible_to_public(): void
    {
        PriceListDocument::create([
            'title' => 'Đang hiện',
            'original_name' => 'a.csv',
            'path' => 'price-lists/a.csv',
            'mime_type' => 'text/csv',
            'size' => 100,
            'is_published' => true,
        ]);

        PriceListDocument::create([
            'title' => 'Đang ẩn',
            'original_name' => 'b.csv',
            'path' => 'price-lists/b.csv',
            'mime_type' => 'text/csv',
            'size' => 100,
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/price-list/documents');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Đang hiện');
    }

    /**
     * Tạo user admin để đăng nhập.
     */
    protected function adminUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::create(array_merge([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('secret1234'),
            'is_admin' => true,
        ], $attributes));
    }
}
