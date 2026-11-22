<template>
    <div class="space-y-5">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <button
                class="hover:text-brand-600"
                @click="$router.push({ name: 'admin-products' })"
            >
                ← Quay lại danh sách
            </button>
            <span>/</span>
            <span class="text-gray-900 font-medium">{{ product?.name || 'Sản phẩm' }}</span>
        </div>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-16 bg-gray-100 rounded animate-pulse" />
        </div>

        <div v-else-if="!variant" class="text-center py-12 text-gray-500">
            Không tìm thấy biến thể.
        </div>

        <div v-else class="space-y-5">
            <!-- Header -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900">{{ variant.name }}</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            SKU: <span class="font-mono">{{ variant.sku }}</span>
                        </p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span
                                class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="variant.is_active
                                    ? 'bg-green-50 text-green-700'
                                    : 'bg-gray-100 text-gray-600'"
                            >
                                {{ variant.is_active ? 'Đang bán' : 'Ẩn' }}
                            </span>
                            <span
                                v-for="(pair, index) in optionPairs"
                                :key="index"
                                class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700"
                            >
                                {{ pair.name }}: <strong>{{ pair.value }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="text-left sm:text-right">
                        <p class="text-xs text-gray-500">Giá bán</p>
                        <p class="text-xl font-bold text-gray-900">{{ formatCurrency(variant.effective_price) }}</p>
                        <p v-if="variant.sale_price !== null" class="text-xs text-gray-400 line-through">
                            {{ formatCurrency(variant.price) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Giá & trạng thái -->
            <div class="grid sm:grid-cols-2 gap-5">
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Thông tin bán hàng</h3>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="text-xs font-medium text-gray-700">Tên biến thể</span>
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            >
                        </label>

                        <div class="grid grid-cols-3 gap-3">
                            <label class="block">
                                <span class="text-xs font-medium text-gray-700">Giá bán</span>
                                <input
                                    v-model.number="form.price"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                >
                            </label>

                            <label class="block">
                                <span class="text-xs font-medium text-gray-700">Giá so sánh</span>
                                <input
                                    v-model.number="form.sale_price"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                >
                            </label>

                            <label class="block">
                                <span class="text-xs font-medium text-gray-700">Giá vốn</span>
                                <input
                                    v-model.number="form.cost_price"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                >
                            </label>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded">
                            Đang bán trên website
                        </label>

                        <button
                            type="button"
                            :disabled="saving"
                            class="w-full px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50"
                            @click="save"
                        >
                            {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </button>
                    </div>
                </div>

                <!-- Thẻ kho -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Thẻ kho biến thể</h3>

                    <dl class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <dt class="text-xs text-gray-500">Số tồn</dt>
                            <dd class="text-lg font-semibold text-gray-900 mt-0.5">
                                {{ inventory.available_stock }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <dt class="text-xs text-gray-500">Số đặt</dt>
                            <dd class="text-lg font-semibold text-gray-900 mt-0.5">
                                {{ inventory.reserved_stock }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <dt class="text-xs text-gray-500">Số tồn cuối</dt>
                            <dd class="text-lg font-semibold text-gray-900 mt-0.5">
                                {{ inventory.on_hand_stock }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <dt class="text-xs text-gray-500">Số khả dụng cuối</dt>
                            <dd
                                class="text-lg font-semibold mt-0.5"
                                :class="inventory.sellable_quantity > 0 ? 'text-green-600' : 'text-red-500'"
                            >
                                {{ inventory.sellable_quantity }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <dt class="text-xs text-gray-500">Tồn an toàn</dt>
                            <dd class="text-lg font-semibold text-gray-900 mt-0.5">
                                {{ inventory.safety_stock }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <dt class="text-xs text-gray-500">Trạng thái sync</dt>
                            <dd class="text-sm font-semibold text-gray-900 mt-1">
                                {{ inventory.sync_state_label || '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Đơn vị tính -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Đơn vị tính</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Đơn vị cơ bản = giá của biến thể. Đơn vị quy đổi có giá tự tính.
                        </p>
                    </div>
                </div>

                <ProductUnitsEditor
                    :units="units"
                    :base-price="variant.effective_price"
                />

                <button
                    type="button"
                    :disabled="saving"
                    class="mt-4 px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50"
                    @click="saveUnits"
                >
                    {{ saving ? 'Đang lưu...' : 'Lưu đơn vị tính' }}
                </button>
            </div>

            <!-- Lịch sử cập nhật -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Lịch sử cập nhật</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Ghi lại mọi thay đổi về giá, SKU và trạng thái của biến thể.
                    </p>
                </div>

                <div v-if="!histories.length" class="text-sm text-gray-500 text-center py-8">
                    Chưa có thay đổi nào được ghi nhận.
                </div>

                <ul v-else class="divide-y divide-gray-100">
                    <li v-for="history in histories" :key="history.id" class="px-4 sm:px-5 py-3">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1">
                            <p class="text-sm text-gray-900">{{ history.summary }}</p>
                            <p class="text-xs text-gray-400 shrink-0">
                                {{ formatDate(history.created_at, true) }}
                                <span v-if="history.changed_by"> · {{ history.changed_by }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                            <span
                                v-for="field in history.changed_fields"
                                :key="field"
                                class="px-1.5 py-0.5 rounded text-[11px] bg-gray-100 text-gray-600"
                            >
                                {{ fieldLabels[field] || field }}
                            </span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import ProductUnitsEditor from '../../components/admin/ProductUnitsEditor.vue';
import api from '../../services/api';
import { errorMessage, formatCurrency, formatDate } from '../../composables/useFormat';

const route = useRoute();

const product = ref(null);
const variant = ref(null);
const histories = ref([]);
const units = ref([]);
const loading = ref(true);
const saving = ref(false);

const form = reactive({
    name: '',
    price: 0,
    sale_price: null,
    cost_price: null,
    is_active: true,
});

const fieldLabels = {
    price: 'Giá bán',
    sale_price: 'Giá so sánh',
    cost_price: 'Giá vốn',
    sku: 'SKU',
    name: 'Tên',
    is_active: 'Trạng thái',
    image: 'Ảnh',
};

const inventory = computed(() => variant.value?.inventory || {
    available_stock: 0,
    reserved_stock: 0,
    on_hand_stock: 0,
    safety_stock: 0,
    sellable_quantity: 0,
});

const optionPairs = computed(() => {
    if (!variant.value) {
        return [];
    }

    return [
        { name: variant.value.option_1_name, value: variant.value.option_1_value },
        { name: variant.value.option_2_name, value: variant.value.option_2_value },
        { name: variant.value.option_3_name, value: variant.value.option_3_value },
    ].filter((pair) => pair.name && pair.value);
});

async function load() {
    loading.value = true;

    try {
        const productId = route.params.id;
        const variantId = route.params.variantId;

        const { data: productData } = await api.get(`/admin/products/${productId}`);
        product.value = productData.data;

        const { data: variantData } = await api.get(
            `/admin/products/${productId}/variants/${variantId}`,
        );
        variant.value = variantData.data;

        Object.assign(form, {
            name: variant.value.name || '',
            price: variant.value.price ?? 0,
            sale_price: variant.value.sale_price ?? null,
            cost_price: variant.value.cost_price ?? null,
            is_active: !!variant.value.is_active,
        });

        // Nạp đơn vị tính (kèm cờ "không sửa tỷ lệ" cho đơn vị đã tồn tại).
        units.value = (variant.value.units || []).map((unit) => ({
            ...unit,
            _key: `u${unit.id}`,
            locked: true,
        }));

        const { data: historyData } = await api.get(
            `/admin/products/${productId}/variants/${variantId}/history`,
        );
        histories.value = historyData.data || [];
    } catch (error) {
        window.alert(errorMessage(error));
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;

    try {
        const { data } = await api.put(
            `/admin/products/${product.value.id}/variants/${variant.value.id}`,
            {
                name: form.name,
                price: form.price,
                sale_price: form.sale_price,
                cost_price: form.cost_price,
                is_active: form.is_active,
            },
        );

        variant.value = data.data;
        histories.value = data.data.histories || histories.value;

        window.alert('Đã cập nhật biến thể.');
    } catch (error) {
        window.alert(errorMessage(error));
    } finally {
        saving.value = false;
    }
}

async function saveUnits() {
    saving.value = true;

    try {
        await api.put(
            `/admin/products/${product.value.id}/variants/${variant.value.id}/units`,
            {
                units: units.value.map((unit) => ({
                    id: unit.id ?? null,
                    name: unit.name,
                    conversion_rate: unit.conversion_rate,
                    is_base: unit.is_base,
                    price: unit.price ?? null,
                    is_sellable: unit.is_sellable,
                    barcode: unit.barcode ?? null,
                })),
            },
        );

        window.alert('Đã cập nhật đơn vị tính.');
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
