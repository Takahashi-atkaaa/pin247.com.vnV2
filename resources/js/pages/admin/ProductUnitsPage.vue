<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <router-link
                    :to="backLink"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600 mb-1"
                >
                    ← Quay lại
                </router-link>
                <h1 class="text-xl font-bold text-gray-900">Đơn vị tính</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ variant?.name || 'Biến thể' }}
                    <span v-if="variant?.sku" class="font-mono ml-2">{{ variant.sku }}</span>
                </p>
            </div>

            <div class="flex gap-2">
                <router-link
                    :to="backLink"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50"
                >
                    Huỷ
                </router-link>
                <button
                    :disabled="saving || loading"
                    class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50"
                    @click="save"
                >
                    {{ saving ? 'Đang lưu...' : 'Lưu đơn vị' }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-16 text-sm text-gray-500">
            Đang tải dữ liệu...
        </div>

        <section v-else class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
            <div class="grid sm:grid-cols-3 gap-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Giá biến thể</p>
                    <p class="text-lg font-bold text-gray-900">{{ formatCurrency(basePrice) }}</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Số đơn vị</p>
                    <p class="text-lg font-bold text-gray-900">{{ units.length }}</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Đơn vị cơ bản</p>
                    <p class="text-lg font-bold text-gray-900">
                        {{ units.find((u) => u.is_base)?.name || '—' }}
                    </p>
                </div>
            </div>

            <ProductUnitsEditor
                :units="units"
                :base-price="basePrice"
            />
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ProductUnitsEditor from '../../components/admin/ProductUnitsEditor.vue';
import api from '../../services/api';
import { errorMessage, formatCurrency } from '../../composables/useFormat';
import { notify } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const variant = ref(null);
const units = ref([]);

const productId = computed(() => route.params.id);
const variantId = computed(() => route.params.variantId);

const basePrice = computed(() => Number(variant.value?.price || 0));

const backLink = computed(() => ({
    name: 'admin-product-variant',
    params: { id: productId.value, variantId: variantId.value },
}));

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get(
            `/admin/products/${productId.value}/variants/${variantId.value}`,
        );

        variant.value = data.data;
        units.value = (data.data.units || []).map((unit) => ({
            _key: `u${unit.id ?? 'new'}`,
            id: unit.id ?? null,
            name: unit.name,
            conversion_rate: unit.conversion_rate,
            is_base: unit.is_base,
            price: unit.price ?? null,
            is_sellable: unit.is_sellable,
            barcode: unit.barcode ?? null,
            // Tỷ lệ quy đổi không sửa được sau khi tạo (quy tắc Haravan).
            locked: unit.conversion_rate !== 1,
        }));
    } catch (error) {
        notify(errorMessage(error), 'error');
        router.replace({ name: 'admin-products' });
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;

    try {
        const payload = units.value
            .filter((unit) => (unit.name || '').trim() !== '')
            .map((unit) => ({
                id: unit.id ?? null,
                name: unit.name.trim(),
                conversion_rate: unit.conversion_rate,
                is_base: unit.is_base,
                price: unit.price ?? null,
                is_sellable: unit.is_sellable,
                barcode: unit.barcode ?? null,
            }));

        await api.put(
            `/admin/products/${productId.value}/variants/${variantId.value}/units`,
            { units: payload },
        );

        notify('Đã lưu đơn vị tính.', 'success');
        router.push(backLink.value);
    } catch (error) {
        notify(errorMessage(error), 'error');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
