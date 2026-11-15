<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lịch sử request/response gửi tới BigSeller.
     *
     * Lưu toàn bộ lịch sử để phục vụ đối chiếu và debug. KHÔNG lưu
     * access_token / app_key trong payload.
     */
    public function up(): void
    {
        Schema::create('bigseller_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_run_id', 64)->index();
            $table->string('operation')->index()->comment('inventory | create_order | cancel_order | order_status | products');
            $table->string('entity_type')->nullable()->comment('sku | order | product');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('request_url')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['operation', 'success']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bigseller_sync_logs');
    }
};
