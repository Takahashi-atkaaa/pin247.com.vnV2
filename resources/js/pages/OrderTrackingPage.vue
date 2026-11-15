<template>
    <div class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Tra cứu đơn hàng</h1>

        <form class="bg-white border border-gray-200 rounded-xl p-5 mb-6" @submit.prevent="track">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mã đơn hàng *</label>
                    <input
                        v-model="form.order_code"
                        type="text"
                        required
                        placeholder="PIN247-20260101-ABC123"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition font-mono"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Số điện thoại *</label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        required
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                    />
                </div>
            </div>

            <button
                type="submit"
                :disabled="loading"
                class="mt-4 px-6 py-2.5 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 disabled:bg-gray-300 transition"
            >
                {{ loading ? 'Đang tra cứu...' : 'Tra cứu' }}
            </button>

            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
        </form>

        <!-- Kết quả -->
        <div v-if="order" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-gray-500">Mã đơn hàng</p>
                    <p class="font-mono font-bold text-gray-900">{{ order.order_code }}</p>
                </div>
                <div v-if="order.bigseller_order_code">
                    <p class="text-xs text-gray-500">Mã BigSeller</p>
                    <p class="font-mono font-bold text-brand-600">{{ order.bigseller_order_code }}</p>
                </div>
                <StatusBadge :status="order.sync_status" :label="order.sync_status_label" />
            </div>

            <div class="p-5 space-y-5">
                <!-- Sản phẩm -->
                <div>
                    <h3 class="font-semibold text-gray-900 mb-3 text-sm">Sản phẩm đã mua</h3>
                    <div
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex gap-3 py-3 border-b border-gray-100 last:border-0"
                    >
                        <div class="w-12 h-12 rounded bg-gray-50 overflow-hidden shrink-0">
                            <img v-if="item.image" :src="item.image" :alt="item.product_name" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900">{{ item.product_name }}</p>
                            <p v-if="item.variant_name" class="text-xs text-gray-500">{{ item.variant_name }}</p>
                            <p class="text-xs text-gray-400">SKU: {{ item.sku }} × {{ item.quantity }}</p>
                        </div>
                        <span class="text-sm font-medium shrink-0">{{ formatCurrency(item.subtotal) }}</span>
                    </div>
                </div>

                <!-- Tổng -->
                <div class="space-y-1.5 text-sm border-t border-gray-100 pt-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tạm tính</span>
                        <span>{{ formatCurrency(order.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Phí vận chuyển</span>
                        <span>{{ formatCurrency(order.shipping_fee) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-900 pt-1 border-t border-gray-100">
                        <span>Tổng tiền</span>
                        <span class="text-brand-600">{{ formatCurrency(order.total_amount) }}</span>
                    </div>
                </div>

                <!-- Trạng thái -->
                <div class="grid sm:grid-cols-3 gap-3 pt-2">
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Trạng thái đơn</p>
                        <p class="text-sm font-semibold text-gray-900">{{ order.sync_status_label }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Thanh toán</p>
                        <p class="text-sm font-semibold text-gray-900">{{ order.payment_status_label }}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Giao hàng</p>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ order.tracking_number || order.shipping_status || 'Chưa cập nhật' }}
                        </p>
                    </div>
                </div>

                <p class="text-xs text-gray-400">
                    Ngày đặt: {{ formatDate(order.created_at, true) }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import StatusBadge from '../components/StatusBadge.vue';
import api from '../services/api';
import { formatCurrency, formatDate, errorMessage } from '../composables/useFormat';

const form = reactive({ order_code: '', phone: '' });

const order = ref(null);
const loading = ref(false);
const error = ref('');

async function track() {
    loading.value = true;
    error.value = '';
    order.value = null;

    try {
        const { data } = await api.post('/orders/track', form);

        order.value = data.data;
    } catch (err) {
        error.value = errorMessage(err, 'Không tìm thấy đơn hàng.');
    } finally {
        loading.value = false;
    }
}
</script>
