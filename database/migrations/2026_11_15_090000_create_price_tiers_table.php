<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();

            // SKU là đơn vị bán — mỗi SKU có một bảng giá bậc thang riêng.
            $table->unsignedBigInteger('sku_id');

            // Số lượng yêu cầu tối thiểu để áp dụng bậc giá này.
            $table->unsignedInteger('min_qty');

            // Số lượng tối đa của bậc. NULL = không giới hạn ("trở lên").
            $table->unsignedInteger('max_qty')->nullable();

            // Đơn giá ứng dụng cho bậc này.
            $table->decimal('unit_price', 15, 2);

            // Nhãn hiển thị trên bảng giá, ví dụ: "Lô 50-199".
            $table->string('label')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();

            // Không cho trùng mốc bắt đầu trong cùng một SKU.
            $table->unique(['sku_id', 'min_qty']);
            $table->index(['sku_id', 'min_qty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_tiers');
    }
};
