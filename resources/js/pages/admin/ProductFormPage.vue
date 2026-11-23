<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <router-link
                    :to="{ name: 'admin-products' }"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600 mb-1"
                >
                    ← Quay lại danh sách sản phẩm
                </router-link>
                <h1 class="text-xl font-bold text-gray-900">
                    {{ isEdit ? 'Cập nhật sản phẩm' : 'Tạo sản phẩm mới' }}
                </h1>
                <p v-if="isEdit" class="text-sm text-gray-500 mt-0.5">
                    {{ form.name || '—' }}
                </p>
            </div>

            <div class="flex gap-2">
                <router-link
                    :to="{ name: 'admin-products' }"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50"
                >
                    Huỷ
                </router-link>
                <button
                    :disabled="saving || loading"
                    class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50"
                    @click="save"
                >
                    {{ saving ? 'Đang lưu...' : 'Lưu sản phẩm' }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-16 text-sm text-gray-500">
            Đang tải dữ liệu...
        </div>

        <form v-else class="space-y-5" @submit.prevent="save">
            <!-- Thông tin cơ bản -->
            <section class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                <h2 class="font-semibold text-gray-900">Thông tin cơ bản</h2>

                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-gray-700">Tên sản phẩm *</span>
                        <input
                            v-model="form.name"
                            required
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Danh mục</span>
                        <select
                            v-model="form.category_id"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                        >
                            <option value="">— Chọn danh mục —</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">SKU chính</span>
                        <input
                            v-model="form.sku"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono"
                        >
                        <span class="text-xs text-gray-500">Để trống nếu sản phẩm có biến thể.</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Thương hiệu</span>
                        <input
                            v-model="form.brand"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Giá bán (VNĐ) *</span>
                        <input
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="100"
                            required
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Giá giảm (VNĐ)</span>
                        <input
                            v-model.number="form.sale_price"
                            type="number"
                            min="0"
                            step="100"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Giá vốn (VNĐ)</span>
                        <input
                            v-model.number="form.cost_price"
                            type="number"
                            min="0"
                            step="100"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        >
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-gray-700">Mô tả</span>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        ></textarea>
                    </label>
                </div>

                <div class="flex flex-wrap gap-4 pt-2 border-t border-gray-100">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded">
                        Đang bán
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_featured" type="checkbox" class="rounded">
                        Nổi bật
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_new" type="checkbox" class="rounded">
                        Hàng mới
                    </label>
                </div>
            </section>

            <!-- Thuộc tính & biến thể -->
            <section class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">Biến thể & đơn vị tính</h2>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Tối đa 3 thuộc tính · tối đa 200 giá trị mỗi thuộc tính · tối đa 500 biến thể
                        </p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="showAttributes" type="checkbox" class="rounded">
                        Quản lý biến thể & đơn vị tính
                    </label>
                </div>

                <ProductAttributesEditor
                    v-if="showAttributes"
                    :attributes="form.attributes"
                    :variants="form.variants"
                    :product-price="form.price || 0"
                    @open-units="openUnits"
                />

                <p v-else class="text-sm text-gray-500 italic">
                    Sản phẩm này sẽ được tạo như sản phẩm đơn giản (không có biến thể).
                </p>
            </section>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ProductAttributesEditor from '../../components/admin/ProductAttributesEditor.vue';
import api from '../../services/api';
import { errorMessage } from '../../composables/useFormat';
import { notify } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => !!route.params.id);

const loading = ref(false);
const saving = ref(false);
const categories = ref([]);
const showAttributes = ref(true);

const form = reactive({
    id: null,
    name: '',
    category_id: '',
    sku: '',
    brand: '',
    price: 0,
    sale_price: null,
    cost_price: null,
    description: '',
    is_active: true,
    is_featured: false,
    is_new: false,
    // GĐ1 — Thuộc tính động.
    attributes: [],
    // Biến thể (được sinh tự động từ attributes).
    variants: [],
});

function resetForm() {
    Object.assign(form, {
        id: null,
        name: '',
        category_id: '',
        sku: '',
        brand: '',
        price: 0,
        sale_price: null,
        cost_price: null,
        description: '',
        is_active: true,
        is_featured: false,
        is_new: false,
        attributes: [],
        variants: [],
    });
}

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get('/admin/categories');
        categories.value = data.data || [];
    } catch {
        categories.value = [];
    }

    if (!isEdit.value) {
        resetForm();
        loading.value = false;
        return;
    }

    try {
        const { data } = await api.get(`/admin/products/${route.params.id}`);
        const product = data.data;

        Object.assign(form, {
            id: product.id,
            name: product.name || '',
            category_id: product.category?.id || '',
            sku: product.sku || '',
            brand: product.brand || '',
            price: product.price ?? 0,
            sale_price: product.sale_price ?? null,
            cost_price: product.cost_price ?? null,
            description: product.description || '',
            is_active: !!product.is_active,
            is_featured: !!product.is_featured,
            is_new: !!product.is_new,
            // GĐ1 — Nạp thuộc tính & biến thể hiện có.
            attributes: (product.attributes || []).map((attribute) => ({
                _key: `a${attribute.id}`,
                id: attribute.id,
                name: attribute.name,
                values: (attribute.values || []).map((value) => ({
                    _key: `v${value.id}`,
                    id: value.id,
                    value: value.value,
                })),
            })),
            variants: product.variants || [],
        });

        showAttributes.value = (product.attributes || []).length > 0;
    } catch (error) {
        notify(errorMessage(error), 'error');
        router.replace({ name: 'admin-products' });
    } finally {
        loading.value = false;
    }
}

// -------------------------------------------------------------------------
// Đơn vị tính → chuyển sang trang riêng
// -------------------------------------------------------------------------

function openUnits(variant) {
    if (!form.id || !variant?.id) {
        notify('Hãy lưu sản phẩm trước khi quản lý đơn vị tính.', 'warning');
        return;
    }

    router.push({
        name: 'admin-product-variant-units',
        params: { id: form.id, variantId: variant.id },
    });
}

// -------------------------------------------------------------------------
// Lưu
// -------------------------------------------------------------------------

async function save() {
    saving.value = true;

    try {
        const payload = {
            name: form.name,
            category_id: form.category_id || null,
            sku: form.sku || null,
            brand: form.brand || null,
            price: form.price,
            sale_price: form.sale_price || null,
            cost_price: form.cost_price || null,
            description: form.description || null,
            is_active: form.is_active,
            is_featured: form.is_featured,
            is_new: form.is_new,
        };

        if (showAttributes.value) {
            // GĐ1 — Gửi thuộc tính + biến thể được sinh tự động.
            payload.attributes = form.attributes.map((attribute) => ({
                id: attribute.id ?? null,
                name: attribute.name,
                values: attribute.values.map((value) => value.value),
            }));

            payload.variants = form.variants.map((variant) => ({
                id: variant.id ?? null,
                sku: variant.sku,
                name: variant.name,
                price: variant.price,
                sale_price: variant.sale_price ?? null,
                cost_price: variant.cost_price ?? null,
                image: variant.image ?? null,
                is_active: variant.is_active,
                option_1_name: variant.option_1_name,
                option_1_value: variant.option_1_value,
                option_2_name: variant.option_2_name,
                option_2_value: variant.option_2_value,
                option_3_name: variant.option_3_name,
                option_3_value: variant.option_3_value,
            }));

            // GĐ2 — Đơn vị tính, gom theo SKU của biến thể.
            const units = {};

            form.variants.forEach((variant) => {
                if (variant.units?.length) {
                    units[variant.sku] = variant.units.map((unit) => ({
                        id: unit.id ?? null,
                        name: unit.name,
                        conversion_rate: unit.conversion_rate,
                        is_base: unit.is_base,
                        price: unit.price ?? null,
                        is_sellable: unit.is_sellable,
                        barcode: unit.barcode ?? null,
                    }));
                }
            });

            if (Object.keys(units).length) {
                payload.units = units;
            }
        }

        if (form.id) {
            await api.put(`/admin/products/${form.id}`, payload);
        } else {
            await api.post('/admin/products', payload);
        }

        notify(isEdit.value ? 'Đã cập nhật sản phẩm.' : 'Đã tạo sản phẩm.', 'success');
        router.push({ name: 'admin-products' });
    } catch (error) {
        notify(errorMessage(error), 'error');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
