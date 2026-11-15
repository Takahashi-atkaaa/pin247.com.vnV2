<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('sku_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();

            // Snapshot dữ liệu tại thời điểm đặt hàng (giá/sku có thể đổi sau đó)
            $table->string('sku');
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('image')->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('price', 15, 2)->comment('Giá bán 1 đơn vị tại thời điểm đặt');
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('sku_id')->references('id')->on('skus')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->index('order_id');
            $table->index('sku');
            $table->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
