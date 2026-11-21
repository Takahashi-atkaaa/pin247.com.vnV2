<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yêu cầu hỗ trợ từ khách hàng (chat với admin).
 *
 * Khách KHÔNG cần đăng nhập để tạo yêu cầu. Họ dùng `public_token`
 * (uuid) làm "phiên chat" riêng tư — không dùng id tự tăng để tránh bị
 * dò/enum request của người khác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();

            // Token công khai để khách đọc lại chat của mình.
            $table->uuid('public_token')->unique();

            // Liên kết tài khoản — biết sau khi admin tìm được khách.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Thông tin người gửi (khách có thể gõ sai, admin tự đối chiếu).
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();

            $table->text('message');

            // open | resolved | closed
            $table->string('status', 20)->default('open')->index();

            // Loại yêu cầu (mặc định: quên mật khẩu).
            $table->string('topic', 50)->default('forgot_password')->index();

            // Admin xử lý lúc nào.
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
