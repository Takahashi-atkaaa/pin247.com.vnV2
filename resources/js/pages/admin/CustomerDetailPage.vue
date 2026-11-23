<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <router-link
                    :to="{ name: 'admin-customers' }"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600 mb-1"
                >
                    ← Quay lại danh sách khách hàng
                </router-link>
                <h1 class="text-xl font-bold text-gray-900">
                    {{ detail?.name || 'Khách hàng' }}
                </h1>
                <p v-if="detail" class="text-sm text-gray-500 mt-0.5">
                    {{ detail.phone }}
                    <span v-if="detail.email"> · {{ detail.email }}</span>
                </p>
            </div>
        </div>

        <div v-if="loading" class="text-center py-16 text-sm text-gray-500">
            Đang tải thông tin khách hàng...
        </div>

        <template v-else-if="detail">
            <!-- Tổng quan -->
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Số đơn</p>
                    <p class="text-xl font-bold text-gray-900">{{ detail.total_orders }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Tổng chi tiêu</p>
                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(detail.total_spent) }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Thông tin liên hệ</p>
                    <p class="text-sm text-gray-700">{{ detail.phone }}</p>
                    <p class="text-xs text-gray-500">{{ detail.email || '—' }}</p>
                </div>
            </section>

            <!-- Địa chỉ -->
            <section v-if="detail.address" class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <h2 class="font-semibold text-gray-900 text-sm mb-2">Địa chỉ</h2>
                <p class="text-sm text-gray-700">{{ detail.address }}</p>
            </section>

            <!-- Lịch sử mua hàng -->
            <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b border-gray-200">
                    <h2 class="font-semibold text-gray-900 text-sm">Lịch sử mua hàng</h2>
                </div>

                <div v-if="!detail.orders?.length" class="text-center py-10 text-sm text-gray-500">
                    Khách hàng chưa có đơn hàng nào.
                </div>

                <ul v-else class="divide-y divide-gray-100">
                    <li v-for="order in detail.orders" :key="order.id" class="px-4 sm:px-5 py-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                            <div>
                                <span class="font-mono font-semibold text-gray-900">{{ order.order_code }}</span>
                                <span class="text-xs text-gray-500 ml-2">
                                    {{ formatDate(order.created_at, true) }}
                                </span>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <StatusBadge
                                    :status="order.sync_status"
                                    :label="order.sync_status_label"
                                />
                                <span class="font-semibold text-gray-900">
                                    {{ formatCurrency(order.total_amount) }}
                                </span>
                            </div>
                        </div>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li v-for="(item, idx) in order.items" :key="idx">
                                {{ item.product_name }}
                                <span v-if="item.variant_name" class="text-xs text-gray-400">
                                    ({{ item.variant_name }})
                                </span>
                                × {{ item.quantity }} — {{ formatCurrency(item.subtotal) }}
                            </li>
                        </ul>
                    </li>
                </ul>
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
import { notify } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();

const detail = ref(null);
const loading = ref(false);

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get(`/admin/customers/${route.params.id}`);
        detail.value = data.data;
    } catch (error) {
        notify(errorMessage(error), 'error');
        router.replace({ name: 'admin-customers' });
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
