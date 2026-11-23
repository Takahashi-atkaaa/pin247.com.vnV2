<template>
    <div class="space-y-5">
        <!-- Trạng thái kết nối -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center justify-between gap-3 mb-4">
                <h2 class="font-semibold text-gray-900">Kết nối BigSeller</h2>
                <div class="flex gap-2">
                    <button
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 disabled:opacity-50"
                        :disabled="busy"
                        @click="checkConnection"
                    >
                        Kiểm tra kết nối
                    </button>
                </div>
            </div>

            <div v-if="!status" class="text-sm text-gray-500">Đang tải trạng thái...</div>

            <template v-else>
                <!-- Cảnh báo mock -->
                <div
                    v-if="status.is_mock"
                    class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-900"
                >
                    <p class="font-semibold">Chế độ MOCK — dữ liệu không phải từ BigSeller thật</p>
                    <p class="mt-1">
                        Để tích hợp production cần: <code class="font-mono">BIGSELLER_MODE=live</code>,
                        <code class="font-mono">BIGSELLER_BASE_URL</code>,
                        <code class="font-mono">BIGSELLER_ACCESS_TOKEN</code> và các endpoint
                        chính thức từ tài liệu Open API BigSeller.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Chế độ</p>
                        <p class="font-semibold" :class="status.is_mock ? 'text-amber-600' : 'text-green-600'">
                            {{ status.mode }}
                        </p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Trạng thái</p>
                        <p class="font-semibold" :class="status.success ? 'text-green-600' : 'text-red-600'">
                            {{ status.success ? 'Sẵn sàng' : 'Có vấn đề' }}
                        </p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Shop</p>
                        <p class="font-medium text-gray-900">
                            {{ status.connection?.shop_name || status.connection?.shop_code || '—' }}
                        </p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Token hết hạn</p>
                        <p
                            class="font-semibold"
                            :class="status.connection?.is_token_expired ? 'text-red-600' : 'text-green-600'"
                        >
                            {{ status.connection?.is_token_expired ? 'Đã hết hạn' : 'Còn hiệu lực' }}
                        </p>
                        <p v-if="status.connection?.token_expires_at" class="text-xs text-gray-500 mt-1">
                            {{ formatDate(status.connection.token_expires_at, true) }}
                        </p>
                    </div>
                </div>

                <p v-if="status.connection?.last_error" class="mt-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
                    {{ status.connection.last_error }}
                </p>

                <!-- Quyền API -->
                <div class="mt-5">
                    <h3 class="font-semibold text-gray-900 text-sm mb-2">Quyền API (capabilities)</h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="cap in capabilities"
                            :key="cap"
                            class="px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="status.capabilities?.includes(cap)
                                ? 'bg-green-50 text-green-700'
                                : 'bg-gray-100 text-gray-500'"
                        >
                            {{ cap }}
                        </span>
                    </div>
                    <p v-if="status.missing_capabilities?.length" class="text-xs text-amber-600 mt-2">
                        Thiếu quyền: {{ status.missing_capabilities.join(', ') }}
                    </p>
                </div>

                <!-- Endpoint đã cấu hình -->
                <div class="mt-5">
                    <h3 class="font-semibold text-gray-900 text-sm mb-2">Endpoint đang cấu hình</h3>
                    <ul class="space-y-1 text-xs font-mono text-gray-600">
                        <li v-for="(url, name) in status.configured_endpoints" :key="name">
                            <span class="text-gray-400">{{ name }}:</span> {{ url || '(chưa cấu hình)' }}
                        </li>
                    </ul>
                </div>
            </template>
        </div>

        <!-- Thao tác đồng bộ -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Đồng bộ thủ công</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <button
                    class="p-4 border border-gray-200 rounded-lg text-left hover:border-brand-400 transition disabled:opacity-50"
                    :disabled="busy"
                    @click="sync('inventory')"
                >
                    <p class="font-medium text-gray-900 text-sm">Tồn kho</p>
                    <p class="text-xs text-gray-500 mt-1">Kéo số lượng tồn từ BigSeller</p>
                </button>

                <button
                    class="p-4 border border-gray-200 rounded-lg text-left hover:border-brand-400 transition disabled:opacity-50"
                    :disabled="busy"
                    @click="sync('products')"
                >
                    <p class="font-medium text-gray-900 text-sm">Sản phẩm</p>
                    <p class="text-xs text-gray-500 mt-1">Đồng bộ danh mục & SKU</p>
                </button>

                <button
                    class="p-4 border border-gray-200 rounded-lg text-left hover:border-brand-400 transition disabled:opacity-50"
                    :disabled="busy"
                    @click="syncOrders"
                >
                    <p class="font-medium text-gray-900 text-sm">Đơn hàng</p>
                    <p class="text-xs text-gray-500 mt-1">Đẩy đơn chờ/lỗi lên BigSeller</p>
                </button>

                <button
                    class="p-4 border border-gray-200 rounded-lg text-left hover:border-brand-400 transition disabled:opacity-50"
                    :disabled="busy"
                    @click="reconcile"
                >
                    <p class="font-medium text-gray-900 text-sm">Đối chiếu SKU</p>
                    <p class="text-xs text-gray-500 mt-1">So khớp website ↔ BigSeller</p>
                </button>
            </div>
        </div>

        <!-- Đối chiếu SKU -->
        <div v-if="reconciliation" class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Kết quả đối chiếu SKU</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4">
                <div class="p-4 bg-green-50 rounded-lg">
                    <p class="text-xs text-green-700 mb-1">Khớp</p>
                    <p class="text-xl font-bold text-green-700">{{ reconciliation.matched_count }}</p>
                </div>
                <div class="p-4 bg-amber-50 rounded-lg">
                    <p class="text-xs text-amber-700 mb-1">Chỉ có trên website</p>
                    <p class="text-xl font-bold text-amber-700">{{ reconciliation.website_only_count }}</p>
                </div>
                <div class="p-4 bg-red-50 rounded-lg">
                    <p class="text-xs text-red-700 mb-1">Chỉ có trên BigSeller</p>
                    <p class="text-xl font-bold text-red-700">{{ reconciliation.bigseller_only_count }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <h3 class="font-medium text-gray-900 text-xs uppercase mb-2">Khớp</h3>
                    <ul class="space-y-1 font-mono text-xs text-gray-600">
                        <li v-for="item in reconciliation.matched" :key="item">{{ item }}</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900 text-xs uppercase mb-2">Chỉ có ở website</h3>
                    <ul class="space-y-1 font-mono text-xs text-gray-600">
                        <li v-for="item in reconciliation.website_only" :key="item">{{ item }}</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900 text-xs uppercase mb-2">Chỉ có ở BigSeller</h3>
                    <ul class="space-y-1 font-mono text-xs text-gray-600">
                        <li v-for="item in reconciliation.bigseller_only" :key="item">{{ item }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Cấu hình lịch -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Cấu hình lịch đồng bộ</h2>
            <div class="grid sm:grid-cols-2 gap-4 max-w-lg">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Đồng bộ tồn kho mỗi (phút)</span>
                    <input
                        v-model.number="schedule.inventory_sync_every_minutes"
                        type="number"
                        min="1"
                        max="1440"
                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Tồn kho an toàn (mặc định)</span>
                    <input
                        v-model.number="schedule.safety_stock"
                        type="number"
                        min="0"
                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                </label>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ schedule.scheduler_hint }}</p>
            <div class="mt-4">
                <button
                    class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700"
                    @click="saveSchedule"
                >
                    Lưu cấu hình
                </button>
            </div>
        </div>

        <!-- Ánh xạ SKU -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <h2 class="font-semibold text-gray-900">Ánh xạ SKU thủ công</h2>
                <button class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50" @click="loadMappings">
                    Tải lại
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mb-4">
                <input
                    v-model="mapping.bigseller_sku"
                    placeholder="SKU BigSeller *"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono"
                >
                <input
                    v-model="mapping.product_name"
                    placeholder="Tên sản phẩm"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                >
                <input
                    v-model.number="mapping.price"
                    type="number"
                    placeholder="Giá"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                >
                <button
                    class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700"
                    @click="saveMapping"
                >
                    Thêm ánh xạ
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">SKU BigSeller</th>
                            <th class="px-4 py-3 text-left font-semibold">Sản phẩm website</th>
                            <th class="px-4 py-3 text-left font-semibold">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="m in mappings" :key="m.id">
                            <td class="px-4 py-3 font-mono text-xs">{{ m.bigseller_sku }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ m.product?.name || m.product_name || '—' }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="m.sync_state" :label="m.sync_state_label" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lịch sử đồng bộ -->
        <div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                <h2 class="font-semibold text-gray-900">Lịch sử đồng bộ</h2>
                <div class="flex gap-2">
                    <select v-model="logFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white" @change="loadLogs">
                        <option value="">Tất cả thao tác</option>
                        <option value="getInventoryListDetail">getInventoryListDetail</option>
                        <option value="createManualOrder">createManualOrder</option>
                        <option value="cancelOrder">cancelOrder</option>
                        <option value="getOrderStatus">getOrderStatus</option>
                    </select>
                    <button class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50" @click="loadLogs">
                        Tải lại
                    </button>
                </div>
            </div>

            <SyncLogTable :rows="logs" />
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import StatusBadge from '../../components/StatusBadge.vue';
import SyncLogTable from '../../components/admin/SyncLogTable.vue';
import api from '../../services/api';
import { errorMessage, formatDate } from '../../composables/useFormat';
import { notify } from '../../composables/useToast';

const capabilities = ['inventory', 'create_order', 'cancel_order', 'order_status', 'products', 'warehouses'];

const status = ref(null);
const logs = ref([]);
const mappings = ref([]);
const reconciliation = ref(null);
const busy = ref(false);
const logFilter = ref('');

const schedule = reactive({
    inventory_sync_every_minutes: 15,
    safety_stock: 0,
    scheduler_hint: '',
});

const mapping = reactive({
    bigseller_sku: '',
    product_name: '',
    price: null,
});

async function loadStatus() {
    try {
        const { data } = await api.get('/admin/bigseller/status');
        status.value = data.data;
    } catch (error) {
        console.error(error);
    }
}

async function checkConnection() {
    busy.value = true;

    try {
        const { data } = await api.post('/admin/bigseller/check-connection');
        notify(data.message, 'success');
        await loadStatus();
    } catch (error) {
        notify(errorMessage(error, 'Không thể kiểm tra kết nối.'), 'error');
        await loadStatus();
    } finally {
        busy.value = false;
    }
}

async function sync(type) {
    busy.value = true;

    const endpoints = {
        inventory: '/admin/bigseller/sync/inventory',
        products: '/admin/bigseller/sync/products',
    };

    try {
        const { data } = await api.post(endpoints[type]);
        notify(data.message || 'Đã đồng bộ.', 'success');
        await loadStatus();
    } catch (error) {
        notify(errorMessage(error), 'error');
    } finally {
        busy.value = false;
    }
}

async function syncOrders() {
    busy.value = true;

    try {
        const { data } = await api.post('/admin/bigseller/sync/orders', { as_job: true });
        notify(data.message, 'success');
    } catch (error) {
        notify(errorMessage(error), 'error');
    } finally {
        busy.value = false;
    }
}

async function reconcile() {
    busy.value = true;

    try {
        const { data } = await api.get('/admin/bigseller/reconcile-skus');
        reconciliation.value = data.data;
    } catch (error) {
        notify(errorMessage(error), 'error');
    } finally {
        busy.value = false;
    }
}

async function loadSchedule() {
    try {
        const { data } = await api.get('/admin/bigseller/schedule');
        schedule.inventory_sync_every_minutes = data.data.inventory_sync_every_minutes;
        schedule.safety_stock = data.data.safety_stock;
        schedule.scheduler_hint = data.data.scheduler_hint;
    } catch {
        // giữ mặc định
    }
}

async function saveSchedule() {
    try {
        await api.put('/admin/bigseller/schedule', {
            inventory_sync_every_minutes: schedule.inventory_sync_every_minutes,
            safety_stock: schedule.safety_stock,
        });

        notify('Đã lưu cấu hình đồng bộ.', 'success');
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function loadMappings() {
    try {
        const { data } = await api.get('/admin/bigseller/sku-mappings');
        mappings.value = data.data || [];
    } catch {
        mappings.value = [];
    }
}

async function saveMapping() {
    try {
        await api.post('/admin/bigseller/sku-mappings', {
            bigseller_sku: mapping.bigseller_sku,
            product_name: mapping.product_name || null,
            price: mapping.price || null,
        });

        mapping.bigseller_sku = '';
        mapping.product_name = '';
        mapping.price = null;

        await loadMappings();
        notify('Đã tạo ánh xạ SKU.', 'success');
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function loadLogs() {
    try {
        const { data } = await api.get('/admin/bigseller/logs', {
            params: { operation: logFilter.value || undefined, limit: 50 },
        });

        logs.value = data.data || [];
    } catch {
        logs.value = [];
    }
}

onMounted(async () => {
    await loadStatus();
    await loadSchedule();
    await loadMappings();
    await loadLogs();
});
</script>
