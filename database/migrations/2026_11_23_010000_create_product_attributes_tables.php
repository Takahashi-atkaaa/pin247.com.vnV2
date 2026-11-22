<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GĐ1 — Thuộc tính động & biến thể tự sinh (clone cơ chế Haravan).
     *
     * Theo Haravan: biến thể là kết quả ghép (cartesian product) của các
     * giá trị thuộc tính. Mỗi sản phẩm tối đa 3 thuộc tính.
     *
     * Bảng `product_attributes`         — thuộc tính của sản phẩm (VD: "Kiểu đầu")
     * Bảng `product_attribute_values`   — giá trị của thuộc tính (VD: "Makita")
     *
     * Bảng `product_variants` được bổ sung:
     *   - `option_3_name/value`  → thuộc tính thứ 3 (Haravan cho tối đa 3)
     *   - `attribute_signature`  → chữ ký "value1|value2|value3" để đối chiếu
     *                                nhanh giữa các lần đồng bộ
     */
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');

            // Tên thuộc tính, VD: "Kiểu đầu", "Dung lượng", "Màu sắc".
            $table->string('name');

            // Vị trí hiển thị (1..3), giữ đúng thứ tự như Haravan.
            $table->unsignedTinyInteger('position')->default(1);

            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'position']);
            $table->index('product_id');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_attribute_id');

            // Giá trị của thuộc tính, VD: "Makita", "2.0Ah", "Đỏ".
            $table->string('value');

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('product_attribute_id')->references('id')->on('product_attributes')->cascadeOnDelete();
            $table->unique(['product_attribute_id', 'value']);
            $table->index('product_attribute_id');
        });

        // Bổ sung thuộc tính thứ 3 + chữ ký đối chiếu cho biến thể.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('option_3_name')->nullable()->after('option_2_value');
            $table->string('option_3_value')->nullable()->after('option_3_name');

            // Chữ ký "value1|value2|value3" (NULL thành chuỗi rỗng).
            // Dùng để tìm nhanh biến thể tương ứng khi tự sinh lại từ
            // thuộc tính, thay vì so khớp từng option rời rạc.
            $table->string('attribute_signature')->nullable()->after('option_3_value');

            $table->index('attribute_signature');
        });

        // Tự sinh biến thể từ thuộc tính: tìm theo (product_id, signature).
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'attribute_signature'], 'product_variants_product_signature_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_product_signature_index');
            $table->dropIndex(['attribute_signature']);
            $table->dropColumn(['option_3_name', 'option_3_value', 'attribute_signature']);
        });

        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
    }
};
