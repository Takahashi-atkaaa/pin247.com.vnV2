<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Danh sách khách hàng.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = $query->latest('id')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginator->through(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'province' => $customer->province,
                'total_orders' => (int) $customer->total_orders,
                'total_spent' => (float) $customer->total_spent,
                'created_at' => $customer->created_at?->toIso8601String(),
            ])->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Chi tiết khách hàng + lịch sử mua hàng.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['orders' => fn ($q) => $q->with('items')->latest('id')->limit(20)]);

        return response()->json([
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'ward' => $customer->ward,
                'district' => $customer->district,
                'province' => $customer->province,
                'note' => $customer->note,
                'total_orders' => (int) $customer->total_orders,
                'total_spent' => (float) $customer->total_spent,
                'orders' => $customer->orders->map(fn ($order) => [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'total_amount' => (float) $order->total_amount,
                    'sync_status' => $order->sync_status->value,
                    'sync_status_label' => $order->sync_status->label(),
                    'created_at' => $order->created_at?->toIso8601String(),
                    'items' => $order->items->map(fn ($item) => [
                        'product_name' => $item->product_name,
                        'variant_name' => $item->variant_name,
                        'quantity' => (int) $item->quantity,
                        'subtotal' => (float) $item->subtotal,
                    ]),
                ]),
            ],
        ]);
    }
}
