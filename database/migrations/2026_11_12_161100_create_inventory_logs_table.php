<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lịch sử thay đổi tồn kho giữa hai lần đồng bộ.
     */
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sku_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('sku');
            $table->integer('previous_available')->default(0);
            $table->integer('new_available')->default(0);
            $table->integer('delta')->default(0);
            $table->string('reason')->nullable()->comment('Ví dụ: sync_bigseller, order_placed, manual_adjust');
            $table->string('sync_run_id', 64)->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->index(['sku_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
