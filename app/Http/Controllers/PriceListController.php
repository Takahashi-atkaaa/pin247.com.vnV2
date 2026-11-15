<?php

namespace App\Http\Controllers;

use App\Http\Resources\PriceListDocumentResource;
use App\Http\Resources\PriceListResource;
use App\Models\PriceListDocument;
use App\Services\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bảng giá — PUBLIC, không yêu cầu đăng nhập.
 */
class PriceListController extends Controller
{
    public function __construct(
        protected PriceListService $priceListService,
    ) {
    }

    /**
     * Danh sách bảng giá theo bậc số lượng yêu cầu.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $this->priceListService->filtersFromRequest($request);

        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));
        $page = max(1, (int) $request->query('page', 1));

        $paginator = $this->priceListService->paginateSkus($filters, $perPage, $page);

        return PriceListResource::collection($paginator)->additional([
            // Các mốc bậc giá dùng để dựng header cột động của bảng.
            'tier_columns' => $this->priceListService->tierColumns()->all(),
        ]);
    }

    /**
     * Các file bảng giá đã publish (khách tải về).
     */
    public function documents(): AnonymousResourceCollection
    {
        $documents = PriceListDocument::query()
            ->where('is_published', true)
            ->orderByDesc('id')
            ->get();

        return PriceListDocumentResource::collection($documents);
    }

    /**
     * Tải file bảng giá.
     */
    public function download(PriceListDocument $document): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($document->is_published, 404);

        $path = storage_path('app/public/'.$document->path);

        abort_unless(is_file($path), 404);

        return response()->download($path, $document->original_name);
    }
}
