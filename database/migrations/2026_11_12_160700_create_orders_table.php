<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Mã đơn hàng NỘI BỘ của website. Duy nhất, dùng để tra cứu & đối chiếu.
            $table->string('order_code')->unique();

            // Mã đơn hàng BigSeller trả về. Có thể null (chưa đồng bộ xong).
            $table->string('bigseller_order_code')->nullable()->index();

            // Idempotency key dùng để chống tạo đơn trùng khi retry.
            $table->string('idempotency_key')->unique();

            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            // --- Thông tin người nhận (snapshot tại thời điểm đặt hàng) ---
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();
            $table->string('shipping_address');
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->text('note')->nullable();
            $table->text('internal_note')->nullable()->comment('Ghi chú nội bộ, Admin chỉ');

            // --- Tiền ---
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // --- Trạng thái ---
            $table->enum('sync_status', [
                'PENDING_SYNC', 'SYNCING', 'SYNCED', 'SYNC_FAILED', 'CANCEL_PENDING',
                'CANCELLED', 'PROCESSING', 'SHIPPED', 'COMPLETED',
            ])->default('PENDING_SYNC')->index();

            $table->enum('payment_method', ['COD', 'BANK_TRANSFER'])->default('COD');
            $table->enum('payment_status', [
                'UNPAID', 'PENDING', 'PAID', 'FAILED', 'REFUNDED', 'PARTIALLY_REFUNDED',
            ])->default('UNPAID')->index();

            $table->string('shipping_status')->nullable()->comment('Trạng thái giao hàng lưu riêng');
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();

            // Số lần thử đẩy lên BigSeller — dùng cho retry an toàn
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->timestamp('last_sync_attempt_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_error', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('customer_phone');
            $table->index('created_at');
            $table->index(['sync_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
