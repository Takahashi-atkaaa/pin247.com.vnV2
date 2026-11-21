<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],

            // Không tin tưởng định danh người dùng từ payload.
            // `user_id` / `customer_id` của đơn luôn lấy từ tài khoản đã
            // xác thực (middleware `auth:sanctum`).
            'user_id' => ['prohibited'],
            'customer_id' => ['prohibited'],

            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s.\-()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'ward' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],

            'payment_method' => ['required', Rule::in(PaymentMethod::values())],

            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_code' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Giỏ hàng không có sản phẩm nào.',
            'items.*.sku.required' => 'Thiếu mã SKU sản phẩm.',
            'items.*.quantity.min' => 'Số lượng phải lớn hơn 0.',
            'user_id.prohibited' => 'Không được gửi user_id khi đặt hàng.',
            'customer_id.prohibited' => 'Không được gửu customer_id khi đặt hàng.',
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'email.email' => 'Email không hợp lệ.',
            'address.required' => 'Vui lòng nhập địa chỉ nhận hàng.',
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
        ];
    }

    /**
     * Chuẩn hóa dữ liệu trước khi validate.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => trim($this->input('phone'))]);
        }
    }
}
