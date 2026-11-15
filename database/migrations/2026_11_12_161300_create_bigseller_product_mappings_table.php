<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ánh xạ sản phẩm/SKU của website sang BigSeller.
     *
     * Đối chiếu chính theo SKU. Không tạo bản ghi trùng SKU.
     */
    public function up(): void
    {
        Schema::create('bigseller_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('sku_id')->nullable();

            // SKU phía BigSeller — khóa đối chiếu
            $table->string('bigseller_sku')->index();
            $table->unsignedBigInteger('bigseller_product_id')->nullable()->index();
            $table->unsignedBigInteger('bigseller_variant_id')->nullable()->index();

            $table->string('product_name')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->json('attributes')->nullable()->comment('Thuộc tính biến thể');

            $table->enum('sync_state', ['PENDING', 'SYNCING', 'SYNCED', 'FAILED'])->default('PENDING');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_error', 500)->nullable();

            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();

            // Một SKU chỉ có một mapping — chống tạo trùng
            $table->unique('bigseller_sku');
            $table->index(['product_id', 'product_variant_id']);
            $table->index('sync_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bigseller_product_mappings');
    }
};
