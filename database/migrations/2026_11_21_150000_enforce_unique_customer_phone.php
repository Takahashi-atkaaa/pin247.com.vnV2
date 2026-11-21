<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ràng buộc "1 Số điện thoại = 1 hồ sơ khách hàng".
 *
 * Bối cảnh:
 *  - `customers.phone` trước đây chỉ có index thường => nhiều hồ sơ được
 *    phép dùng cùng 1 SĐT => đơn hàng của tài khoản A có thể bị gắn nhầm
 *    vào hồ sơ của tài khoản B.
 *  - Chính sách mới: mỗi SĐT chỉ thuộc về một hồ sơ khách hàng.
 *
 * Cách triển khai trên MariaDB 10.4 (bản XAMPP hiện tại):
 *  - Cột generated `phone_active` = SĐT khi hồ sơ CHƯA bị xoá mềm, NULL
 *    khi đã xoá mềm => unique index vẫn cho phép tái sử dụng SĐT sau khi
 *    hồ sơ cũ được xoá mềm.
 *  - MariaDB cho phép INDEX trên cột generated (đã kiểm chứng trên server).
 *
 * Lưu ý:
 *  - `deleted_at` đã có sẵn từ migration tạo bảng `customers`.
 *  - Migration phải chạy được trên MySQL/MariaDB (production) VÀ SQLite
 *    (môi trường test) => dùng query builder thay vì raw SQL đặc thù MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Bước 1: dọn hồ sơ legacy trùng SĐT ---
        //
        // Chỉ xoá hồ sơ VÃNG LAI (`user_id IS NULL`). Hồ sơ đã gắn tài khoản
        // được giữ nguyên để không mất liên kết đơn hàng / hồ sơ.
        //
        // Điều kiện an toàn: chỉ xoá khi tồn tại hồ sơ khác CÙNG SĐT đã
        // được gắn với một tài khoản. Như vậy hồ sơ vãng lai "độc quyền"
        // (không trùng ai) vẫn được giữ nguyên.
        $this->deleteGuestCustomersConflictingWithOwned();

        // Nếu còn trường hợp 2 hồ sơ đều đã gắn tài khoản nhưng trùng SĐT
        // (dữ liệu cũ), giữ lại hồ sơ đã gắn tài khoản và gỡ `user_id` của
        // các hồ sơ còn lại để chúng trở thành hồ sơ vãng lai (không còn
        // xung đột unique index).
        $this->detachDuplicateOwnedCustomers();

        // Xoá nốt các hồ sơ vãng lai còn sót lại do trùng SĐT với hồ sơ
        // đã gắn tài khoản (sau bước trên).
        $this->deleteGuestCustomersConflictingWithOwned();

        // --- Bước 2: thêm cột generated ---
        if (! Schema::hasColumn('customers', 'phone_active')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('phone_active', 30)
                    ->storedAs('IF(`deleted_at` IS NULL, `phone`, NULL)');
            });
        }

        // --- Bước 3: unique index ---
        //
        // Tách khối vì MariaDB yêu cầu index trên cột generated được khai
        // báo tách riêng khỏi câu lệnh ADD COLUMN.
        if (! $this->hasIndex('customers_phone_active_unique')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unique('phone_active', 'customers_phone_active_unique');
            });
        }
    }

    public function down(): void
    {
        // Gỡ index unique trước rồi mới gỡ cột generated.
        if ($this->hasIndex('customers_phone_active_unique')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropUnique('customers_phone_active_unique');
            });
        }

        if (Schema::hasColumn('customers', 'phone_active')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropColumn('phone_active');
            });
        }
    }

    /**
     * Xoá hồ sơ vãng lai (`user_id IS NULL`) trùng SĐT với hồ sơ đã gắn
     * tài khoản. Hồ sơ vãng lai không trùng ai được giữ nguyên.
     */
    protected function deleteGuestCustomersConflictingWithOwned(): void
    {
        $ownedPhones = DB::table('customers')
            ->whereNull('deleted_at')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('phone');

        if ($ownedPhones->isEmpty()) {
            return;
        }

        DB::table('customers')
            ->whereNull('deleted_at')
            ->whereNull('user_id')
            ->whereIn('phone', $ownedPhones)
            ->delete();
    }

    /**
     * Xử lý trường hợp 2 hồ sơ ĐỀU đã gắn tài khoản nhưng trùng SĐT.
     *
     * Giữ hồ sơ có `id` lớn nhất (hồ sơ mới nhất), gỡ `user_id` của các
     * hồ sơ còn lại để chúng không vi phạm unique index.
     */
    protected function detachDuplicateOwnedCustomers(): void
    {
        $duplicates = DB::table('customers')
            ->whereNull('deleted_at')
            ->whereNotNull('user_id')
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        foreach ($duplicates as $phone) {
            $ids = DB::table('customers')
                ->whereNull('deleted_at')
                ->whereNotNull('user_id')
                ->where('phone', $phone)
                ->orderByDesc('id')
                ->pluck('id')
                ->all();

            // Giữ lại hồ sơ mới nhất, gỡ liên kết tài khoản của các hồ sơ cũ.
            $detach = array_slice($ids, 1);

            if ($detach !== []) {
                DB::table('customers')
                    ->whereIn('id', $detach)
                    ->update(['user_id' => null]);
            }
        }
    }

    /**
     * Kiểm tra index đã tồn tại chưa (tương thích MySQL & SQLite).
     */
    protected function hasIndex(string $name): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA index_list(customers)') as $row) {
                if (($row->name ?? null) === $name) {
                    return true;
                }
            }

            return false;
        }

        foreach (DB::select('SHOW INDEX FROM customers') as $index) {
            if ($index->Key_name === $name) {
                return true;
            }
        }

        return false;
    }
};
