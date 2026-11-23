<template>
    <div class="space-y-5">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-3">
            <input
                v-model="filters.search"
                type="search"
                placeholder="Tìm SKU hoặc tên sản phẩm..."
                class="w-full sm:flex-1 sm:min-w-[220px] px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-500"
                @input="debouncedLoad"
            />

            <select
                v-model="filters.sync_state"
                class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                @change="load"
            >
                <option value="">Tất cả trạng thái</option>
                <option value="PENDING">Chờ đồng bộ</option>
                <option value="SYNCED">Đã đồng bộ</option>
                <option value="FAILED">Lỗi</option>
                <option value="MISMATCH">Không khớp</option>
            </select>

            <label class="flex items-center gap-2 text-sm text-gray-700 px-3 py-2 border border-gray-300 rounded-lg">
                <input v-model="filters.low_stock" type="checkbox" class="rounded" @change="load">
                Sắp hết hàng
            </label>

            <button
                class="w-full sm:w-auto px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 transition"
                :disabled="syncing"
                @click="runSync"
            >
                {{ syncing ? 'Đang đồng bộ...' : 'Đồng bộ từ BigSeller' }}
            </button>
        </div>

        <!-- Bảng tồn kho -->
        <InventoryTable :rows="rows" title="Tồn kho SKU" @history="openHistory" />

        <!-- Phân trang -->
        <div
            v-if="meta && meta.last_page > 1"
            class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm"
        >
            <p class="text-gray-500">
                Trang {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} SKU
            </p>
            <div class="flex gap-1">
                <button
                    v-for="page in meta.last_page"
                    :key="page"
                    class="w-8 h-8 rounded text-sm"
                    :class="page === meta.current_page
                        ? 'bg-brand-600 text-white'
                        : 'border border-gray-300 text-gray-700 hover:border-brand-400'"
                    @click="changePage(page)"
                >
                    {{ page }}
                </button>
            </div>
        </div>

        <!-- Kho hàng -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Kho hàng & ánh xạ BigSeller</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Kho</th>
                            <th class="px-4 py-3 text-left font-semibold">Mã nội bộ</th>
                            <th class="px-4 py-3 text-left font-semibold">Mã BigSeller</th>
                            <th class="px-4 py-3 text-left font-semibold">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="w in warehouses" :key="w.id">
                            <td class="px-4 py-3 text-gray-900">{{ w.name }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ w.code }}</td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ w.bigseller?.code || '— chưa ánh xạ —' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="w.bigseller ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                                >
                                    {{ w.bigseller ? 'Đã ánh xạ' : 'Chưa ánh xạ' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="warehouseMeta?.default_warehouse" class="text-xs text-gray-500 mt-3">
                Kho mặc định dùng cho đơn đẩy lên BigSeller:
                <span class="font-mono font-semibold">{{ warehouseMeta.default_warehouse }}</span>
            </p>
        </div>

    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import InventoryTable from '../../components/admin/InventoryTable.vue';
import api from '../../services/api';
import { errorMessage, formatDate } from '../../composables/useFormat';
import { notify } from '../../composables/useToast';

const router = useRouter();

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const syncing = ref(false);
const warehouses = ref([]);
const warehouseMeta = ref(null);

const filters = reactive({
    search: '',
    sync_state: '',
    low_stock: false,
    page: 1,
});

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get('/admin/inventory', {
            params: {
                search: filters.search || undefined,
                sync_state: filters.sync_state || undefined,
                low_stock: filters.low_stock ? 1 : undefined,
                page: filters.page,
                per_page: 30,
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

async function runSync() {
    syncing.value = true;

    try {
        const { data } = await api.post('/admin/inventory/sync');
        notify(data.message || 'Đã đồng bộ tồn kho.', 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error, 'Đồng bộ tồn kho thất bại.'), 'error');
    } finally {
        syncing.value = false;
    }
}

function openHistory(row) {
    router.push({
        name: 'admin-inventory-history',
        params: { sku: row.sku },
    });
}

onMounted(async () => {
    await load();

    try {
        const { data } = await api.get('/admin/inventory/warehouses');
        warehouses.value = data.data || [];
        warehouseMeta.value = data.meta || null;
    } catch {
        warehouses.value = [];
    }
});
</script>
