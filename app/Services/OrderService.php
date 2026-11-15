<?php

namespace App\Services;

use App\Enums\OrderSyncStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Sku;
use App\Services\BigSeller\BigSellerInventoryService;
use App\Services\BigSeller\BigSellerOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Nghiệp vụ đặt hàng.
 */
class OrderService
{
    public function __construct(
        protected InventoryService $inventory,
        protected BigSellerInventoryService $bigSellerInventory,
        protected BigSellerOrderService $bigSellerOrders,
    ) {
    }

    /**
     * Tạo đơn hàng từ giỏ hàng.
     *
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @param  array<string, mixed>  $customerData
     * @return array{order: Order, warnings: array<int, string>}
     */
    public function createOrder(array $items, array $customerData, ?int $userId = null): array
    {
        if ($items === []) {
            throw new BusinessRuleException('Giỏ hàng trống, không thể tạo đơn hàng.');
        }

        // --- Bước 1: chuẩn hóa & gộp các dòng trùng SKU ---
        $quantities = [];

        foreach ($items as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($sku === '' || $quantity <= 0) {
                continue;
            }

            $quantities[$sku] = ($quantities[$sku] ?? 0) + $quantity;
        }

        if ($quantities === []) {
            throw new BusinessRuleException('Giỏ hàng không có sản phẩm hợp lệ.');
        }

        // --- Bước 2: kiểm tra SKU tồn tại ---
        $skuRecords = Sku::query()
            ->whereIn('sku', array_keys($quantities))
            ->with(['product', 'variant'])
            ->get()
            ->keyBy('sku');

        $missing = array_diff(array_keys($quantities), $skuRecords->keys()->all());

        if ($missing !== []) {
            throw new BusinessRuleException(
                'SKU không tồn tại: '.implode(', ', $missing)
            );
        }

        // --- Bước 3: kiểm tra tồn kho khả dụng (cache hiện tại) ---
        $availability = $this->inventory->checkAvailability($quantities);

        if (! $availability['valid']) {
            throw new BusinessRuleException(
                'Tồn kho không đủ: '.implode('; ', $availability['errors']),
                ['availability' => $availability],
            );
        }

        // --- Bước 4: kiểm tra LẠI tồn kho gần thời điểm đặt hàng ---
        // Mục đích giảm rủi ro oversell do đồng bộ định kỳ bị trễ.
        $warnings = [];
        $liveCheckFailed = false;

        foreach ($quantities as $sku => $quantity) {
            $liveAvailable = $this->bigSellerInventory->checkAvailable($sku);

            if ($liveAvailable === null) {
                // Không lấy được dữ liệu BigSeller -> KHÔNG coi là còn hàng.
                $liveCheckFailed = true;

                $warnings[] = "Không kiểm tra được tồn kho BigSeller cho SKU {$sku}. "
                    .'Đơn hàng sẽ được giữ ở trạng thái chờ đồng bộ để BigSeller xác nhận.';

                continue;
            }

            $sellable = max(0, $liveAvailable - (int) $skuRecords[$sku]->safety_stock);

            if ($quantity > $sellable) {
                throw new BusinessRuleException(
                    "Sản phẩm {$sku} hiện chỉ còn {$sellable} trên BigSeller (yêu cầu {$quantity}).",
                    ['sku' => $sku, 'available' => $sellable, 'requested' => $quantity],
                );
            }
        }

        // --- Bước 5: tạo đơn hàng trong transaction ---
        $order = DB::transaction(function () use ($quantities, $skuRecords, $customerData, $userId) {
            // Bước 5.1: khoá dòng SKU để chống oversell do đặt hàng đồng thời.
            //
            // `lockForUpdate()` giữ row lock tới hết transaction, đảm bảo hai
            // request đặt hàng cùng lúc KHÔNG cùng đọc được một giá trị
            // tồn kho cũ. Tồn kho được "đặt giữ" (reserved) ngay khi tạo
            // đơn, sau đó BigSeller sẽ xác nhận lại qua đồng bộ tồn kho.
            $lockedSkus = Sku::query()
                ->whereIn('sku', array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('sku');

            foreach ($quantities as $sku => $quantity) {
                /** @var Sku|null $locked */
                $locked = $lockedSkus->get($sku);

                if ($locked === null) {
                    throw new BusinessRuleException("SKU không tồn tại: {$sku}");
                }

                $sellable = $locked->sellableQuantity();

                if ($quantity > $sellable) {
                    throw new BusinessRuleException(
                        "Sản phẩm {$sku} chỉ còn {$sellable} (yêu cầu {$quantity}).",
                        ['sku' => $sku, 'available' => $sellable, 'requested' => $quantity],
                    );
                }

                // Giữ lại tồn kho cho đơn này (chỉ giữ ở phía website).
                $locked->forceFill([
                    'local_reserved_stock' => $locked->local_reserved_stock + $quantity,
                ])->save();
            }

            $customer = $this->resolveCustomer($customerData, $userId);

            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'customer_id' => $customer?->id,
                'user_id' => $userId,
                'customer_name' => $customerData['name'],
                'customer_phone' => $customerData['phone'],
                'customer_email' => $customerData['email'] ?? null,
                'shipping_address' => $customerData['address'],
                'ward' => $customerData['ward'] ?? null,
                'district' => $customerData['district'] ?? null,
                'province' => $customerData['province'] ?? null,
                'note' => $customerData['note'] ?? null,
                'payment_method' => $customerData['payment_method'] ?? PaymentMethod::Cod,
                'sync_status' => OrderSyncStatus::PendingSync,
                'payment_status' => PaymentStatus::Unpaid,
            ]);

            $subtotal = 0;

            foreach ($quantities as $sku => $quantity) {
                $skuRecord = $skuRecords[$sku];

                $price = (float) ($skuRecord->variant?->effectivePrice()
                    ?? $skuRecord->product?->effectivePrice()
                    ?? $skuRecord->price);

                $lineTotal = round($price * $quantity, 2);
                $subtotal += $lineTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'sku_id' => $skuRecord->id,
                    'product_id' => $skuRecord->product_id,
                    'product_variant_id' => $skuRecord->product_variant_id,
                    'sku' => $sku,
                    'product_name' => $skuRecord->product?->name ?? $sku,
                    'variant_name' => $skuRecord->variant?->name,
                    'image' => $skuRecord->variant?->image ?? $skuRecord->product?->image,
                    'quantity' => $quantity,
                    'price' => $price,
                    'cost_price' => $skuRecord->cost_price,
                    'subtotal' => $lineTotal,
                ]);
            }

            $shippingFee = (float) ($customerData['shipping_fee'] ?? 0);
            $discount = (float) ($customerData['discount_amount'] ?? 0);
            $total = max(0, round($subtotal + $shippingFee - $discount, 2));

            $order->forceFill([
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount_amount' => $discount,
                'total_amount' => $total,
            ])->save();

            Payment::create([
                'order_id' => $order->id,
                'method' => $order->payment_method,
                'status' => PaymentStatus::Unpaid,
                'amount' => $total,
                'paid_amount' => 0,
                'bank_code' => $customerData['bank_code'] ?? null,
                'bank_account' => $customerData['bank_account'] ?? null,
            ]);

            if ($customer) {
                $customer->forceFill([
                    'total_orders' => $customer->total_orders + 1,
                    'total_spent' => (float) $customer->total_spent + $total,
                ])->save();
            }

            return $order->fresh(['items', 'payments']);
        });

        return [
            'order' => $order,
            'warnings' => $warnings,
            'live_check_failed' => $liveCheckFailed,
        ];
    }

    /**
     * Đẩy đơn hàng lên BigSeller (được gọi từ job hoặc thủ công).
     */
    public function pushToBigSeller(Order $order): array
    {
        try {
            $result = $this->bigSellerOrders->pushOrder($order);
        } catch (\Throwable $e) {
            Log::error('Đẩy đơn lên BigSeller thất bại: '.$e->getMessage(), [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
            ]);

            $order->forceFill([
                'sync_status' => OrderSyncStatus::SyncFailed,
                'sync_error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        // Thành công = BigSeller đã trả về mã đơn hàng.
        //
        // KHÔNG dựa vào `sync_status` bằng SYNCED: BigSeller có thể trả về
        // trạng thái đầu vào khác (vd. PENDING_CONFIRM -> PROCESSING) dù đơn
        // đã được tạo thành công. Tiêu chí đúng là đã có mã đơn.
        $success = filled($result['bigseller_order_code'] ?? null);

        return [
            'success' => $success,
            'message' => $result['message'],
            'bigseller_order_code' => $result['bigseller_order_code'],
            'status' => $result['status'],
        ];
    }

    /**
     * Hủy đơn hàng.
     */
    public function cancelOrder(Order $order): array
    {
        if ($order->sync_status->isTerminal() && $order->sync_status !== OrderSyncStatus::SyncFailed) {
            return [
                'success' => false,
                'message' => 'Đơn hàng đã ở trạng thái '.$order->sync_status->label().', không thể hủy.',
            ];
        }

        $result = $this->bigSellerOrders->cancelOrder($order);

        // Đơn đã hủy => giải phóng lượng tồn kho website đang giữ.
        if ($result['success'] && $order->fresh()->sync_status === OrderSyncStatus::Cancelled) {
            $this->releaseLocalReservation($order);
        }

        return $result;
    }

    /**
     * Giải phóng lượng tồn kho do website giữ cho một đơn.
     *
     * Được gọi khi đơn bị hủy. Việc BigSeller xác nhận giải phóng tồn kho
     * thực hiện qua đồng bộ tồn kho định kỳ.
     */
    public function releaseLocalReservation(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            foreach ($order->items()->get() as $item) {
                if (! $item->sku_id) {
                    continue;
                }

                $sku = Sku::whereKey($item->sku_id)->lockForUpdate()->first();

                if ($sku === null) {
                    continue;
                }

                $sku->forceFill([
                    'local_reserved_stock' => max(0, $sku->local_reserved_stock - (int) $item->quantity),
                ])->save();
            }
        });
    }

    /**
     * Tìm hoặc tạo khách hàng.
     */
    protected function resolveCustomer(array $customerData, ?int $userId): ?Customer
    {
        $phone = (string) $customerData['phone'];

        $customer = Customer::where('phone', $phone)->first();

        if ($customer === null && $userId !== null) {
            $customer = Customer::where('user_id', $userId)->first();
        }

        if ($customer === null) {
            $customer = Customer::create([
                'user_id' => $userId,
                'name' => $customerData['name'],
                'email' => $customerData['email'] ?? null,
                'phone' => $phone,
                'address' => $customerData['address'] ?? null,
                'ward' => $customerData['ward'] ?? null,
                'district' => $customerData['district'] ?? null,
                'province' => $customerData['province'] ?? null,
            ]);
        } else {
            $customer->forceFill([
                'name' => $customerData['name'],
                'email' => $customerData['email'] ?? $customer->email,
                'address' => $customerData['address'] ?? $customer->address,
                'ward' => $customerData['ward'] ?? $customer->ward,
                'district' => $customerData['district'] ?? $customer->district,
                'province' => $customerData['province'] ?? $customer->province,
            ])->save();
        }

        return $customer;
    }
}
