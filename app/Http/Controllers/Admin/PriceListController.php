<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PriceListDocumentResource;
use App\Http\Resources\PriceTierResource;
use App\Models\PriceListDocument;
use App\Models\PriceTier;
use App\Services\FileUploadService;
use App\Services\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PriceListController extends Controller
{
    public function __construct(
        protected PriceListService $priceListService,
        protected FileUploadService $fileUploadService,
    ) {
    }

    /**
     * Danh sách bậc giá (dạng phẳng, mỗi dòng là 1 bậc).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $this->priceListService->filtersFromRequest($request);

        $query = PriceTier::query()->with(['sku.product', 'sku.variant']);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->whereHas('sku', fn ($q) => $q
                ->where('sku', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")));
        }

        if ($skuCode = trim((string) $request->query('sku', ''))) {
            $query->whereHas('sku', fn ($q) => $q->where('sku', $skuCode));
        }

        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));

        return PriceTierResource::collection(
            $query->orderBy('sku_id')->orderBy('min_qty')->paginate($perPage)->withQueryString()
        );
    }

    /**
     * Thêm bậc giá.
     */
    public function storeTier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'min_qty' => ['required', 'integer', 'min:1'],
            'max_qty' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $attributes = $this->priceListService->normalizeTier($validated);
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $exists = PriceTier::where('sku_id', $attributes['sku_id'])
            ->where('min_qty', $attributes['min_qty'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'SKU này đã có bậc giá bắt đầu từ số lượng '.$attributes['min_qty'].'.',
            ], 422);
        }

        $tier = PriceTier::create($attributes);

        return response()->json([
            'message' => 'Đã thêm bậc giá.',
            'data' => new PriceTierResource($tier->load('sku.product')),
        ], 201);
    }

    /**
     * Cập nhật bậc giá.
     */
    public function updateTier(Request $request, PriceTier $tier): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['sometimes', 'string', 'max:100'],
            'min_qty' => ['sometimes', 'integer', 'min:1'],
            'max_qty' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $sku = $tier->sku;

        $payload = array_merge([
            'sku' => $sku?->sku,
            'min_qty' => $tier->min_qty,
            'max_qty' => $tier->max_qty,
            'unit_price' => $tier->unit_price,
            'label' => $tier->label,
        ], $validated);

        try {
            $attributes = $this->priceListService->normalizeTier($payload);
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $duplicate = PriceTier::where('sku_id', $attributes['sku_id'])
            ->where('min_qty', $attributes['min_qty'])
            ->whereKeyNot($tier->id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'SKU này đã có bậc giá bắt đầu từ số lượng '.$attributes['min_qty'].'.',
            ], 422);
        }

        $tier->forceFill($attributes)->save();

        return response()->json([
            'message' => 'Đã cập nhật bậc giá.',
            'data' => new PriceTierResource($tier->fresh('sku.product')),
        ]);
    }

    /**
     * Xoá bậc giá.
     */
    public function destroyTier(PriceTier $tier): JsonResponse
    {
        $tier->delete();

        return response()->json(['message' => 'Đã xoá bậc giá.']);
    }

    /**
     * Nhập bảng giá từ file CSV.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:40960'],
        ]);

        $path = $request->file('file')->getRealPath();

        $result = $this->priceListService->importCsv($path);

        return response()->json([
            'message' => 'Đã nhập bảng giá.',
            'data' => $result,
        ]);
    }

    /**
     * Tải file CSV mẫu.
     */
    public function importTemplate(): BinaryFileResponse
    {
        $path = $this->priceListService->importTemplateCsv();

        return response()->download($path, 'mau-bang-gia.csv');
    }

    /**
     * Xuất toàn bộ bảng giá hiện tại ra CSV.
     */
    public function export(): BinaryFileResponse
    {
        $path = $this->priceListService->exportCsv();

        return response()->download($path, 'bang-gia-pin247-'.now()->format('Ymd').'.csv');
    }

    /**
     * Danh sách file bảng giá đã đăng.
     */
    public function documents(): AnonymousResourceCollection
    {
        $documents = PriceListDocument::query()->orderByDesc('id')->get();

        return PriceListDocumentResource::collection($documents);
    }

    /**
     * Đăng file bảng giá.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_published' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'max:40960'],
        ]);

        try {
            $stored = $this->fileUploadService->store(
                $request->file('file'),
                'price-lists'
            );
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $document = PriceListDocument::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_published' => $request->boolean('is_published', true),
        ] + $stored);

        return response()->json([
            'message' => 'Đã đăng file bảng giá.',
            'data' => new PriceListDocumentResource($document),
        ], 201);
    }

    /**
     * Tải file bảng giá (Admin — kể cả file đang ẩn).
     */
    public function downloadDocument(PriceListDocument $document): BinaryFileResponse
    {
        $path = storage_path('app/public/'.$document->path);

        abort_unless(is_file($path), 404);

        return response()->download($path, $document->original_name);
    }

    /**
     * Cập nhật thông tin file bảng giá (tiêu đề, trạng thái publish).
     */
    public function updateDocument(Request $request, PriceListDocument $document): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $document->fill($validated)->save();

        return response()->json([
            'message' => 'Đã cập nhật file bảng giá.',
            'data' => new PriceListDocumentResource($document),
        ]);
    }

    /**
     * Xoá file bảng giá.
     */
    public function destroyDocument(PriceListDocument $document): JsonResponse
    {
        $document->deleteFile();
        $document->delete();

        return response()->json(['message' => 'Đã xoá file bảng giá.']);
    }
}
