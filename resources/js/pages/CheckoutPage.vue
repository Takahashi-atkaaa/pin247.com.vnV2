<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Thanh toán</h1>

        <div v-if="cart.isEmpty" class="text-center py-20">
            <p class="text-gray-500 mb-6">Giỏ hàng trống, không thể thanh toán.</p>
            <RouterLink
                :to="{ name: 'products' }"
                class="inline-block px-6 py-3 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 transition"
            >
                Mua sắm ngay
            </RouterLink>
        </div>

        <form v-else class="grid lg:grid-cols-[1fr_380px] gap-8" @submit.prevent="submit">
            <!-- Thông tin -->
            <div class="space-y-6">
                <section class="bg-white border border-gray-200 rounded-xl p-5">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <h2 class="font-semibold text-gray-900">Thông tin người nhận</h2>

                        <RouterLink
                            v-if="auth.isAuthenticated"
                            :to="{ name: 'account' }"
                            class="text-xs text-brand-600 hover:underline font-medium shrink-0"
                        >
                            Sửa trong tài khoản của tôi
                        </RouterLink>
                    </div>

                    <p
                        v-if="prefilled"
                        class="mb-4 text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2"
                    >
                        Đã tự động điền từ hồ sơ của bạn. Vui lòng kiểm tra lại trước khi đặt hàng.
                    </p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Họ tên *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                maxlength="255"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Số điện thoại *</label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                required
                                maxlength="30"
                                placeholder="09xxxxxxxx"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                            <input
                                v-model="form.email"
                                type="email"
                                maxlength="255"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Địa chỉ nhận hàng *</label>
                            <textarea
                                v-model="form.address"
                                required
                                maxlength="500"
                                rows="2"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition resize-none"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Phường/Xã</label>
                            <input
                                v-model="form.ward"
                                type="text"
                                maxlength="255"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Quận/Huyện</label>
                            <input
                                v-model="form.district"
                                type="text"
                                maxlength="255"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tỉnh/Thành phố</label>
                            <input
                                v-model="form.province"
                                type="text"
                                maxlength="255"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ghi chú</label>
                            <textarea
                                v-model="form.note"
                                maxlength="1000"
                                rows="2"
                                placeholder="Ghi chú thêm cho đơn hàng (không bắt buộc)"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition resize-none"
                            />
                        </div>
                    </div>
                </section>

                <!-- Phương thức thanh toán -->
                <section class="bg-white border border-gray-200 rounded-xl p-5">
                    <h2 class="font-semibold text-gray-900 mb-4">Phương thức thanh toán</h2>

                    <div class="space-y-3">
                        <label
                            v-for="method in paymentMethods"
                            :key="method.value"
                            class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition"
                            :class="form.payment_method === method.value
                                ? 'border-brand-500 bg-brand-50/40'
                                : 'border-gray-200 hover:border-gray-300'"
                        >
                            <input
                                v-model="form.payment_method"
                                type="radio"
                                :value="method.value"
                                class="mt-1 accent-brand-600"
                            />
                            <div>
                                <p class="font-medium text-gray-900 text-sm">{{ method.label }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ method.description }}</p>
                            </div>
                        </label>
                    </div>

                    <div
                        v-if="form.payment_method === 'BANK_TRANSFER'"
                        class="mt-4 p-4 bg-blue-50 rounded-lg text-sm text-blue-900"
                    >
                        <p class="font-medium mb-1">Thông tin chuyển khoản</p>
                        <p v-for="(line, index) in bankInfo" :key="index">{{ line }}</p>
                        <p class="mt-2 text-xs text-blue-700">
                            Đơn hàng sẽ ở trạng thái chờ thanh toán sau khi xác nhận chuyển khoản.
                        </p>
                    </div>
                </section>
            </div>

            <!-- Tổng đơn -->
            <aside class="lg:sticky lg:top-24 h-fit bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Đơn hàng ({{ cart.count }} sản phẩm)</h2>

                <div class="space-y-3 max-h-64 overflow-y-auto mb-4">
                    <div v-for="item in cart.items" :key="item.sku" class="flex gap-3 text-sm">
                        <div class="w-12 h-12 rounded bg-gray-50 overflow-hidden shrink-0">
                            <img v-if="item.image" :src="item.image" :alt="item.product_name" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-900 truncate">{{ item.product_name }}</p>
                            <p v-if="item.variant_name" class="text-xs text-gray-500">{{ item.variant_name }}</p>
                            <p class="text-xs text-gray-400">SKU: {{ item.sku }} × {{ item.quantity }}</p>
                        </div>
                        <span class="font-medium shrink-0">{{ formatCurrency(item.price * item.quantity) }}</span>
                    </div>
                </div>

                <div class="space-y-2 text-sm border-t border-gray-100 pt-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tạm tính</span>
                        <span class="font-medium">{{ formatCurrency(cart.total) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Phí vận chuyển</span>
                        <span class="font-medium">{{ formatCurrency(shippingFee) }}</span>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-4 mt-4 flex justify-between items-baseline">
                    <span class="font-semibold text-gray-900">Tổng cộng</span>
                    <span class="text-2xl font-bold text-brand-600">{{ formatCurrency(grandTotal) }}</span>
                </div>

                <p v-if="error" class="mt-3 text-sm text-red-600 bg-red-50 p-3 rounded">{{ error }}</p>

                <ul v-if="warnings.length" class="mt-3 space-y-1 text-xs text-amber-700 bg-amber-50 p-3 rounded">
                    <li v-for="(warning, index) in warnings" :key="index">{{ warning }}</li>
                </ul>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="mt-5 w-full px-6 py-3 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition"
                >
                    {{ submitting ? 'Đang xử lý...' : 'Đặt hàng' }}
                </button>

                <p class="mt-3 text-xs text-gray-400 text-center">
                    Đơn hàng sẽ được đồng bộ tới BigSeller để xử lý tập trung.
                </p>
            </aside>
        </form>

        <!-- Modal thành công -->
        <div v-if="success" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl max-w-md w-full p-6 text-center">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Đặt hàng thành công!</h2>
                <p class="text-sm text-gray-600 mb-1">Mã đơn hàng:</p>
                <p class="font-mono text-lg font-bold text-brand-600 mb-4">{{ success.order_code }}</p>
                <p v-if="success.bigseller_order_code" class="text-xs text-gray-500 mb-4">
                    Mã BigSeller: {{ success.bigseller_order_code }}
                </p>
                <p class="text-sm text-gray-600 mb-6">
                    Đơn hàng đang được đồng bộ và xử lý tập trung tại BigSeller.
                </p>
                <div class="flex gap-3">
                    <RouterLink
                        :to="{ name: 'order-tracking' }"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition"
                    >
                        Tra cứu đơn
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'home' }"
                        class="flex-1 px-4 py-2.5 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 transition"
                    >
                        Về trang chủ
                    </RouterLink>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import { formatCurrency, errorMessage } from '../composables/useFormat';

const router = useRouter();
const cart = useCartStore();
const auth = useAuthStore();

const submitting = ref(false);
const error = ref('');
const warnings = ref([]);
const success = ref(null);
const prefilled = ref(false);

const paymentMethods = [
    {
        value: 'COD',
        label: 'Thanh toán khi nhận hàng (COD)',
        description: 'Bạn thanh toán cho shipper khi nhận hàng.',
    },
    {
        value: 'BANK_TRANSFER',
        label: 'Chuyển khoản ngân hàng',
        description: 'Chuyển khoản trước, đơn hàng được xử lý sau khi xác nhận.',
    },
];

const bankInfo = [
    'Ngân hàng: Vietcombank',
    'Số tài khoản: 0123456789',
    'Chủ tài khoản: Công ty TNHH PIN247',
];

const form = reactive({
    name: '',
    phone: '',
    email: '',
    address: '',
    ward: '',
    district: '',
    province: '',
    note: '',
    payment_method: 'COD',
});

/**
 * Tự động điền thông tin người nhận từ hồ sơ khách hàng đã lưu.
 *
 * Chỉ điền những trường NGƯỜI DÙNG CHƯA nhập — không ghi đè giá trị họ
 * đã gõ (ví dụ khi quay lại trang thanh toán sau khi đổi ý).
 *
 * Nguồn: GET /api/account/profile (đã có sẵn, không cần endpoint mới).
 */
async function prefillFromProfile() {
    if (!auth.isAuthenticated) {
        return;
    }

    try {
        const { data } = await api.get('/account/profile');

        const profile = data.data?.profile || {};

        for (const field of ['name', 'phone', 'email', 'address', 'ward', 'district', 'province']) {
            if (!form[field] && profile[field]) {
                form[field] = profile[field];
                prefilled.value = true;
            }
        }
    } catch {
        // Không tải được hồ sơ thì khách tự nhập — không chặn thanh toán.
    }
}

onMounted(prefillFromProfile);

const shippingFee = computed(() => (cart.total >= 500000 ? 0 : 30000));

const grandTotal = computed(() => cart.total + shippingFee.value);

async function submit() {
    submitting.value = true;
    error.value = '';
    warnings.value = [];

    try {
        const { data } = await api.post('/checkout', {
            ...form,
            shipping_fee: shippingFee.value,
            items: cart.items.map((item) => ({
                sku: item.sku,
                quantity: item.quantity,
            })),
        });

        warnings.value = data.warnings || [];
        success.value = data.data;

        cart.clear();
    } catch (err) {
        error.value = errorMessage(err, 'Không thể đặt hàng. Vui lòng thử lại.');
    } finally {
        submitting.value = false;
    }
}
</script>
