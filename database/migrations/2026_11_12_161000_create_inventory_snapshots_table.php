<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot tồn kho tại một thời điểm — lấy từ BigSeller.
     *
     * Giữ lại lịch sử để đối chiếu, phân tích xu hướng và điều tra sự cố.
     */
    public function up(): void
    {
        Schema::create('inventory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sku_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('sku')->index()->comment('Snapshot lưu kèm SKU để tra cứu nhanh');

            $table->integer('available')->default(0);
            $table->integer('reserved')->nullable();
            $table->integer('on_hand')->nullable();
            $table->integer('locked')->nullable();
            $table->integer('shipped')->nullable();

            $table->string('sync_run_id', 64)->index()->comment('Mã lần đồng bộ');
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->index(['sku_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_snapshots');
    }
};
