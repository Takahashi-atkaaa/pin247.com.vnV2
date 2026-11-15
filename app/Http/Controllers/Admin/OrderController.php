<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderSyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Jobs\PushWebsiteOrderToBigSellerJob;
use App\Models\Order;
use App\Services\BigSeller\BigSellerOrderService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected BigSellerOrderService $bigSellerOrders,
    ) {
    }

    /**
     * Danh sách đơn hàng.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()->with(['items', 'payments']);

        if ($code = trim((string) $request->query('order_code', ''))) {
            $query->where(function ($q) use ($code): void {
                $q->where('order_code', 'like', "%{$code}%")
                    ->orWhere('bigseller_order_code', 'like', "%{$code}%");
            });
        }

        if ($phone = trim((string) $request->query('phone', ''))) {
            $query->where('customer_phone', 'like', "%{$phone}%");
        }

        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->query('sync_status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        return OrderResource::collection($query->latest('id')->paginate($perPage)->withQueryString());
    }

    /**
     * Chi tiết đơn hàng.
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payments', 'customer'])),
        ]);
    }

    /**
     * Thử đồng bộ lại đơn hàng lỗi.
     */
    public function resync(Request $request, Order $order): JsonResponse
    {
        if ($order->isSyncedToBigSeller()) {
            return response()->json([
                'message' => 'Đơn hàng đã có mã BigSeller, không cần đồng bộ lại.',
                'data' => ['bigseller_order_code' => $order->bigseller_order_code],
            ]);
        }

        $result = $this->orderService->pushToBigSeller($order->fresh(['items']));

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Đẩy đơn lên BigSeller bằng queue.
     */
    public function pushToBigSeller(Order $order): JsonResponse
    {
        PushWebsiteOrderToBigSellerJob::dispatch($order->id);

        return response()->json([
            'message' => 'Đã đưa đơn hàng vào hàng đợi đồng bộ BigSeller.',
        ]);
    }

    /**
     * Hủy đơn hàng.
     */
    public function cancel(Order $order): JsonResponse
    {
        $result = $this->orderService->cancelOrder($order);

        return response()->json(
            ['message' => $result['message'], 'data' => $result],
            $result['success'] ? 200 : 422,
        );
    }

    /**
     * Cập nhật trạng thái đơn từ BigSeller.
     */
    public function refreshStatus(Order $order): JsonResponse
    {
        $result = $this->bigSellerOrders->refreshStatus($order);

        return response()->json(
            ['message' => $result['message'], 'data' => $result],
            $result['success'] ? 200 : 422,
        );
    }

    /**
     * Ghi chú nội bộ.
     */
    public function updateNote(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order->forceFill(['internal_note' => $validated['internal_note'] ?? null])->save();

        return response()->json(['message' => 'Đã lưu ghi chú nội bộ.']);
    }
}
