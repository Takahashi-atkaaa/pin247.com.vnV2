<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_documents', function (Blueprint $table) {
            $table->id();

            // Tiêu đề hiển thị trên trang bảng giá, ví dụ "Bảng giá cell 18650 - 12/2026".
            $table->string('title');

            // Ghi chú ngắn hiển thị dưới nút tải.
            $table->text('description')->nullable();

            // Tên file gốc khi Admin tải lên (giữ để hiển thị cho người dùng).
            $table->string('original_name');

            // Đường dẫn file trên disk 'public'.
            $table->string('path');

            $table->string('mime_type', 100);

            // Dung lượng (bytes).
            $table->unsignedBigInteger('size');

            // Chỉ file publish mới hiển thị nút tải trên trang bảng giá.
            $table->boolean('is_published')->default(true);

            $table->timestamps();

            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_documents');
    }
};
