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
                    <button class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                        @click="openDetail(row)">
                        Chi tiết
                    </button>
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

        <!-- Modal chi tiết -->
        <Teleport to="body">
            <div
                v-if="detail"
                class="fixed inset-0 bg-black/40 z-50 flex items-start justify-center overflow-y-auto py-0 sm:py-8"
                @click.self="detail = null"
            >
                <div class="bg-white shadow-xl w-full max-w-3xl rounded-t-2xl sm:rounded-xl sm:my-auto my-0">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">
                            Đơn {{ detail.order_code }}
                        </h3>
                        <button class="text-gray-400 hover:text-gray-700" @click="detail = null">✕</button>
                    </div>

                    <div class="p-4 sm:p-6 space-y-5">
                        <!-- Tổng quan -->
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500 mb-1">Khách hàng</p>
                                <p class="font-medium text-gray-900">{{ detail.customer_name }}</p>
                                <p class="text-sm text-gray-600">{{ detail.customer_phone }}</p>
                                <p v-if="detail.customer_email" class="text-sm text-gray-600">{{ detail.customer_email }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500 mb-1">Địa chỉ giao hàng</p>
                                <p class="text-sm text-gray-700">{{ detail.shipping_address }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ detail.ward }}, {{ detail.district }}, {{ detail.province }}
                                </p>
                            </div>
                        </div>

                        <!-- Trạng thái -->
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

                        <p v-if="detail.sync_error" class="p-3 bg-red-50 text-red-700 text-sm rounded-lg">
                            {{ detail.sync_error }}
                        </p>

                        <!-- Sản phẩm -->
                        <div>
                            <h4 class="font-semibold text-gray-900 text-sm mb-2">Sản phẩm</h4>
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
                            <div class="mt-3 space-y-1 text-sm text-right">
                                <p class="text-gray-600">Tạm tính: {{ formatCurrency(detail.subtotal) }}</p>
                                <p class="text-gray-600">Phí vận chuyển: {{ formatCurrency(detail.shipping_fee) }}</p>
                                <p v-if="detail.discount_amount > 0" class="text-red-600">
                                    Giảm giá: -{{ formatCurrency(detail.discount_amount) }}
                                </p>
                                <p class="text-lg font-bold text-gray-900">
                                    Tổng: {{ formatCurrency(detail.total_amount) }}
                                </p>
                            </div>
                        </div>

                        <!-- Ghi chú nội bộ -->
                        <div>
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
const detail = ref(null);
const note = ref('');

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

async function openDetail(row) {
    try {
        const { data } = await api.get(`/admin/orders/${row.id}`);
        detail.value = data.data;
        note.value = data.data.internal_note || '';
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

async function resync(row) {
    try {
        const { data } = await api.post(`/admin/orders/${row.id}/resync`);
        window.alert(data.message);
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

async function push(row) {
    try {
        const { data } = await api.post(`/admin/orders/${row.id}/push`);
        window.alert(data.message);
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

async function cancel(row) {
    if (!window.confirm(`Hủy đơn ${row.order_code}?`)) {
        return;
    }

    try {
        const { data } = await api.post(`/admin/orders/${row.id}/cancel`);
        window.alert(data.message);
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

async function saveNote() {
    try {
        await api.put(`/admin/orders/${detail.value.id}/note`, { internal_note: note.value });
        window.alert('Đã lưu ghi chú nội bộ.');
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

onMounted(load);
</script>
