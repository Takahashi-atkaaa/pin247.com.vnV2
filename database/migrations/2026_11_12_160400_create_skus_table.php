<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng SKU — đơn vị tồn kho (stock keeping unit).
     *
     * SKU là mã NGHIỆP VỤ, KHÔNG dùng làm khóa chính của database.
     * Khoá chính vẫn là `id` (bigint auto increment); `sku` có unique constraint.
     */
    public function up(): void
    {
        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();

            // Mã nghiệp vụ, khóa đối chiếu với BigSeller. Bắt buộc unique.
            $table->string('sku')->unique();

            $table->string('name')->nullable();
            $table->enum('source', ['MANUAL', 'BIGSELLER', 'IMPORTED'])->default('MANUAL');

            // Tồn kho khả dụng hiện tại trên website (snapshot gần nhất từ BigSeller)
            $table->integer('available_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('on_hand_stock')->default(0);
            $table->integer('locked_stock')->default(0);
            $table->integer('shipped_stock')->default(0);

            // Tồn kho an toàn: website không bán vượt quá (available - safety_stock)
            $table->integer('safety_stock')->default(0);

            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->nullable();

            $table->enum('sync_state', ['PENDING', 'SYNCING', 'SYNCED', 'FAILED'])->default('PENDING');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_error', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('sync_state');
            $table->index('available_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skus');
    }
};
