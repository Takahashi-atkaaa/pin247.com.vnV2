<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <router-link
                    :to="{ name: 'admin-orders' }"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600 mb-1"
                >
                    ← Quay lại danh sách đơn hàng
                </router-link>
                <h1 class="text-xl font-bold text-gray-900">
                    Đơn {{ detail?.order_code || '—' }}
                </h1>
                <p v-if="detail?.created_at" class="text-sm text-gray-500 mt-0.5">
                    Tạo lúc {{ formatDate(detail.created_at, true) }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-if="detail && (detail.sync_status === 'SYNC_FAILED' || detail.sync_status === 'PENDING_SYNC')"
                    class="px-4 py-2 border border-amber-300 text-amber-700 rounded-lg text-sm font-medium hover:bg-amber-50"
                    @click="resync"
                >
                    Thử lại đồng bộ
                </button>
                <button
                    v-if="detail && !detail.bigseller_order_code"
                    class="px-4 py-2 border border-blue-200 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-50"
                    @click="push"
                >
                    Đẩy BigSeller
                </button>
                <button
                    v-if="detail && !['CANCELLED', 'COMPLETED'].includes(detail.sync_status)"
                    class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50"
                    @click="cancel"
                >
                    Hủy đơn
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-16 text-sm text-gray-500">
            Đang tải đơn hàng...
        </div>

        <template v-else-if="detail">
            <!-- Tổng quan -->
            <section class="grid sm:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Khách hàng</p>
                    <p class="font-medium text-gray-900">{{ detail.customer_name }}</p>
                    <p class="text-sm text-gray-600">{{ detail.customer_phone }}</p>
                    <p v-if="detail.customer_email" class="text-sm text-gray-600">{{ detail.customer_email }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Địa chỉ giao hàng</p>
                    <p class="text-sm text-gray-700">{{ detail.shipping_address }}</p>
                    <p class="text-sm text-gray-500">
                        {{ detail.ward }}, {{ detail.district }}, {{ detail.province }}
                    </p>
                </div>
            </section>

            <!-- Trạng thái -->
            <section class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Trạng thái đồng bộ</p>
                        <StatusBadge :status="detail.sync_status" :label="detail.sync_status_label" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Thanh toán</p>
                        <StatusBadge :status="detail.payment_status" :label="detail.payment_status_label" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Mã BigSeller</p>
                        <p class="font-mono text-sm">{{ detail.bigseller_order_code || '—' }}</p>
                    </div>
                </div>

                <p v-if="detail.sync_error" class="mt-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
                    {{ detail.sync_error }}
                </p>
            </section>

            <!-- Sản phẩm -->
            <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b border-gray-200">
                    <h2 class="font-semibold text-gray-900 text-sm">Sản phẩm</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Sản phẩm</th>
                                <th class="px-3 py-2 text-right font-semibold">SL</th>
                                <th class="px-3 py-2 text-right font-semibold">Giá</th>
                                <th class="px-3 py-2 text-right font-semibold">Tổng</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in detail.items || []" :key="item.id">
                                <td class="px-3 py-2">
                                    <p class="text-gray-900">{{ item.product_name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ item.variant_name }} • <span class="font-mono">{{ item.sku }}</span>
                                    </p>
                                </td>
                                <td class="px-3 py-2 text-right">{{ item.quantity }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 sm:px-5 py-4 border-t border-gray-100 space-y-1 text-sm text-right">
                    <p class="text-gray-600">Tạm tính: {{ formatCurrency(detail.subtotal) }}</p>
                    <p class="text-gray-600">Phí vận chuyển: {{ formatCurrency(detail.shipping_fee) }}</p>
                    <p v-if="detail.discount_amount > 0" class="text-red-600">
                        Giảm giá: -{{ formatCurrency(detail.discount_amount) }}
                    </p>
                    <p class="text-lg font-bold text-gray-900">
                        Tổng: {{ formatCurrency(detail.total_amount) }}
                    </p>
                </div>
            </section>

            <!-- Ghi chú nội bộ -->
            <section class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Ghi chú nội bộ</span>
                    <textarea
                        v-model="note"
                        rows="3"
                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        placeholder="Ghi chú chỉ hiển thị trong trang quản trị..."
                    ></textarea>
                </label>
                <div class="flex justify-end mt-2">
                    <button
                        class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700"
                        @click="saveNote"
                    >
                        Lưu ghi chú
                    </button>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import StatusBadge from '../../components/StatusBadge.vue';
import api from '../../services/api';
import { errorMessage, formatCurrency, formatDate } from '../../composables/useFormat';
import { confirmDialog, notify } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();

const detail = ref(null);
const note = ref('');
const loading = ref(false);

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get(`/admin/orders/${route.params.id}`);
        detail.value = data.data;
        note.value = data.data.internal_note || '';
    } catch (error) {
        notify(errorMessage(error), 'error');
        router.replace({ name: 'admin-orders' });
    } finally {
        loading.value = false;
    }
}

async function resync() {
    try {
        const { data } = await api.post(`/admin/orders/${detail.value.id}/resync`);
        notify(data.message, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function push() {
    try {
        const { data } = await api.post(`/admin/orders/${detail.value.id}/push`);
        notify(data.message, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function cancel() {
    if (!(await confirmDialog(`Hủy đơn ${detail.value.order_code}?`, { danger: true }))) {
        return;
    }

    try {
        const { data } = await api.post(`/admin/orders/${detail.value.id}/cancel`);
        notify(data.message, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function saveNote() {
    try {
        await api.put(`/admin/orders/${detail.value.id}/note`, { internal_note: note.value });
        notify('Đã lưu ghi chú nội bộ.', 'success');
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

onMounted(load);
</script>
