<?php

namespace App\Http\Resources;

use App\Models\PriceListDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceListDocument
 */
class PriceListDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'size_label' => $this->humanSize(),
            'is_published' => (bool) $this->is_published,

            // Đường dẫn TƯƠNG ĐỐI — frontend tự ghép APP_BASE.
            // Nhờ vậy không phụ thuộc APP_URL (XAMPP thường chạy ở /duanma).
            'download_path' => 'api/price-list/documents/'.$this->id.'/download',
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
