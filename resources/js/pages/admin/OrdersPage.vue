<template>
    <div class="space-y-5">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-3">
            <input
                v-model="filters.order_code"
                type="search"
                placeholder="Tìm mã đơn hàng..."
                class="w-full sm:w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-500"
                @input="debouncedLoad"
            />

            <input
                v-model="filters.phone"
                type="search"
                placeholder="Số điện thoại"
                class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-500"
                @input="debouncedLoad"
            />

            <select
                v-model="filters.sync_status"
                class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                @change="load"
            >
                <option value="">Tất cả trạng thái</option>
                <option value="PENDING_SYNC">Chờ đồng bộ</option>
                <option value="SYNCING">Đang đồng bộ</option>
                <option value="SYNCED">Đã đồng bộ</option>
                <option value="SYNC_FAILED">Đồng bộ lỗi</option>
                <option value="CANCEL_PENDING">Chờ hủy</option>
                <option value="CANCELLED">Đã hủy</option>
                <option value="PROCESSING">Đang xử lý</option>
                <option value="SHIPPED">Đã gửi</option>
                <option value="COMPLETED">Hoàn tất</option>
            </select>

            <select
                v-model="filters.payment_status"
                class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                @change="load"
            >
                <option value="">Tất cả thanh toán</option>
                <option value="UNPAID">Chưa thanh toán</option>
                <option value="PAID">Đã thanh toán</option>
                <option value="REFUNDED">Đã hoàn tiền</option>
            </select>
        </div>

        <!-- Bảng -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :meta="meta"
            :loading="loading"
            empty-text="Chưa có đơn hàng nào."
            @page="changePage"
        >
            <template #cell-order_code="{ row }">
                <div>
                    <p class="font-mono font-semibold text-gray-900">{{ row.order_code }}</p>
                    <p v-if="row.bigseller_order_code" class="font-mono text-xs text-gray-400">
                        BS: {{ row.bigseller_order_code }}
                    </p>
                </div>
            </template>

            <template #cell-customer_name="{ row }">
                <div>
                    <p class="text-gray-900">{{ row.customer_name }}</p>
                    <p class="text-xs text-gray-500">{{ row.customer_phone }}</p>
                </div>
            </template>

            <template #cell-total_amount="{ row }">
                <span class="font-semibold text-gray-900">{{ formatCurrency(row.total_amount) }}</span>
            </template>

            <template #cell-sync_status="{ row }">
                <StatusBadge :status="row.sync_status" :label="row.sync_status_label" />
                <p v-if="row.sync_error" class="text-xs text-red-600 mt-1 max-w-xs truncate">
                    {{ row.sync_error }}
                </p>
            </template>

            <template #cell-payment_status="{ row }">
                <StatusBadge :status="row.payment_status" :label="row.payment_status_label" />
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-xs text-gray-600">{{ formatDate(row.created_at, true) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex flex-wrap gap-1">
                    <router-link
                        :to="{ name: 'admin-order-detail', params: { id: row.id } }"
                        class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                    >
                        Chi tiết
                    </router-link>
                    <button
                        v-if="row.sync_status === 'SYNC_FAILED' || row.sync_status === 'PENDING_SYNC'"
                        class="px-2 py-1 text-xs border border-amber-300 text-amber-700 rounded hover:bg-amber-50"
                        @click="resync(row)"
                    >
                        Thử lại
                    </button>
                    <button
                        v-if="!row.bigseller_order_code"
                        class="px-2 py-1 text-xs border border-blue-200 text-blue-700 rounded hover:bg-blue-50"
                        @click="push(row)"
                    >
                        Đẩy BigSeller
                    </button>
                    <button
                        v-if="!['CANCELLED', 'COMPLETED'].includes(row.sync_status)"
                        class="px-2 py-1 text-xs border border-red-200 text-red-600 rounded hover:bg-red-50"
                        @click="cancel(row)"
                    >
                        Hủy
                    </button>
                </div>
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
import { confirmDialog, notify } from '../../composables/useToast';

const columns = [
    { key: 'order_code', label: 'Mã đơn', primary: true },
    { key: 'customer_name', label: 'Khách hàng' },
    { key: 'total_amount', label: 'Tổng tiền' },
    { key: 'sync_status', label: 'Đồng bộ' },
    { key: 'payment_status', label: 'Thanh toán' },
    { key: 'created_at', label: 'Thời gian' },
    { key: 'actions', label: 'Thao tác' },
];

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);

const filters = reactive({
    order_code: '',
    phone: '',
    sync_status: '',
    payment_status: '',
    page: 1,
});

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get('/admin/orders', {
            params: {
                order_code: filters.order_code || undefined,
                phone: filters.phone || undefined,
                sync_status: filters.sync_status || undefined,
                payment_status: filters.payment_status || undefined,
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


async function resync(row) {
    try {
        const { data } = await api.post(`/admin/orders/${row.id}/resync`);
        notify(data.message, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function push(row) {
    try {
        const { data } = await api.post(`/admin/orders/${row.id}/push`);
        notify(data.message, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function cancel(row) {
    if (!(await confirmDialog(`Hủy đơn ${row.order_code}?`, { danger: true }))) {
        return;
    }

    try {
        const { data } = await api.post(`/admin/orders/${row.id}/cancel`);
        notify(data.message, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}


onMounted(load);
</script>
