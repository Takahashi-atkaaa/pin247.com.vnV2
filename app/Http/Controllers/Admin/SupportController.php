<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chat hỗ trợ — phía ADMIN.
 *
 * Admin xem danh sách yêu cầu, tìm tài khoản khách, đặt lại mật khẩu và
 * trả lời khách trong khung chat.
 *
 * LƯU Ý BẢO MẬT:
 *   - Mật khẩu plaintext KHÔNG BAO GIỜ được lưu vào DB hay ghi vào chat.
 *     Nó chỉ được trả về ĐÚNG 1 LẦN trong response của endpoint reset.
 *   - Admin phải tự chọn tài khoản từ danh sách kết quả tìm kiếm
 *     (không auto-reset) để tránh reset nhầm.
 */
class SupportController extends Controller
{
    /**
     * Danh sách yêu cầu hỗ trợ (mới nhất trước).
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportRequest::query()->with(['messages' => fn ($q) => $q->latest('id')]);

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = $query->latest('id')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginator->through(fn ($supportRequest) => [
                'id' => $supportRequest->id,
                'name' => $supportRequest->name,
                'email' => $supportRequest->email,
                'phone' => $supportRequest->phone,
                'message' => $supportRequest->message,
                'status' => $supportRequest->status,
                'topic' => $supportRequest->topic,
                'user_id' => $supportRequest->user_id,
                'customer_id' => $supportRequest->customer_id,
                'message_count' => $supportRequest->messages->count(),
                'last_message_at' => $supportRequest->messages->last()?->created_at?->toIso8601String(),
                'created_at' => $supportRequest->created_at?->toIso8601String(),
                'resolved_at' => $supportRequest->resolved_at?->toIso8601String(),
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
     * Chi tiết 1 yêu cầu + toàn bộ lịch sử chat.
     */
    public function show(SupportRequest $supportRequest): JsonResponse
    {
        $supportRequest->load(['messages' => fn ($q) => $q->oldest('id')]);

        return response()->json([
            'data' => [
                'id' => $supportRequest->id,
                'name' => $supportRequest->name,
                'email' => $supportRequest->email,
                'phone' => $supportRequest->phone,
                'message' => $supportRequest->message,
                'status' => $supportRequest->status,
                'topic' => $supportRequest->topic,
                'user_id' => $supportRequest->user_id,
                'customer_id' => $supportRequest->customer_id,
                'created_at' => $supportRequest->created_at?->toIso8601String(),
                'messages' => $supportRequest->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender_name,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Tìm tài khoản khách theo email / số điện thoại.
     *
     * Trả về DANH SÁCH (tối đa 20) để admin tự chọn — tránh reset nhầm khi
     * số điện thoại trùng giữa nhiều tài khoản.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $email = trim((string) ($validated['email'] ?? ''));
        $phone = trim((string) ($validated['phone'] ?? ''));

        if ($email === '' && $phone === '') {
            throw ValidationException::withMessages([
                'email' => ['Cần ít nhất email hoặc số điện thoại để tìm tài khoản.'],
            ]);
        }

        $users = User::query()
            ->when($email !== '', fn ($q) => $q->where('email', 'like', "%{$email}%"))
            ->when($phone !== '', fn ($q) => $q->where('phone', 'like', "%{$phone}%"))
            ->with('customer')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_admin' => $user->isAdmin(),
                'created_at' => $user->created_at?->toIso8601String(),
                'total_orders' => $user->orders()->count(),
                'customer_id' => $user->customer?->id,
            ]),
        ]);
    }

    /**
     * Đặt lại mật khẩu cho khách (thao tác của admin).
     *
     * - Admin không nhập mật khẩu => hệ thống sinh mật khẩu ngẫu nhiên.
     * - Mật khẩu chỉ được TRẢ VỀ 1 LẦN trong response, KHÔNG lưu vào DB.
     * - Thu hồi toàn bộ token đăng nhập (bắt buộc khi đổi mật khẩu).
     */
    public function resetPassword(Request $request, SupportRequest $supportRequest): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ], [
            'user_id.required' => 'Vui lòng chọn tài khoản cần đặt lại mật khẩu.',
            'user_id.exists' => 'Tài khoản không tồn tại.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Không cho reset tài khoản quản trị khác (tránh chiếm quyền).
        if ($user->isAdmin() && $user->id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Không thể đặt lại mật khẩu cho tài khoản quản trị.'],
            ]);
        }

        $plain = $validated['password'] ?? $this->generatePassword();

        DB::transaction(function () use ($user, $plain): void {
            // Cast 'password' => 'hashed' tự bcrypt hoá — không cần Hash::make().
            $user->forceFill(['password' => $plain])->save();

            // Thu hồi toàn bộ phiên đăng nhập (Sanctum) — buộc đăng nhập lại.
            $user->tokens()->delete();
            $user->forceFill(['remember_token' => null])->save();
        });

        // Liên kết request với tài khoản đã xác định.
        $supportRequest->forceFill([
            'user_id' => $user->id,
            'customer_id' => $user->customer?->id,
        ])->save();

        // Ghi nhận trong chat — KHÔNG chứa mật khẩu.
        SupportMessage::create([
            'support_request_id' => $supportRequest->id,
            'sender_type' => 'admin',
            'sender_name' => $request->user()->name,
            'body' => 'Đã đặt lại mật khẩu thành công cho tài khoản '.$user->email
                .'. Mật khẩu mới đã được gửi riêng cho khách.',
        ]);

        return response()->json([
            'message' => 'Đã đặt lại mật khẩu. Hiển thị mật khẩu cho khách NGAY (chỉ hiện 1 lần).',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                // CHỈ trả về 1 lần, không lưu vào DB.
                'password' => $plain,
            ],
        ]);
    }

    /**
     * Admin gửi tin nhắn cho khách.
     */
    public function reply(Request $request, SupportRequest $supportRequest): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Vui lòng nhập nội dung tin nhắn.',
            'body.max' => 'Tin nhắn tối đa 2000 ký tự.',
        ]);

        $message = SupportMessage::create([
            'support_request_id' => $supportRequest->id,
            'sender_type' => 'admin',
            'sender_name' => $request->user()->name,
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => 'Đã gửi.',
            'data' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Đóng yêu cầu.
     */
    public function resolve(Request $request, SupportRequest $supportRequest): JsonResponse
    {
        $supportRequest->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ])->save();

        return response()->json(['message' => 'Đã đóng yêu cầu.']);
    }

    /**
     * Sinh mật khẩu ngẫu nhiên dễ đọc qua điện thoại.
     *
     * Bỏ ký tự dễ nhầm (0/O, 1/l/I) và bắt buộc có đủ chữ thường, chữ hoa,
     * số và ký hiệu.
     */
    protected function generatePassword(): string
    {
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digit = '23456789';
        $symbol = '!@#$%&*';

        $all = $lower.$upper.$digit.$symbol;
        $chars = [];

        // Bắt buộc có mặt từng loại.
        foreach ([$lower, $upper, $digit, $symbol] as $pool) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        // Thêm 6 ký tự nữa cho độ dài 10 — dễ đọc qua điện thoại.
        for ($i = 0; $i < 6; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle(implode('', $chars));
    }
}
