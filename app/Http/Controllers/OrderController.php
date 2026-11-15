<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Đơn hàng của người dùng đang đăng nhập.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'payments'])
            ->latest('id')
            ->paginate(min(30, max(1, (int) $request->query('per_page', 10))));

        return OrderResource::collection($orders);
    }

    /**
     * Chi tiết đơn hàng (chỉ chủ đơn hoặc Admin).
     */
    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403, 'Bạn không có quyền xem đơn hàng này.');
        }

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payments'])),
        ]);
    }
}
