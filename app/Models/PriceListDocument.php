<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * File bảng giá để khách tải về (Excel / CSV / PDF).
 */
class PriceListDocument extends Model
{
    protected $fillable = [
        'title',
        'description',
        'original_name',
        'path',
        'mime_type',
        'size',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Đường dẫn URL công khai để tải file.
     */
    public function downloadUrl(): string
    {
        return asset('storage/'.$this->path);
    }

    /**
     * Đường dẫn tải file — tự ghép APP_BASE phía frontend.
     */
    public function downloadPath(): string
    {
        return 'api/price-list/documents/'.$this->id.'/download';
    }

    /**
     * Dung lượng hiển thị cho người dùng.
     */
    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }

    /**
     * Xoá file vật lý trên disk.
     */
    public function deleteFile(): void
    {
        if (filled($this->path)) {
            Storage::disk('public')->delete($this->path);
        }
    }
}
