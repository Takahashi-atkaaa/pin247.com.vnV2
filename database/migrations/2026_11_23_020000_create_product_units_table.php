<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GĐ2 — Đơn vị tính (UoM) theo cơ chế Haravan.
     *
     * Haravan: mỗi biến thể có thể có nhiều đơn vị tính:
     *   - "đơn vị cơ bản" (base) — giá sản phẩm nhập là giá của đơn vị này
     *   - các "đơn vị quy đổi" — có `conversion_rate`, giá TỰ ĐỘNG = giá cơ bản × rate
     *   - mỗi đơn vị có barcode riêng + cờ "bán hàng" (is_sellable)
     *
     * Quy tắc Haravan được giữ nguyên:
     *   - `conversion_rate` KHÔNG sửa được sau khi tạo (chỉ thêm mới).
     *   - Website chỉ hiển thị đơn vị cơ bản.
     *
     * Bảng `product_units` gắn với `product_variants` (đơn vị tính là thuộc
     * biến thể trong Haravan, không thuộc sản phẩm).
     */
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_variant_id');

            // Tên đơn vị: "cái", "hộp", "lô", "thùng".
            $table->string('name');

            // Tỷ lệ quy đổi so với đơn vị cơ bản.
            // 1 = đơn vị cơ bản; 12 = 1 thùng = 12 cái.
            $table->unsignedInteger('conversion_rate')->default(1);

            // Đơn vị cơ bản của biến thể (chỉ đúng 1 đơn vị có rate = 1).
            $table->boolean('is_base')->default(false);

            // Giá bán riêng cho đơn vị này (nếu NULL => tự tính từ giá cơ bản).
            $table->decimal('price', 15, 2)->nullable();

            // Đơn vị này có được bán trên website không.
            $table->boolean('is_sellable')->default(true);

            // Barcode riêng cho đơn vị (quét nhanh khi bán).
            $table->string('barcode')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Cột sinh: 1 khi là đơn vị cơ bản, NULL khi không.
            // Unique index trên (product_variant_id, is_base_key) => mỗi biến
            // thể chỉ có tối đa 1 đơn vị cơ bản, nhưng vẫn cho phép nhiều
            // đơn vị quy đổi (NULL không bị tính trong unique index).
            $table->unsignedTinyInteger('is_base_key')
                ->nullable()
                ->storedAs('CASE WHEN is_base = 1 THEN 1 ELSE NULL END');

            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();

            // Mỗi biến thể chỉ có 1 đơn vị cơ bản.
            //
            // Unique trên (product_variant_id, is_base) KHÔNG dùng được vì
            // nhiều đơn vị quy đổi đều có is_base = 0 → vi phạm unique.
            // Vì vậy dùng cột sinh: `is_base_key` = 1 khi is_base, NULL khi
            // không — NULL không bị tính trong unique index (MySQL/SQLite đều
            // cho phép nhiều NULL trong unique index).
            $table->index(['product_variant_id', 'is_base']);
            $table->index('barcode');
        });

        // Unique: mỗi biến thể chỉ có tối đa 1 đơn vị cơ bản.
        Schema::table('product_units', function (Blueprint $table): void {
            $table->unique(['product_variant_id', 'is_base_key'], 'product_units_unique_base_per_variant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
