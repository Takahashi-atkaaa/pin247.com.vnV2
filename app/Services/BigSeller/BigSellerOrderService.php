<?php

namespace App\Services\BigSeller;

use App\Enums\OrderSyncStatus;
use App\Exceptions\BigSellerApiException;
use App\Models\BigSellerOrderMapping;
use App\Models\Order;
use App\Services\BigSeller\Contracts\BigSellerClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Đẩy đơn hàng website lên BigSeller.
 *
 * YÊU CẦU CHỐNG TẠO ĐƠN TRÙNG (bắt buộc):
 * 1. Mỗi đơn có `idempotency_key` duy nhất, lưu trong orders và
 *    bigseller_order_mappings.
 * 2. Trước khi gửi, kiểm tra mapping đã có bigseller_order_code chưa
 *    => nếu có thì KHÔNG gửi lại.
 * 3. Sau khi gửi, lưu mã BigSeller vào mapping VÀ đơn, trong transaction.
 * 4. Nếu request timeout (không chắc đã tạo hay chưa), retry sẽ dùng lại
 *    cùng idempotency_key => BigSeller từ chối tạo trùng.
 */
class BigSellerOrderService
{
    public function __construct(
        protected BigSellerClient $client,
        protected BigSellerAuthService $auth,
        protected BigSellerStatusMapper $statusMapper,
    ) {
    }

    /**
     * Đẩy một đơn hàng lên BigSeller.
     *
     * @return array{status: string, bigseller_order_code: ?string, message: string, duplicate: bool}
     */
    public function pushOrder(Order $order, ?string $syncRunId = null): array
    {
        $syncRunId ??= (string) Str::uuid();

        // --- Bước 1: đối chiếu trước khi gửi (chống trùng) ---
        $existing = BigSellerOrderMapping::where('order_id', $order->id)->first();

        if ($existing && filled($existing->bigseller_order_code)) {
            // Đã tạo trên BigSeller rồi -> không gửi lại.
            if ($order->bigseller_order_code !== $existing->bigseller_order_code) {
                $order->forceFill(['bigseller_order_code' => $existing->bigseller_order_code])->save();
            }

            return [
                'status' => OrderSyncStatus::Synced->value,
                'bigseller_order_code' => $existing->bigseller_order_code,
                'message' => 'Đơn đã được đồng bộ trước đó, bỏ qua gửi lại.',
                'duplicate' => true,
            ];
        }

        if (filled($order->bigseller_order_code)) {
            return [
                'status' => $order->sync_status->value,
                'bigseller_order_code' => $order->bigseller_order_code,
                'message' => 'Đơn đã có mã BigSeller, bỏ qua gửi lại.',
                'duplicate' => true,
            ];
        }

        // --- Bước 2: đánh dấu đang gửi ---
        $order->forceFill([
            'sync_status' => OrderSyncStatus::Syncing,
            'sync_attempts' => $order->sync_attempts + 1,
            'last_sync_attempt_at' => now(),
            'sync_error' => null,
        ])->save();

        $mapping = $existing ?? new BigSellerOrderMapping([
            'order_id' => $order->id,
            'idempotency_key' => $order->idempotency_key,
        ]);

        $payload = $this->buildPayload($order, $syncRunId);

        $mapping->forceFill([
            'idempotency_key' => $order->idempotency_key,
            'request_payload' => $payload,
            'attempts' => $mapping->attempts + 1,
            'last_attempt_at' => now(),
        ])->save();

        // --- Bước 3: gọi BigSeller ---
        try {
            $result = $this->client->createManualOrder($payload);
        } catch (\Throwable $e) {
            $this->markFailed($order, $mapping, $e->getMessage());

            throw $e;
        }

        $mapping->forceFill(['response_payload' => $result->raw])->save();

        if (! $result->success) {
            $message = $result->errorMessage ?? 'BigSeller từ chối đơn hàng.';

            $this->markFailed($order, $mapping, $message, $result->errorCode);

            return [
                'status' => OrderSyncStatus::SyncFailed->value,
                'bigseller_order_code' => null,
                'message' => $message,
                'duplicate' => false,
            ];
        }

        if (blank($result->bigSellerOrderCode)) {
            // BigSeller báo thành công nhưng không trả mã đơn -> không thể xác nhận.
            $message = 'BigSeller phản hồi thành công nhưng không trả về mã đơn hàng. Cần đối chiếu thủ công.';

            $this->markFailed($order, $mapping, $message);

            return [
                'status' => OrderSyncStatus::SyncFailed->value,
                'bigseller_order_code' => null,
                'message' => $message,
                'duplicate' => false,
            ];
        }

        // --- Bước 4: lưu kết quả trong transaction ---
        $mappedStatus = $this->statusMapper->mapOrKeep($result->status, OrderSyncStatus::Synced);

        DB::transaction(function () use ($order, $mapping, $result, $mappedStatus): void {
            $order->forceFill([
                'bigseller_order_code' => $result->bigSellerOrderCode,
                'sync_status' => $mappedStatus,
                'synced_at' => now(),
                'sync_error' => null,
            ])->save();

            $mapping->forceFill([
                'bigseller_order_code' => $result->bigSellerOrderCode,
                'bigseller_status' => $result->status,
                'mapped_status' => $mappedStatus->value,
                'synced_at' => now(),
                'last_error' => null,
            ])->save();
        });

        return [
            'status' => $mappedStatus->value,
            'bigseller_order_code' => $result->bigSellerOrderCode,
            'message' => 'Đẩy đơn hàng lên BigSeller thành công.',
            'duplicate' => false,
        ];
    }

    /**
     * Hủy đơn hàng trên BigSeller.
     *
     * Nếu tài khoản chưa được cấp quyền API hủy, đánh dấu chờ xử lý và
     * KHÔNG giả lập thành công.
     */
    public function cancelOrder(Order $order): array
    {
        if (blank($order->bigseller_order_code)) {
            $order->forceFill([
                'sync_status' => OrderSyncStatus::Cancelled,
                'sync_error' => 'Đơn chưa được đồng bộ lên BigSeller nên không thể hủy trên BigSeller.',
            ])->save();

            return [
                'success' => true,
                'message' => 'Đơn chưa gửi lên BigSeller, đã đánh dấu hủy nội bộ.',
            ];
        }

        if (! $this->auth->hasCapability('cancel_order')) {
            $order->forceFill([
                'sync_status' => OrderSyncStatus::CancelPending,
                'sync_error' => 'Chờ xử lý: tài khoản BigSeller chưa được cấp quyền API hủy đơn.',
            ])->save();

            return [
                'success' => false,
                'message' => 'Tài khoản BigSeller chưa được cấp quyền API hủy đơn. Đơn đã được đánh dấu chờ xử lý.',
            ];
        }

        try {
            $result = $this->client->cancelOrder([
                'order_code' => $order->bigseller_order_code,
                '__local_order_id' => $order->id,
            ]);
        } catch (BigSellerApiException $e) {
            $order->forceFill([
                'sync_status' => OrderSyncStatus::CancelPending,
                'sync_error' => 'Lỗi khi hủy đơn trên BigSeller: '.$e->getMessage(),
            ])->save();

            return [
                'success' => false,
                'message' => 'Lỗi khi hủy đơn trên BigSeller: '.$e->getMessage(),
            ];
        }

        if (! $result->success) {
            $order->forceFill([
                'sync_status' => OrderSyncStatus::CancelPending,
                'sync_error' => $result->errorMessage ?? 'BigSeller từ chối yêu cầu hủy.',
            ])->save();

            return [
                'success' => false,
                'message' => $result->errorMessage ?? 'BigSeller từ chối yêu cầu hủy.',
            ];
        }

        $mappedStatus = $this->statusMapper->mapOrKeep($result->rawStatus, OrderSyncStatus::Cancelled);

        $order->forceFill([
            'sync_status' => $mappedStatus,
            'sync_error' => null,
        ])->save();

        return [
            'success' => true,
            'message' => 'Đã hủy đơn trên BigSeller.',
        ];
    }

    /**
     * Đồng bộ trạng thái đơn từ BigSeller về website.
     */
    public function refreshStatus(Order $order): array
    {
        if (blank($order->bigseller_order_code)) {
            return [
                'success' => false,
                'message' => 'Đơn chưa có mã BigSeller.',
            ];
        }

        if (! $this->auth->hasCapability('order_status')) {
            return [
                'success' => false,
                'message' => 'Tài khoản BigSeller chưa được cấp quyền API tra cứu trạng thái đơn.',
            ];
        }

        try {
            $result = $this->client->getOrderStatus([
                'order_code' => $order->bigseller_order_code,
                '__local_order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        if (! $result->success) {
            return [
                'success' => false,
                'message' => $result->errorMessage ?? 'Không lấy được trạng thái đơn.',
            ];
        }

        $mapped = $this->statusMapper->map($result->rawStatus);

        if ($mapped === null) {
            Log::warning('Trạng thái BigSeller chưa biết, giữ nguyên trạng thái nội bộ.', [
                'order_id' => $order->id,
                'bigseller_status' => $result->rawStatus,
            ]);

            return [
                'success' => false,
                'message' => "Trạng thái '{$result->rawStatus}' chưa được ánh xạ. Đơn giữ nguyên trạng thái.",
            ];
        }

        $order->forceFill(['sync_status' => $mapped])->save();

        BigSellerOrderMapping::where('order_id', $order->id)->update([
            'bigseller_status' => $result->rawStatus,
            'mapped_status' => $mapped->value,
        ]);

        return [
            'success' => true,
            'message' => 'Đã cập nhật trạng thái đơn từ BigSeller.',
            'status' => $mapped->value,
        ];
    }

    /**
     * Đánh dấu đơn thất bại đồng bộ.
     */
    protected function markFailed(Order $order, BigSellerOrderMapping $mapping, string $message, ?string $errorCode = null): void
    {
        $message = mb_substr($message, 0, 500);

        $order->forceFill([
            'sync_status' => OrderSyncStatus::SyncFailed,
            'sync_error' => $message,
        ])->save();

        $mapping->forceFill([
            'last_error' => $message,
        ])->save();
    }

    /**
     * Dựng payload gửi BigSeller.
     *
     * Cấu trúc payload bám theo tài liệu API chính thức của BigSeller.
     * Khi nhận được tài liệu, chỉ cần chỉnh lại method này.
     */
    protected function buildPayload(Order $order, string $syncRunId): array
    {
        $items = $order->items()->get()->map(fn ($item) => [
            'sku' => $item->sku,
            'quantity' => $item->quantity,
            'price' => (float) $item->price,
        ])->all();

        return [
            '__idempotency_key' => $order->idempotency_key,
            '__sync_run_id' => $syncRunId,
            '__local_order_id' => $order->id,

            'external_order_code' => $order->order_code,
            'shop_code' => $this->auth->activeConnection()?->shop_code,
            'warehouse_code' => $this->auth->activeConnection()?->defaultWarehouse?->code,

            'receiver' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
                'address' => $order->shipping_address,
                'ward' => $order->ward,
                'district' => $order->district,
                'province' => $order->province,
            ],

            'items' => $items,

            'total_amount' => (float) $order->total_amount,
            'shipping_fee' => (float) $order->shipping_fee,
            'payment_method' => $order->payment_method->value,
            'note' => $order->note,
        ];
    }
}
