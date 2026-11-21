<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trang thông tin người dùng (Tài khoản của tôi).
 *
 * Khách đăng nhập xem lại thông tin cá nhân, địa chỉ nhận hàng,
 * lịch sử mua hàng và đổi mật khẩu.
 */
class AccountController extends Controller
{
    /**
     * Thông tin tài khoản + hồ sơ khách hàng + thống kê.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        $customer = Customer::where('user_id', $user->id)->first();

        $stats = Customer::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(total_orders), 0) as total_orders, COALESCE(SUM(total_spent), 0) as total_spent')
            ->first();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_admin' => $user->isAdmin(),
                    'created_at' => $user->created_at?->toIso8601String(),
                ],
                'profile' => [
                    'name' => $customer?->name ?? $user->name,
                    'email' => $customer?->email ?? $user->email,
                    'phone' => $customer?->phone ?? $user->phone,
                    'address' => $customer?->address,
                    'ward' => $customer?->ward,
                    'district' => $customer?->district,
                    'province' => $customer?->province,
                    'has_customer_record' => $customer !== null,
                ],
                'stats' => [
                    'total_orders' => (int) ($stats?->total_orders ?? 0),
                    'total_spent' => (float) ($stats?->total_spent ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Cập nhật thông tin cá nhân + địa chỉ nhận hàng mặc định.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s.\-()]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'ward' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ])->save();

        // Đồng bộ hồ sơ khách hàng (tạo nếu chưa có) để dùng làm địa chỉ mặc định.
        //
        // Chính sách "1 SĐT = 1 hồ sơ" (khớp với OrderService::resolveCustomer):
        //  - Ưu tiên hồ sơ đã gắn với tài khoản này.
        //  - Nếu chưa có, tái sử dụng hồ sơ vãng lai (`user_id IS NULL`) cùng SĐT
        //    và hợp nhất nó vào tài khoản này.
        //  - KHÔNG dùng hồ sơ của tài khoản khác chỉ vì trùng SĐT.
        $customer = Customer::where('user_id', $user->id)->first();

        if ($customer === null) {
            $customer = Customer::whereNull('user_id')
                ->where('phone', $validated['phone'])
                ->first();
        }

        // Số điện thoại đã thuộc hồ sơ của TÀI KHOẢN KHÁC => chặn.
        //
        // Kiểm tra trước khi ghi để trả lỗi rõ ràng cho người dùng thay vì
        // để unique index của database ném lỗi 500.
        $ownedByOther = Customer::where('phone', $validated['phone'])
            ->where('user_id', '!=', $user->id)
            ->whereNotNull('user_id')
            ->exists();

        if ($ownedByOther) {
            throw ValidationException::withMessages([
                'phone' => ['Số điện thoại đã được liên kết với tài khoản khác.'],
            ]);
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?? null,
            'ward' => $validated['ward'] ?? null,
            'district' => $validated['district'] ?? null,
            'province' => $validated['province'] ?? null,
        ];

        if ($customer) {
            $customer->forceFill($attributes + ['user_id' => $user->id])->save();
        } else {
            Customer::create($attributes + ['user_id' => $user->id]);
        }

        return response()->json([
            'message' => 'Đã cập nhật thông tin.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_admin' => $user->isAdmin(),
                ],
                'profile' => [
                    'name' => $attributes['name'],
                    'email' => $attributes['email'],
                    'phone' => $attributes['phone'],
                    'address' => $attributes['address'],
                    'ward' => $attributes['ward'],
                    'district' => $attributes['district'],
                    'province' => $attributes['province'],
                    'has_customer_record' => true,
                ],
            ],
        ]);
    }

    /**
     * Đổi mật khẩu.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không đúng.'],
            ]);
        }

        $user->forceFill(['password' => $validated['password']])->save();

        // Thu hồi các phiên khác, giữ phiên hiện tại để không bị đăng xuất.
        $currentTokenId = $user->currentAccessToken()?->id;

        $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json(['message' => 'Đã đổi mật khẩu thành công.']);
    }

    /**
     * Danh sách đơn hàng của người dùng.
     */
    public function orders(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with(['items', 'payments'])
            ->latest('id')
            ->paginate(min(30, max(1, (int) $request->query('per_page', 10))));

        return OrderResource::collection($orders);
    }

    /**
     * Thống kê nhanh: số đơn theo trạng thái.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $byStatus = $user->orders()
            ->selectRaw('sync_status, COUNT(*) as total')
            ->groupBy('sync_status')
            ->pluck('total', 'sync_status');

        return response()->json([
            'data' => [
                'total_orders' => (int) $byStatus->sum(),
                'by_status' => $byStatus->map(fn ($v) => (int) $v)->all(),
            ],
        ]);
    }
}
