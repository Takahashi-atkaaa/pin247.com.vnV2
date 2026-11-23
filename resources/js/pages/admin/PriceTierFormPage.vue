<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <router-link
                    :to="{ name: 'admin-price-list' }"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600 mb-1"
                >
                    ← Quay lại bảng giá
                </router-link>
                <h1 class="text-xl font-bold text-gray-900">
                    {{ isEdit ? 'Cập nhật bậc giá' : 'Thêm bậc giá' }}
                </h1>
                <p v-if="isEdit" class="text-sm text-gray-500 mt-0.5 font-mono">{{ form.sku }}</p>
            </div>

            <div class="flex gap-2">
                <router-link
                    :to="{ name: 'admin-price-list' }"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50"
                >
                    Huỷ
                </router-link>
                <button
                    :disabled="saving"
                    class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50"
                    @click="save"
                >
                    {{ saving ? 'Đang lưu...' : 'Lưu bậc giá' }}
                </button>
            </div>
        </div>

        <section class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="space-y-4 max-w-2xl">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">SKU *</span>
                    <input
                        v-model="form.sku"
                        required
                        list="sku-options"
                        placeholder="Nhập hoặc chọn SKU"
                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono"
                    >
                    <datalist id="sku-options">
                        <option v-for="s in skuOptions" :key="s.sku" :value="s.sku">
                            {{ s.name }}
                        </option>
                    </datalist>
                </label>

                <div class="grid grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">SL yêu cầu tối thiểu *</span>
                        <input
                            v-model.number="form.min_qty"
                            type="number"
                            min="1"
                            step="1"
                            required
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">SL tối đa</span>
                        <input
                            v-model.number="form.max_qty"
                            type="number"
                            min="1"
                            step="1"
                            placeholder="Để trống = trở lên"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Đơn giá (VNĐ) *</span>
                        <input
                            v-model.number="form.unit_price"
                            type="number"
                            min="0"
                            step="100"
                            required
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Nhãn</span>
                        <input
                            v-model="form.label"
                            placeholder="Lô 50-199"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import { errorMessage } from '../../composables/useFormat';
import { notify } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => !!route.params.id);

const saving = ref(false);
const skuOptions = ref([]);

const form = reactive({
    id: null,
    sku: '',
    min_qty: 1,
    max_qty: null,
    unit_price: 0,
    label: '',
});

async function loadSkuOptions() {
    try {
        const { data } = await api.get('/products', { params: { per_page: 60 } });

        skuOptions.value = (data.data || []).map((p) => ({
            sku: p.default_variant?.sku || p.sku,
            name: p.name,
        })).filter((o) => o.sku);
    } catch {
        skuOptions.value = [];
    }
}

async function load() {
    await loadSkuOptions();

    if (!isEdit.value) {
        return;
    }

    try {
        const { data } = await api.get(`/admin/price-list/tiers/${route.params.id}`);

        Object.assign(form, {
            id: data.data.id,
            sku: data.data.sku || '',
            min_qty: data.data.min_qty,
            max_qty: data.data.max_qty ?? null,
            unit_price: data.data.unit_price,
            label: data.data.label || '',
        });
    } catch (error) {
        notify(errorMessage(error), 'error');
        router.replace({ name: 'admin-price-list' });
    }
}

async function save() {
    saving.value = true;

    try {
        const payload = {
            sku: form.sku,
            min_qty: form.min_qty,
            max_qty: form.max_qty || null,
            unit_price: form.unit_price,
            label: form.label || null,
        };

        if (form.id) {
            await api.put(`/admin/price-list/tiers/${form.id}`, payload);
        } else {
            await api.post('/admin/price-list/tiers', payload);
        }

        notify(isEdit.value ? 'Đã cập nhật bậc giá.' : 'Đã thêm bậc giá.', 'success');
        router.push({ name: 'admin-price-list' });
    } catch (error) {
        notify(errorMessage(error), 'error');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
