<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected OrderService $orderService,
    ) {
    }

    /**
     * Kiểm tra giỏ hàng: tồn kho & giá mới nhất.
     *
     * Giỏ hàng được quản lý phía client (Pinia + localStorage), API chỉ làm
     * nguồn kiểm tra tồn kho và giá trước khi thanh toán.
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $quantities = [];

        foreach ($validated['items'] as $item) {
            $quantities[$item['sku']] = ($quantities[$item['sku']] ?? 0) + $item['quantity'];
        }

        $availability = $this->inventoryService->checkAvailability($quantities);

        return response()->json([
            'valid' => $availability['valid'],
            'errors' => $availability['errors'],
            'data' => $availability['available'],
        ]);
    }
}
