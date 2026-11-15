<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('name')->comment('Ví dụ: Pin 21V 2Ah');
            $table->string('sku')->comment('SKU riêng của biến thể, khóa đối chiếu với BigSeller');
            $table->string('option_1_name')->nullable()->comment('Ví dụ: Dung tượng');
            $table->string('option_1_value')->nullable()->comment('Ví dụ: 2Ah');
            $table->string('option_2_name')->nullable();
            $table->string('option_2_value')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique('sku');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
