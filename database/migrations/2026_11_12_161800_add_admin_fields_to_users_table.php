<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phân quyền: user thường (khách đã đăng nhập) và admin.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->string('phone', 30)->nullable()->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('is_admin');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_admin']);
            $table->dropIndex(['phone']);
            $table->dropColumn(['is_admin', 'phone']);
        });
    }
};
