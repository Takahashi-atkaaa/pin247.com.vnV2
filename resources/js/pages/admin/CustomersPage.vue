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
                <router-link
                    :to="{ name: 'admin-customer-detail', params: { id: row.id } }"
                    class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                >
                    Lịch sử
                </router-link>
            </template>
        </DataTable>

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


onMounted(load);
</script>
