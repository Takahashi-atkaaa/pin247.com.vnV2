<?php

namespace App\Services\BigSeller;

use App\Enums\OrderSyncStatus;

/**
 * Ánh xạ trạng thái BigSeller -> trạng thái nội bộ của website.
 *
 * QUAN TRỌNG: Tên trạng thái của BigSeller KHÔNG được giả định trùng với
 * trạng thái nội bộ. Bảng ánh xạ dưới đây là nơi DUY NHẤT để khai báo
 * tương ứng, và được ghi chú rõ để cập nhật khi tài liệu chính thức
 * xác nhận danh sách trạng thái thực tế.
 *
 * Khi BigSeller trả về một trạng thái CHƯA biết, mapper sẽ:
 *   - ghi log cảnh báo,
 *   - trả về null để caller giữ nguyên trạng thái hiện tại,
 *   - KHÔNG tự đoán.
 */
class BigSellerStatusMapper
{
    /**
     * Bảng ánh xạ trạng thái BigSeller -> trạng thái nội bộ.
     *
     * @var array<string, OrderSyncStatus>
     */
    protected const MAP = [
        // --- Đơn thủ công do website tạo ---
        'PENDING' => OrderSyncStatus::PendingSync,
        'PENDING_CONFIRM' => OrderSyncStatus::Processing,
        'WAITING_STOCK' => OrderSyncStatus::Processing,

        // --- Quy trình xử lý đơn ---
        'PROCESSING' => OrderSyncStatus::Processing,
        'PICKING' => OrderSyncStatus::Processing,
        'PICKED' => OrderSyncStatus::Processing,
        'PACKED' => OrderSyncStatus::Processing,
        'READY_TO_SHIP' => OrderSyncStatus::Processing,
        'AWAITING_SHIP' => OrderSyncStatus::Processing,

        // --- Giao hàng ---
        'SHIPPED' => OrderSyncStatus::Shipped,
        'SHIPPING' => OrderSyncStatus::Shipped,
        'DELIVERING' => OrderSyncStatus::Shipped,
        'IN_TRANSIT' => OrderSyncStatus::Shipped,

        // --- Hoàn tất ---
        'COMPLETED' => OrderSyncStatus::Completed,
        'DELIVERED' => OrderSyncStatus::Completed,
        'SUCCESS' => OrderSyncStatus::Completed,
        'FINISHED' => OrderSyncStatus::Completed,

        // --- Hủy ---
        'CANCELLED' => OrderSyncStatus::Cancelled,
        'CANCELED' => OrderSyncStatus::Cancelled,

        // --- Lỗi ---
        'FAILED' => OrderSyncStatus::SyncFailed,
        'ERROR' => OrderSyncStatus::SyncFailed,
    ];

    /**
     * Ánh xạ trạng thái BigSeller sang trạng thái nội bộ.
     *
     * Trả về null nếu chưa biết trạng thái này.
     */
    public function map(?string $bigSellerStatus): ?OrderSyncStatus
    {
        if (blank($bigSellerStatus)) {
            return null;
        }

        $key = strtoupper(trim($bigSellerStatus));

        $mapped = self::MAP[$key] ?? null;

        if ($mapped === null) {
            $this->warn('BigSeller: chưa biết trạng thái, giữ nguyên trạng thái nội bộ.', [
                'bigseller_status' => $bigSellerStatus,
            ]);
        }

        return $mapped;
    }

    /**
     * Ghi cảnh báo nếu đang ở trong Laravel container.
     *
     * Mapper cũng được dùng trong unit test thuần (không có app), nên phải
     * an toàn khi không có logger.
     */
    protected function warn(string $message, array $context = []): void
    {
        try {
            if (app()->bound('log')) {
                logger()->warning($message, $context);
            }
        } catch (\Throwable) {
            // Bỏ qua: không có logger trong môi trường hiện tại.
        }
    }

    /**
     * Danh sách các trạng thái BigSeller đã biết (dùng cho tài liệu/admin).
     *
     * @return array<int, string>
     */
    public function knownStatuses(): array
    {
        return array_keys(self::MAP);
    }

    /**
     * Ánh xạ và trả về trạng thái mới, hoặc giữ trạng thái hiện tại nếu không ánh xạ được.
     */
    public function mapOrKeep(?string $bigSellerStatus, OrderSyncStatus $current): OrderSyncStatus
    {
        $mapped = $this->map($bigSellerStatus);

        return $mapped ?? $current;
    }
}
