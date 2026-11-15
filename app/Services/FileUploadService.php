<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lưu file tải lên an toàn.
 *
 * Nguyên tắc:
 * 1. Chỉ chấp nhận định dạng trong whitelist.
 * 2. Kiểm tra mime THẬT của file, không tin đuôi file (chặn .php đổi đuôi).
 * 3. Tên file trên đĩa là random => chặn path traversal & ghi đè.
 */
class FileUploadService
{
    /**
     * Định dạng được phép tải lên (bảng giá).
     */
    public const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'pdf'];

    /**
     * Mime type thật được phép tương ứng.
     */
    public const ALLOWED_MIMES = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv',
        'text/plain',
        'application/pdf',
        'application/octet-stream',
    ];

    /**
     * Giới hạn dung lượng mỗi file (KB).
     */
    public const MAX_KB = 40 * 1024;

    /**
     * Lưu một file và trả về thông tin để lưu vào database.
     *
     * @return array{original_name: string, path: string, mime_type: string, size: int}
     */
    public function store(UploadedFile $file, string $folder): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BusinessRuleException(
                'Định dạng .'.$extension.' không được phép tải lên. '
                .'Chỉ nhận: '.implode(', ', self::ALLOWED_EXTENSIONS).'.'
            );
        }

        // --- Kiểm tra mime THẬT (không tin đuôi file) ---
        $mimeType = $file->getMimeType();

        if (! in_array($mimeType, self::ALLOWED_MIMES, true)) {
            throw new BusinessRuleException(
                'Nội dung file không hợp lệ (định dạng thực: '.$mimeType.'). '
                .'Vui lòng tải lại đúng định dạng.'
            );
        }

        // --- Kiểm tra dung lượng thật ---
        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw new BusinessRuleException('File tải lên rỗng hoặc không đọc được.');
        }

        if ($size > self::MAX_KB * 1024) {
            throw new BusinessRuleException('Mỗi file tối đa 40MB.');
        }

        // --- Tên random: chặn path traversal + ghi đè ---
        $name = Str::uuid().'.'.$extension;

        // Đảm bảo thư mục đích tồn tại VÀ ghi được.
        // Apache (XAMPP) chạy user khác (daemon) nên thư mục do CLI tạo
        // có thể không ghi được cho web server.
        $this->ensureWritableDirectory($folder);

        try {
            $path = $file->storeAs($folder, $name, 'public');
        } catch (\Throwable $e) {
            Log::error('Lưu file báo giá thất bại: '.$e->getMessage(), [
                'folder' => $folder,
                'extension' => $extension,
                'size' => $size,
            ]);

            throw new BusinessRuleException('Không lưu được file lên máy chủ: '.$e->getMessage());
        }

        if (! $path) {
            Log::error('Lưu file báo giá thất bại: storeAs trả về rỗng', [
                'folder' => $folder,
                'extension' => $extension,
                'size' => $size,
                'disk_root' => Storage::disk('public')->path(''),
            ]);

            throw new BusinessRuleException('Không lưu được file lên máy chủ.');
        }

        return [
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }

    /**
     * Tạo thư mục đích nếu chưa có và bảo đảm web server (Apache) ghi được.
     *
     * XAMPP chạy Apache bằng user "daemon" trong khi thư mục được tạo bởi
     * CLI (user hiện tại) với quyền 755 => daemon không ghi được.
     */
    protected function ensureWritableDirectory(string $folder): void
    {
        $directory = Storage::disk('public')->path($folder);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0775, true);
        }

        if (! is_writable($directory)) {
            @chmod($directory, 0777);
        }
    }

    /**
     * Xoá file trên disk (bỏ qua nếu không tồn tại).
     */
    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        if (File::exists(storage_path('app/public/'.$path))) {
            File::delete(storage_path('app/public/'.$path));
        }
    }
}
