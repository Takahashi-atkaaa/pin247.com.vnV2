<?php

namespace App\Services\BigSeller;

use App\Enums\SyncState;
use App\Models\BigSellerProductMapping;
use App\Models\Sku;
use App\Services\BigSeller\Contracts\BigSellerClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Đồng bộ sản phẩm/SKU từ BigSeller.
 *
 * CHỈ hoạt động khi tài khoản được cấp quyền API sản phẩm.
 * Nếu chưa có quyền, dùng luồng nhập CSV/Excel (ImportService) để khởi tạo.
 */
class BigSellerProductService
{
    public function __construct(
        protected BigSellerClient $client,
        protected BigSellerAuthService $auth,
    ) {
    }

    /**
     * Đồng bộ danh sách SKU từ BigSeller về website.
     *
     * @param  array<string, mixed>  $params
     * @return array{synced: int, skipped: int, created: int, missing_skus: array<int, string>, message: string}
     */
    public function syncProducts(array $params = []): array
    {
        if (! $this->auth->hasCapability('products')) {
            return [
                'synced' => 0,
                'skipped' => 0,
                'created' => 0,
                'missing_skus' => [],
                'message' => 'Tài khoản BigSeller chưa được cấp quyền API sản phẩm. '
                    .'Hãy dùng luồng nhập CSV/Excel để khởi tạo danh mục sản phẩm và ánh xạ SKU.',
            ];
        }

        $result = $this->client->getProducts($params);
        $items = $result['items'] ?? [];

        $synced = 0;
        $created = 0;
        $skipped = 0;

        foreach ($items as $productData) {
            if (blank($productData->sku)) {
                $skipped++;

                continue;
            }

            $existing = BigSellerProductMapping::where('bigseller_sku', $productData->sku)->first();

            if ($existing) {
                // Cập nhật, KHÔNG tạo trùng. Không tự đổi SKU đã xác nhận.
                $existing->forceFill([
                    'bigseller_product_id' => $productData->bigSellerProductId,
                    'bigseller_variant_id' => $productData->bigSellerVariantId,
                    'product_name' => $productData->name,
                    'image' => $productData->image,
                    'price' => $productData->price,
                    'attributes' => $productData->attributes,
                    'sync_state' => SyncState::Synced,
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ])->save();

                $synced++;

                continue;
            }

            BigSellerProductMapping::create([
                'bigseller_sku' => $productData->sku,
                'bigseller_product_id' => $productData->bigSellerProductId,
                'bigseller_variant_id' => $productData->bigSellerVariantId,
                'product_name' => $productData->name,
                'image' => $productData->image,
                'price' => $productData->price,
                'attributes' => $productData->attributes,
                'sync_state' => SyncState::Synced,
                'last_synced_at' => now(),
            ]);

            $created++;
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'created' => $created,
            'missing_skus' => [],
            'message' => "Đã đồng bộ {$synced} SKU, tạo mới {$created} mapping.",
        ];
    }

    /**
     * Đối chiếu SKU giữa BigSeller và website.
     *
     * @return array{matched: array<int, string>, website_only: array<int, string>, bigseller_only: array<int, string>}
     */
    public function reconcileSkus(): array
    {
        $websiteSkus = Sku::query()->pluck('sku')->all();
        $bigSellerSkus = BigSellerProductMapping::query()->pluck('bigseller_sku')->all();

        return [
            'matched' => array_values(array_intersect($websiteSkus, $bigSellerSkus)),
            'website_only' => array_values(array_diff($websiteSkus, $bigSellerSkus)),
            'bigseller_only' => array_values(array_diff($bigSellerSkus, $websiteSkus)),
        ];
    }

    /**
     * Tạo mapping thủ công (dùng khi nhập CSV hoặc Admin khai báo tay).
     */
    public function createMapping(array $data): BigSellerProductMapping
    {
        return DB::transaction(function () use ($data) {
            $mapping = BigSellerProductMapping::updateOrCreate(
                ['bigseller_sku' => $data['bigseller_sku']],
                [
                    'product_id' => $data['product_id'] ?? null,
                    'product_variant_id' => $data['product_variant_id'] ?? null,
                    'sku_id' => $data['sku_id'] ?? null,
                    'bigseller_product_id' => $data['bigseller_product_id'] ?? null,
                    'bigseller_variant_id' => $data['bigseller_variant_id'] ?? null,
                    'product_name' => $data['product_name'] ?? null,
                    'image' => $data['image'] ?? null,
                    'price' => $data['price'] ?? null,
                    'attributes' => $data['attributes'] ?? null,
                    'sync_state' => $data['sync_state'] ?? SyncState::Pending,
                    'last_synced_at' => $data['last_synced_at'] ?? null,
                    'sync_error' => null,
                ],
            );

            return $mapping;
        });
    }
}
