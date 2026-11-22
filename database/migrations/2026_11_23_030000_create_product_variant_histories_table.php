<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GĐ3 — Lịch sử cập nhật biến thể.
     *
     * Theo Haravan: tại màn "Chi tiết biến thể" có mục "Lịch sử cập nhật" để
     * xem lại các thay đổi về giá bán, giá so sánh, SKU, tồn kho.
     *
     * Bảng này ghi lại MỌI thay đổi có ý nghĩa đối với biến thể:
     *   - đổi giá bán / giá so sánh / giá vốn
     *   - đổi SKU
     *   - bật/tắt biến thể
     *   - đổi ảnh
     *
     * Mỗi dòng lưu snapshot của các trường trước và sau khi đổi.
     */
    public function up(): void
    {
        Schema::create('product_variant_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('product_id');

            // Người thực hiện thay đổi (admin).
            $table->unsignedBigInteger('changed_by')->nullable();

            // Danh sách trường đã đổi, lưu dạng JSON: ["price","sku"]
            $table->json('changed_fields');

            // Snapshot trước / sau.
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            // Ghi chú tự động, VD: "Giá bán: 249.000đ → 259.000đ"
            $table->string('summary', 500)->nullable();

            $table->timestamps();

            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->index(['product_variant_id', 'id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_histories');
    }
};
