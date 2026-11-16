<template>
    <div class="space-y-6">
        <!-- Cảnh báo mock -->
        <div v-if="isMock" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <div class="min-w-0">
                    <p class="font-semibold text-amber-900 text-sm">Đang chạy chế độ MOCK</p>
                    <p class="text-amber-800 text-sm mt-1">
                        Dữ liệu tồn kho và đơn hàng hiện KHÔNG đến từ BigSeller thật.
                        Cần cấu hình <code class="font-mono">BIGSELLER_MODE=live</code> cùng base URL,
                        endpoint và token từ tài liệu API chính thức để tích hợp production.
                    </p>
                </div>
            </div>
        </div>

        <!-- Chỉ số đơn hàng -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
            <StatCard
                v-for="card in orderCards"
                :key="card.label"
                :label="card.label"
                :value="card.value"
                :tone="card.tone"
                :hint="card.hint"
            />
        </div>

        <!-- Chỉ số danh mục & tồn kho -->
        <div class="grid lg:grid-cols-2 gap-4 sm:gap-6">
            <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Danh mục sản phẩm</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-600">Tổng sản phẩm</dt>
                        <dd class="font-semibold text-gray-900">{{ data.catalog?.total_products ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-600">Tổng biến thể</dt>
                        <dd class="font-semibold text-gray-900">{{ data.catalog?.total_variants ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-600">Tổng SKU</dt>
                        <dd class="font-semibold text-gray-900">{{ data.catalog?.total_skus ?? 0 }}</dd>
                    </div>
                    <div v-for="(count, source) in data.catalog?.by_source || {}" :key="source" class="flex justify-between text-sm">
                        <dt class="text-gray-600">{{ sourceLabel(source) }}</dt>
                        <dd class="font-semibold text-gray-900">{{ count }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Tồn kho</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-600">Sắp hết hàng (≤ {{ data.inventory?.low_stock_threshold }})</dt>
                        <dd class="font-semibold text-amber-600">{{ data.inventory?.low_stock_count ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-600">Hết hàng</dt>
                        <dd class="font-semibold text-red-600">{{ data.inventory?.out_of_stock_count ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-600">Tổng giá trị tồn kho</dt>
                        <dd class="font-semibold text-gray-900">
                            <template v-if="data.inventory?.total_value !== null && data.inventory?.total_value !== undefined">
                                {{ formatCurrency(data.inventory.total_value) }}
                            </template>
                            <span v-else class="text-gray-400 text-xs">Chưa có dữ liệu giá vốn</span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Doanh thu -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Doanh thu</h2>
            <div class="grid sm:grid-cols-3 gap-3 sm:gap-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Doanh thu website (tổng)</p>
                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(data.revenue?.website_total) }}</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Doanh thu website (tháng này)</p>
                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(data.revenue?.website_this_month) }}</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Doanh thu đa sàn</p>
                    <p class="text-xl font-bold text-gray-400">Chưa có nguồn dữ liệu</p>
                    <p class="text-xs text-gray-500 mt-1">{{ data.revenue?.note }}</p>
                </div>
            </div>
        </div>

        <!-- Trạng thái BigSeller -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <h2 class="font-semibold text-gray-900">Trạng thái BigSeller</h2>
                <RouterLink
                    :to="{ name: 'admin-bigseller' }"
                    class="text-sm text-brand-600 hover:underline font-medium"
                >
                    Quản lý đồng bộ →
                </RouterLink>
            </div>
            <div class="grid sm:grid-cols-3 gap-3 sm:gap-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Chế độ</p>
                    <p class="font-semibold" :class="isMock ? 'text-amber-600' : 'text-green-600'">
                        {{ isMock ? 'MOCK (không phải API thật)' : 'LIVE' }}
                    </p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Đơn chờ đồng bộ</p>
                    <p class="text-xl font-bold text-amber-600">{{ data.orders?.pending_sync ?? 0 }}</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Đơn đồng bộ lỗi</p>
                    <p class="text-xl font-bold text-red-600">{{ data.orders?.sync_failed ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import StatCard from '../../components/admin/StatCard.vue';
import api from '../../services/api';
import { formatCurrency } from '../../composables/useFormat';

const data = ref({});
const isMock = ref(false);

const orderCards = computed(() => [
    { label: 'Tổng đơn hàng', value: data.value.orders?.total ?? 0, tone: 'default' },
    { label: 'Đơn hàng mới', value: data.value.orders?.new_today ?? 0, tone: 'default', hint: 'Hôm nay' },
    { label: 'Chờ đồng bộ', value: data.value.orders?.pending_sync ?? 0, tone: 'warning' },
    { label: 'Đồng bộ lỗi', value: data.value.orders?.sync_failed ?? 0, tone: 'danger' },
    { label: 'Đã đồng bộ', value: data.value.orders?.synced ?? 0, tone: 'success' },
]);

function sourceLabel(source) {
    const map = {
        MANUAL: 'Quản lý trên website',
        BIGSELLER: 'Đồng bộ từ BigSeller',
        IMPORTED: 'Nhập từ CSV/Excel',
    };

    return map[source] || source;
}

onMounted(async () => {
    try {
        const { data: response } = await api.get('/admin/dashboard');
        data.value = response.data;
    } catch (error) {
        console.error(error);
    }

    try {
        const { data: bs } = await api.get('/admin/bigseller/status');
        isMock.value = bs.data.is_mock;
    } catch {
        isMock.value = false;
    }
});
</script>
