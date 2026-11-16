<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
            <input
                v-model="filters.search"
                type="search"
                placeholder="Tìm tên, SĐT hoặc email..."
                class="w-full sm:flex-1 sm:min-w-[240px] px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-500"
                @input="debouncedLoad"
            />
        </div>

        <DataTable
            :columns="columns"
            :rows="rows"
            :meta="meta"
            :loading="loading"
            empty-text="Chưa có khách hàng nào."
            @page="changePage"
        >
            <template #cell-name="{ row }">
                <div>
                    <p class="font-medium text-gray-900">{{ row.name }}</p>
                    <p class="text-xs text-gray-500">{{ row.email || '—' }}</p>
                </div>
            </template>

            <template #cell-phone="{ row }">
                <span class="text-gray-700">{{ row.phone }}</span>
            </template>

            <template #cell-address="{ row }">
                <span class="text-xs text-gray-600">{{ row.address || '—' }} {{ row.province || '' }}</span>
            </template>

            <template #cell-total_orders="{ row }">
                <span class="font-semibold text-gray-900">{{ row.total_orders }}</span>
            </template>

            <template #cell-total_spent="{ row }">
                <span class="font-semibold text-gray-900">{{ formatCurrency(row.total_spent) }}</span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-xs text-gray-600">{{ formatDate(row.created_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <button
                    class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                    @click="openDetail(row)"
                >
                    Lịch sử
                </button>
            </template>
        </DataTable>

        <!-- Modal lịch sử mua hàng -->
        <Teleport to="body">
            <div
                v-if="detail"
                class="fixed inset-0 bg-black/40 z-50 flex items-start justify-center overflow-y-auto py-0 sm:py-8"
                @click.self="detail = null"
            >
                <div class="bg-white shadow-xl w-full max-w-3xl rounded-t-2xl sm:rounded-xl sm:my-auto my-0">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">
                            Lịch sử mua hàng — {{ detail.name }}
                        </h3>
                        <button class="text-gray-400 hover:text-gray-700" @click="detail = null">✕</button>
                    </div>

                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Số đơn</p>
                                <p class="text-xl font-bold text-gray-900">{{ detail.total_orders }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Tổng chi tiêu</p>
                                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(detail.total_spent) }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Thông tin liên hệ</p>
                                <p class="text-sm text-gray-700">{{ detail.phone }}</p>
                                <p class="text-xs text-gray-500">{{ detail.email || '—' }}</p>
                            </div>
                        </div>

                        <div v-if="!detail.orders?.length" class="text-center py-10 text-sm text-gray-500">
                            Khách hàng chưa có đơn hàng nào.
                        </div>

                        <div v-for="order in detail.orders || []" :key="order.id" class="border border-gray-200 rounded-lg p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                <div>
                                    <span class="font-mono font-semibold text-gray-900">{{ order.order_code }}</span>
                                    <span class="text-xs text-gray-500 ml-2">{{ formatDate(order.created_at, true) }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <StatusBadge :status="order.sync_status" :label="order.sync_status_label" />
                                    <span class="font-semibold text-gray-900">{{ formatCurrency(order.total_amount) }}</span>
                                </div>
                            </div>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li v-for="(item, idx) in order.items" :key="idx">
                                    {{ item.product_name }}
                                    <span v-if="item.variant_name" class="text-xs text-gray-400">({{ item.variant_name }})</span>
                                    × {{ item.quantity }} — {{ formatCurrency(item.subtotal) }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import DataTable from '../../components/admin/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import api from '../../services/api';
import { errorMessage, formatCurrency, formatDate } from '../../composables/useFormat';

const columns = [
    { key: 'name', label: 'Khách hàng', primary: true },
    { key: 'phone', label: 'Điện thoại' },
    { key: 'address', label: 'Địa chỉ' },
    { key: 'total_orders', label: 'Số đơn' },
    { key: 'total_spent', label: 'Tổng chi tiêu' },
    { key: 'created_at', label: 'Ngày tạo' },
    { key: 'actions', label: 'Thao tác' },
];

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const detail = ref(null);

const filters = reactive({
    search: '',
    page: 1,
});

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get('/admin/customers', {
            params: {
                search: filters.search || undefined,
                page: filters.page,
                per_page: 20,
            },
        });

        rows.value = data.data || [];
        meta.value = data.meta || null;
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
}

let timer = null;

function debouncedLoad() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        filters.page = 1;
        load();
    }, 350);
}

function changePage(page) {
    filters.page = page;
    load();
}

async function openDetail(row) {
    try {
        const { data } = await api.get(`/admin/customers/${row.id}`);
        detail.value = data.data;
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

onMounted(load);
</script>
