<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chat hỗ trợ — phía KHÁCH (chưa đăng nhập).
 *
 * Luồng:
 *   1. Khách POST /api/support/requests  → tạo request + tin nhắn đầu tiên,
 *      nhận về `public_token` (uuid) để làm "phiên chat" riêng tư.
 *   2. Khách GET/POST /api/support/messages/{token} → đọc / gửi tin nhắn.
 *
 * BẢO MẬT:
 *   - Dùng `public_token` (uuid) thay vì id tự tăng ⇒ không thể dò chat
 *     của người khác.
 *   - Throttle chặn spam.
 *   - Không tiết lộ request có tồn tại hay không (trả 404 chung).
 */
class SupportController extends Controller
{
    /**
     * Tạo yêu cầu hỗ trợ mới.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'message.required' => 'Vui lòng nhập nội dung cần hỗ trợ.',
            'message.max' => 'Nội dung tối đa 2000 ký tự.',
            'email.email' => 'Email không hợp lệ.',
            'phone.max' => 'Số điện thoại tối đa 30 ký tự.',
        ]);

        if (blank($validated['email'] ?? null) && blank($validated['phone'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => ['Vui lòng cung cấp email hoặc số điện thoại để admin tìm tài khoản của bạn.'],
            ]);
        }

        // 5 lần / 5 phút / IP.
        RateLimiter::hit('support-create:'.$request->ip(), 300);

        $supportRequest = SupportRequest::create([
            'public_token' => (string) Str::uuid(),
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'],
            'status' => 'open',
            'topic' => 'forgot_password',
        ]);

        SupportMessage::create([
            'support_request_id' => $supportRequest->id,
            'sender_type' => 'customer',
            'sender_name' => $validated['name'] ?? 'Khách hàng',
            'body' => $validated['message'],
        ]);

        return response()->json([
            'message' => 'Đã gửi yêu cầu. Admin sẽ phản hồi ngay trong khung chat này.',
            'data' => [
                'public_token' => $supportRequest->public_token,
                'created_at' => $supportRequest->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Lấy trạng thái + danh sách tin nhắn của 1 yêu cầu.
     */
    public function messages(Request $request, string $token): JsonResponse
    {
        $supportRequest = SupportRequest::where('public_token', $token)->first();

        if (! $supportRequest) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu.'], 404);
        }

        $messages = $supportRequest->messages()
            ->oldest('id')
            ->limit(200)
            ->get(['id', 'sender_type', 'sender_name', 'body', 'created_at']);

        return response()->json([
            'data' => [
                'request' => [
                    'status' => $supportRequest->status,
                    'created_at' => $supportRequest->created_at?->toIso8601String(),
                    'resolved_at' => $supportRequest->resolved_at?->toIso8601String(),
                ],
                'messages' => $messages->map(fn ($message) => [
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
     * Khách gửi tin nhắn tiếp trong luồng chat.
     */
    public function reply(Request $request, string $token): JsonResponse
    {
        $supportRequest = SupportRequest::where('public_token', $token)->first();

        if (! $supportRequest) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu.'], 404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Vui lòng nhập nội dung tin nhắn.',
            'body.max' => 'Tin nhắn tối đa 2000 ký tự.',
        ]);

        RateLimiter::hit('support-reply:'.$request->ip(), 60);

        $message = SupportMessage::create([
            'support_request_id' => $supportRequest->id,
            'sender_type' => 'customer',
            'sender_name' => $supportRequest->name ?? 'Khách hàng',
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
}
