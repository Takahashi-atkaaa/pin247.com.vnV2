<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bigseller_warehouse_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->string('bigseller_warehouse_code')->index();
            $table->unsignedBigInteger('bigseller_warehouse_id')->nullable()->index();
            $table->string('bigseller_warehouse_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            // Tên index tường minh: tên mặc định của MySQL vượt quá giới hạn 64 ký tự.
            $table->unique(['warehouse_id', 'bigseller_warehouse_code'], 'bs_wh_map_wh_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bigseller_warehouse_mappings');
    }
};
