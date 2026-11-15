<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Nghiệp vụ bảng giá.
 *
 * Bảng giá là nguồn chân lý cho trang /bang-gia:
 * mỗi SKU có nhiều bậc giá theo số lượng yêu cầu.
 */
class PriceListService
{
    /**
     * Header file CSV import/export.
     */
    public const CSV_HEADERS = ['sku', 'min_qty', 'max_qty', 'unit_price', 'label'];

    /**
     * Các SKU có bậc giá, đã nạp quan hệ cần thiết.
     *
     * @param  array<string, mixed>  $filters
     */
    public function querySkus(array $filters = []): Collection
    {
        return $this->baseSkuQuery($filters)
            ->with(['priceTiers' => fn ($t) => $t->orderBy('min_qty')])
            ->orderBy('sku')
            ->get();
    }

    /**
     * Truy vấn gốc (chưa phân trang) dùng cho cả list và export.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function baseSkuQuery(array $filters = [])
    {
        $query = Sku::query()
            ->with(['product.category', 'variant'])
            ->has('priceTiers');

        if ($categorySlug = trim((string) ($filters['category'] ?? ''))) {
            $query->whereHas('product', fn ($q) => $q
                ->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)));
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('variant', fn ($v) => $v->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('sku');
    }

    /**
     * Danh sách SKU có bậc giá, phân trang.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateSkus(array $filters, int $perPage = 30, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->baseSkuQuery($filters)
            ->with(['priceTiers' => fn ($t) => $t->orderBy('min_qty')])
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /**
     * Tất cả các mốc bậc giá đang dùng trên hệ thống.
     *
     * Dùng để dựng header cột động cho bảng ngang: mỗi mốc là một cột.
     *
     * @return Collection<int, array{min_qty: int, max_qty: ?int, label: string}>
     */
    public function tierColumns(): Collection
    {
        return PriceTier::query()
            ->orderBy('min_qty')
            ->get()
            ->map(fn (PriceTier $tier) => [
                'min_qty' => (int) $tier->min_qty,
                'max_qty' => $tier->max_qty === null ? null : (int) $tier->max_qty,
                'label' => $tier->displayLabel(),
            ])
            ->unique(fn (array $column) => $column['min_qty'].'-'.($column['max_qty'] ?? 'x'))
            ->values();
    }

    /**
     * Đọc file CSV import bảng giá.
     *
     * Dòng lỗi KHÔNG chặn cả file: các dòng hợp lệ vẫn được ghi vào DB,
     * danh sách lỗi được trả về để Admin sửa.
     *
     * @return array{imported: int, updated: int, errors: array<int, array{line: int, message: string}>}
     */
    public function importCsv(string $path): array
    {
        $reader = Reader::createFromPath($path, 'r');
        $reader->setHeaderOffset(0);

        $skus = Sku::query()->pluck('id', 'sku');

        $imported = 0;
        $updated = 0;
        $errors = [];
        $line = 1;

        foreach ($reader->getRecords() as $record) {
            $line++;

            $sku = trim((string) ($record['sku'] ?? ''));
            $minQty = trim((string) ($record['min_qty'] ?? ''));
            $maxQty = trim((string) ($record['max_qty'] ?? ''));
            $unitPrice = trim((string) ($record['unit_price'] ?? ''));
            $label = trim((string) ($record['label'] ?? ''));

            if ($sku === '' && $minQty === '' && $unitPrice === '') {
                continue; // dòng trống
            }

            if ($sku === '') {
                $errors[] = ['line' => $line, 'message' => 'Thiếu SKU.'];

                continue;
            }

            if (! $skus->has($sku)) {
                $errors[] = ['line' => $line, 'message' => 'SKU "'.$sku.'" không tồn tại trong hệ thống.'];

                continue;
            }

            if (! is_numeric($minQty) || (int) $minQty < 1) {
                $errors[] = ['line' => $line, 'message' => 'SL yêu cầu (min_qty) phải là số >= 1.'];

                continue;
            }

            $maxQtyValue = null;

            if ($maxQty !== '') {
                if (! is_numeric($maxQty) || (int) $maxQty < (int) $minQty) {
                    $errors[] = ['line' => $line, 'message' => 'SL tối đa (max_qty) phải lớn hơn hoặc bằng SL yêu cầu.'];

                    continue;
                }

                $maxQtyValue = (int) $maxQty;
            }

            if (! is_numeric($unitPrice) || (float) $unitPrice < 0) {
                $errors[] = ['line' => $line, 'message' => 'Đơn giá không hợp lệ.'];

                continue;
            }

            $skuId = (int) $skus->get($sku);

            $existing = PriceTier::where('sku_id', $skuId)
                ->where('min_qty', (int) $minQty)
                ->first();

            $attributes = [
                'sku_id' => $skuId,
                'min_qty' => (int) $minQty,
                'max_qty' => $maxQtyValue,
                'unit_price' => round((float) $unitPrice, 2),
                'label' => $label !== '' ? $label : null,
            ];

            if ($existing) {
                $existing->forceFill($attributes)->save();
                $updated++;

                continue;
            }

            PriceTier::create($attributes + ['sort_order' => (int) $minQty]);
            $imported++;
        }

        return compact('imported', 'updated', 'errors');
    }

    /**
     * Xuất toàn bộ bảng giá hiện tại ra file CSV (mở được bằng Excel).
     */
    public function exportCsv(): string
    {
        $path = storage_path('app/price-list-export-'.now()->format('YmdHis').'.csv');

        $writer = Writer::createFromPath($path, 'w+');
        $writer->insertOne(self::CSV_HEADERS);

        $skus = $this->querySkus();

        foreach ($skus as $sku) {
            foreach ($sku->priceTiers as $tier) {
                $writer->insertOne([
                    (string) $sku->sku,
                    (string) $tier->min_qty,
                    $tier->max_qty === null ? '' : (string) $tier->max_qty,
                    (string) $tier->unit_price,
                    (string) ($tier->label ?? ''),
                ]);
            }
        }

        return $path;
    }

    /**
     * Nội dung file CSV mẫu để Admin tải về và điền.
     */
    public function importTemplateCsv(): string
    {
        $path = storage_path('app/price-list-template.csv');

        $writer = Writer::createFromPath($path, 'w+');
        $writer->insertOne(self::CSV_HEADERS);

        $rows = PriceTier::query()
            ->with('sku')
            ->orderBy('sku')
            ->orderBy('min_qty')
            ->limit(20)
            ->get();

        foreach ($rows as $tier) {
            $writer->insertOne([
                (string) ($tier->sku?->sku ?? ''),
                (string) $tier->min_qty,
                $tier->max_qty === null ? '' : (string) $tier->max_qty,
                (string) $tier->unit_price,
                (string) ($tier->label ?? ''),
            ]);
        }

        return $path;
    }

    /**
     * Chuẩn hóa dữ liệu bậc giá trước khi lưu.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeTier(array $data): array
    {
        $minQty = max(1, (int) ($data['min_qty'] ?? 1));
        $maxQty = isset($data['max_qty']) && $data['max_qty'] !== '' && $data['max_qty'] !== null
            ? (int) $data['max_qty']
            : null;

        if ($maxQty !== null && $maxQty < $minQty) {
            throw new BusinessRuleException('SL tối đa phải lớn hơn hoặc bằng SL yêu cầu.');
        }

        $unitPrice = (float) ($data['unit_price'] ?? 0);

        if ($unitPrice < 0) {
            throw new BusinessRuleException('Đơn giá không hợp lệ.');
        }

        $sku = Sku::where('sku', trim((string) ($data['sku'] ?? '')))->first();

        if (! $sku) {
            throw new BusinessRuleException('SKU không tồn tại: '.($data['sku'] ?? ''));
        }

        return [
            'sku_id' => $sku->id,
            'min_qty' => $minQty,
            'max_qty' => $maxQty,
            'unit_price' => round($unitPrice, 2),
            'label' => trim((string) ($data['label'] ?? '')) ?: null,
            'sort_order' => $minQty,
        ];
    }

    /**
     * Danh sách danh mục có sản phẩm (dùng cho bộ lọc).
     */
    public function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * Chuẩn hóa tham số lọc từ request.
     *
     * @return array<string, mixed>
     */
    public function filtersFromRequest(Request $request): array
    {
        return [
            'category' => (string) $request->query('category', ''),
            'search' => (string) $request->query('search', ''),
        ];
    }
}
