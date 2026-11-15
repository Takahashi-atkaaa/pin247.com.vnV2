<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->comment('Mã nghiệp vụ của sản phẩm cha, có thể trùng nếu sản phẩm có nhiều biến thể');
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->longText('specifications')->nullable()->comment('JSON thông số kỹ thuật');
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable()->comment('Giá vốn, dùng để tính giá trị tồn kho');
            $table->string('image')->nullable();
            $table->enum('source', ['MANUAL', 'BIGSELLER', 'IMPORTED'])->default('MANUAL');
            $table->boolean('has_variants')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false)->comment('Sản phẩm nổi bật');
            $table->boolean('is_new')->default(false)->comment('Sản phẩm mới');
            $table->unsignedInteger('sales_count')->default(0)->comment('Số lượng đã bán, dùng xếp hạng bán chạy');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index('category_id');
            $table->index('sku');
            $table->index('source');
            $table->index(['is_active', 'is_featured']);
            $table->index('is_new');
            $table->index('sales_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
