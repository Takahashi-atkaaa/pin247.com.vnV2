<?php

namespace App\Enums;

/**
 * Trạng thái đồng bộ đơn hàng website -> BigSeller.
 *
 * Mỗi trạng thái trong danh sách dưới đây là trạng thái NỘI BỘ của website.
 * Trạng thái trả về từ BigSeller phải được ánh xạ (map) sang các trạng thái
 * này qua BigSellerStatusMapper — không được giả định chúng trùng nhau.
 */
enum OrderSyncStatus: string
{
    /** Đơn đã tạo nội bộ, chưa gửi lên BigSeller. */
    case PendingSync = 'PENDING_SYNC';

    /** Đang trong lúc gửi lên BigSeller (worker đang xử lý). */
    case Syncing = 'SYNCING';

    /** BigSeller đã nhận và trả về mã đơn hàng. */
    case Synced = 'SYNCED';

    /** Gửi lên BigSeller thất bại. */
    case SyncFailed = 'SYNC_FAILED';

    /** Đã yêu cầu hủy, đang chờ BigSeller xác nhận. */
    case CancelPending = 'CANCEL_PENDING';

    /** Đơn đã được hủy thành công (BigSeller xác nhận). */
    case Cancelled = 'CANCELLED';

    /** BigSeller đã tiếp nhận và đang xử lý (đơn thủ công trong quy trình). */
    case Processing = 'PROCESSING';

    /** Đã gửi hàng. */
    case Shipped = 'SHIPPED';

    /** Hoàn tất. */
    case Completed = 'COMPLETED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Trạng thái cho phép chuyển tiếp hợp lệ.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingSync => [self::Syncing, self::SyncFailed, self::Cancelled],
            self::Syncing => [self::Synced, self::SyncFailed, self::PendingSync],
            self::SyncFailed => [self::PendingSync, self::Syncing, self::Cancelled],
            self::Synced => [self::Processing, self::Shipped, self::Completed, self::CancelPending, self::SyncFailed],
            self::CancelPending => [self::Cancelled, self::Synced, self::SyncFailed],
            self::Cancelled => [],
            self::Processing => [self::Shipped, self::Completed, self::CancelPending],
            self::Shipped => [self::Completed, self::CancelPending],
            self::Completed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Đơn có cần retry/push lại lên BigSeller hay không.
     */
    public function requiresPush(): bool
    {
        return in_array($this, [self::PendingSync, self::SyncFailed], true);
    }

    /**
     * Nhãn tiếng Việt để hiển thị trên giao diện.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingSync => 'Chờ đồng bộ',
            self::Syncing => 'Đang đồng bộ',
            self::Synced => 'Đã đồng bộ',
            self::SyncFailed => 'Đồng bộ lỗi',
            self::CancelPending => 'Chờ hủy',
            self::Cancelled => 'Đã hủy',
            self::Processing => 'Đang xử lý',
            self::Shipped => 'Đã gửi hàng',
            self::Completed => 'Hoàn tất',
        };
    }
}
