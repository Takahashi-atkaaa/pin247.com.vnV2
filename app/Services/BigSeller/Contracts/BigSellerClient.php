<?php

namespace App\Services\BigSeller\Contracts;

use App\Services\BigSeller\Data\InventoryItem;
use App\Services\BigSeller\Data\ManualOrderResult;
use App\Services\BigSeller\Data\OrderStatusResult;
use App\Services\BigSeller\Data\ProductData;
use App\Services\BigSeller\Data\WarehouseData;

/**
 * HỢP ĐỒNG (contract) của toàn bộ tích hợp BigSeller.
 *
 * Đây là interface DUY NHẤT mà phần còn lại của ứng dụng được phép phụ thuộc.
 * Mọi triển khai (HTTP thật / Mock) đều phải tuân thủ đúng interface này.
 *
 * LƯU Ý QUAN TRỌNG:
 * Tài liệu API của BigSeller KHÔNG được công khai tự do — chỉ được cung cấp
 * sau khi tài khoản được BigSeller phê duyệt quyền truy cập. Vì vậy:
 *   - Không tự bịa endpoint, chữ ký xác thực, cấu trúc request/response.
 *   - Mọi method dưới đây được thiết kế để CHỈ được gọi khi capability
 *     tương ứng đã được cấp quyền (xem BigSellerAuthService).
 */
interface BigSellerClient
{
    /**
     * Kiểm tra kết nối & xác thực hiện tại.
     *
     * @return array{success: bool, message: string, details: array}
     */
    public function checkConnection(): array;

    /**
     * Truy vấn tồn kho khả dụng theo SKU tại một kho.
     *
     * @param  array<string, mixed>  $params  Tham số theo tài liệu BigSeller.
     * @return array{items: array<int, InventoryItem>, raw: array}
     */
    public function getInventoryListDetail(array $params): array;

    /**
     * Tạo đơn hàng thủ công trên BigSeller từ đơn hàng website.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createManualOrder(array $payload): ManualOrderResult;

    /**
     * Hủy đơn hàng đã tạo trên BigSeller.
     *
     * @param  array<string, mixed>  $params
     */
    public function cancelOrder(array $params): OrderStatusResult;

    /**
     * Lấy trạng thái hiện tại của đơn hàng BigSeller.
     *
     * @param  array<string, mixed>  $params
     */
    public function getOrderStatus(array $params): OrderStatusResult;

    /**
     * Lấy danh sách sản phẩm/SKU từ BigSeller.
     *
     * CHỈ gọi khi tài khoản được cấp quyền API sản phẩm.
     *
     * @param  array<string, mixed>  $params
     */
    public function getProducts(array $params): array;

    /**
     * Lấy danh sách kho hàng từ BigSeller.
     *
     * @param  array<string, mixed>  $params
     * @return array{items: array<int, WarehouseData>, raw: array}
     */
    public function getWarehouses(array $params): array;
}
