<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tin nhắn trong khung chat hỗ trợ.
 *
 * LƯU Ý BẢO MẬT: `body` KHÔNG BAO GIỜ được chứa mật khẩu plaintext.
 * Khi admin đặt lại mật khẩu, hệ thống chỉ ghi "Đã đặt lại mật khẩu
 * thành công" — mật khẩu thật trả về 1 lần qua response và không lưu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_request_id')
                ->constrained('support_requests')
                ->cascadeOnDelete();

            // customer | admin
            $table->string('sender_type', 20);
            $table->string('sender_name', 255)->nullable();

            $table->text('body');

            // Admin đã đọc (dùng cho badge "chưa đọc").
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['support_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
