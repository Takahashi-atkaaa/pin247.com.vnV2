<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ánh xạ đơn hàng website -> đơn hàng BigSeller.
     *
     * Đây là bảng CHỐNG TẠO ĐƠN TRÙNG:
     * - `idempotency_key` unique: mỗi lần đẩy đơn dùng một key xác định.
     * - `bigseller_order_code` unique (nullable): một mã BigSeller chỉ gắn với một đơn.
     */
    public function up(): void
    {
        Schema::create('bigseller_order_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('idempotency_key')->unique();
            $table->string('bigseller_order_code')->nullable()->unique();
            $table->string('bigseller_shop_code')->nullable();
            $table->string('bigseller_status')->nullable()->comment('Trạng thái nguyên bản từ BigSeller');
            $table->string('mapped_status')->nullable()->comment('Trạng thái nội bộ sau khi ánh xạ');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index('order_id');
            $table->index('bigseller_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bigseller_order_mappings');
    }
};
