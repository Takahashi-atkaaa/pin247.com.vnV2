<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\PushWebsiteOrderToBigSellerJob;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {
    }

    /**
     * Tạo đơn hàng.
     *
     * Luồng:
     *  1. Validate dữ liệu.
     *  2. Kiểm tra tồn kho (cache + kiểm tra gần thời điểm đặt hàng).
     *  3. Tạo đơn nội bộ với trạng thái PENDING_SYNC.
     *  4. Dispatch job đẩy lên BigSeller.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $items = $request->validated()['items'];

        try {
            $result = $this->orderService->createOrder(
                $items,
                $request->validated(),
                $request->user()?->id,
            );
        } catch (BusinessRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->context,
            ], 422);
        }

        /** @var Order $order */
        $order = $result['order'];

        // Đẩy đơn lên BigSeller bằng queue.
        PushWebsiteOrderToBigSellerJob::dispatch($order->id);

        return response()->json([
            'message' => 'Đặt hàng thành công.',
            'warnings' => $result['warnings'],
            'data' => new OrderResource($order->load(['items', 'payments'])),
        ], 201);
    }

    /**
     * Tra cứu đơn hàng bằng mã đơn + số điện thoại (không cần đăng nhập).
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_code' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $order = Order::query()
            ->where('order_code', $validated['order_code'])
            ->where('customer_phone', $validated['phone'])
            ->with(['items', 'payments'])
            ->first();

        if ($order === null) {
            return response()->json([
                'message' => 'Không tìm thấy đơn hàng phù hợp với mã đơn và số điện thoại.',
            ], 404);
        }

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }
}
