<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tách lượng tồn kho do WEBSITE giữ (đơn chưa được BigSeller xác nhận) khỏi
 * lượng `reserved_stock` do BigSeller báo về.
 *
 * Lý do:
 * - `reserved_stock` là số BigSeller đang giữ cho đơn của chính BigSeller.
 *   BigSeller đã trừ số này khỏi `available` trước khi trả về -> nếu trừ
 *   thêm lần nữa sẽ tính đôi.
 * - `local_reserved_stock` là số website tự giữ cho đơn nội bộ, chỉ được
 *   giải phóng khi đơn bị hủy / BigSeller xác nhận thành công.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->integer('local_reserved_stock')->default(0)->after('reserved_stock');
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn('local_reserved_stock');
        });
    }
};
