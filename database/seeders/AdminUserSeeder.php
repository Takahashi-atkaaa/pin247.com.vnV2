<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Tài khoản quản trị mặc định.
     *
     * Mật khẩu mặc định: "admin1234" — PHẢI đổi khi triển khai production.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@pin247.vn'],
            [
                'name' => 'Quản trị PIN247',
                'password' => Hash::make('admin1234'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
