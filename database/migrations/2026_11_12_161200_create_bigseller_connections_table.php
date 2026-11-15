<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kết nối BigSeller của website.
     *
     * Lưu ý bảo mật:
     * - access_token / app_key KHÔNG BAO GIỜ được serialize ra API cho Admin UI.
     * - Dùng `encrypted` cast để mã hoá at-rest (APP_KEY).
     * - Không đưa credentials vào frontend.
     */
    public function up(): void
    {
        Schema::create('bigseller_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('BigSeller chính');
            $table->string('app_id')->nullable()->index();
            $table->text('app_key')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('capabilities')->nullable()->comment('Danh sách API được cấp quyền');

            // Gian hàng trên BigSeller dùng để đẩy đơn
            $table->string('shop_code')->nullable()->comment('Mã gian hàng BigSeller');
            $table->string('shop_name')->nullable();
            $table->unsignedBigInteger('default_warehouse_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->boolean('is_mock')->default(false)->comment('True = đang dùng mock client, KHÔNG phải production');
            $table->timestamps();

            $table->foreign('default_warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bigseller_connections');
    }
};
