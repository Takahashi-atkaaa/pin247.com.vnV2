<template>
    <div class="space-y-5">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-3">
            <input
                v-model="filters.search"
                type="search"
                placeholder="Tìm theo tên hoặc SKU..."
                class="w-full sm:flex-1 sm:min-w-[220px] px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-500"
                @input="debouncedLoad"
            />

            <select
                v-model="filters.category_id"
                class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                @change="load"
            >
                <option value="">Tất cả danh mục</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>

            <select
                v-model="filters.source"
                class="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                @change="load"
            >
                <option value="">Tất cả nguồn</option>
                <option value="MANUAL">Quản lý tại website</option>
                <option value="BIGSELLER">Đồng bộ từ BigSeller</option>
                <option value="IMPORTED">Nhập từ CSV/Excel</option>
            </select>

            <button
                class="w-full sm:w-auto px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 transition"
                @click="openCreate"
            >
                + Thêm sản phẩm
            </button>
        </div>

        <!-- Thao tác hàng loạt -->
        <div
            v-if="selectedIds.length"
            class="flex flex-col sm:flex-row sm:items-center gap-3 bg-brand-50 border border-brand-200 rounded-xl px-4 py-3"
        >
            <p class="text-sm font-medium text-brand-900">
                Đã chọn {{ selectedIds.length }} sản phẩm
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-brand-300 text-brand-700 rounded-lg hover:bg-brand-100"
                    @click="bulkAction('activate')"
                >
                    Hiển thị
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-brand-300 text-brand-700 rounded-lg hover:bg-brand-100"
                    @click="bulkAction('deactivate')"
                >
                    Ẩn
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-brand-300 text-brand-700 rounded-lg hover:bg-brand-100"
                    @click="bulkAction('feature')"
                >
                    Nổi bật
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-brand-300 text-brand-700 rounded-lg hover:bg-brand-100"
                    @click="bulkAction('mark_new')"
                >
                    Hàng mới
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-red-300 text-red-600 rounded-lg hover:bg-red-50"
                    @click="bulkAction('delete')"
                >
                    Xoá
                </button>
                <button
                    class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50"
                    @click="clearSelection"
                >
                    Bỏ chọn
                </button>
            </div>
        </div>

        <!-- Bảng -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :meta="meta"
            :loading="loading"
            empty-text="Chưa có sản phẩm nào."
            @page="changePage"
        >
            <template #cell-select="{ row }">
                <input
                    type="checkbox"
                    class="rounded"
                    :checked="selectedIds.includes(row.id)"
                    @change="toggleSelect(row.id)"
                >
            </template>

            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <img
                        v-if="row.image"
                        :src="row.image"
                        :alt="row.name"
                        class="w-10 h-10 object-cover rounded border border-gray-200"
                    >
                    <div v-else class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center text-xs text-gray-400">
                        Ảnh
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">{{ row.name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ row.brand || '—' }}
                            <span v-if="row.has_variants"> • {{ row.variants_count || 0 }} biến thể</span>
                        </p>
                    </div>
                </div>
            </template>

            <template #cell-sku="{ row }">
                <span class="font-mono text-xs text-gray-600">{{ row.sku || '—' }}</span>
            </template>

            <template #cell-price="{ row }">
                <div>
                    <p class="font-medium text-gray-900">{{ formatCurrency(row.effective_price) }}</p>
                    <p v-if="row.is_on_sale" class="text-xs text-gray-400 line-through">
                        {{ formatCurrency(row.price) }}
                    </p>
                </div>
            </template>

            <template #cell-stock="{ row }">
                <span
                    class="font-semibold"
                    :class="row.total_available_stock > 0 ? 'text-green-600' : 'text-red-500'"
                >
                    {{ row.total_available_stock }}
                </span>
            </template>

            <template #cell-source="{ row }">
                <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                    {{ row.source_label || row.source }}
                </span>
            </template>

            <template #cell-is_active="{ row }">
                <span
                    class="px-2 py-1 rounded-full text-xs font-medium"
                    :class="row.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                >
                    {{ row.is_active ? 'Đang bán' : 'Ẩn' }}
                </span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex flex-wrap gap-2">
                    <button
                        class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                        @click="openEdit(row)"
                    >
                        Sửa
                    </button>
                    <button
                        class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                        @click="openVariants(row)"
                    >
                        Biến thể
                    </button>
                    <button
                        class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                        @click="duplicate(row)"
                    >
                        Nhân bản
                    </button>
                    <button
                        class="px-2 py-1 text-xs border border-red-200 text-red-600 rounded hover:bg-red-50"
                        @click="remove(row)"
                    >
                        Xóa
                    </button>
                </div>
            </template>
        </DataTable>

        <!-- Modal tạo / sửa -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 bg-black/40 z-50 flex items-start justify-center overflow-y-auto py-0 sm:py-8"
                @click.self="showModal = false"
            >
                <div class="bg-white shadow-xl w-full max-w-5xl rounded-t-2xl sm:rounded-xl sm:my-auto my-0">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">
                            {{ form.id ? 'Cập nhật sản phẩm' : 'Tạo sản phẩm mới' }}
                        </h3>
                        <button class="text-gray-400 hover:text-gray-700" @click="showModal = false">
                            ✕
                        </button>
                    </div>

                    <form class="p-4 sm:p-6 space-y-4" @submit.prevent="save">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="block sm:col-span-2">
                                <span class="text-sm font-medium text-gray-700">Tên sản phẩm *</span>
                                <input v-model="form.name" required
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Danh mục</span>
                                <select v-model="form.category_id"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                    <option value="">— Chọn danh mục —</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">SKU chính</span>
                                <input v-model="form.sku"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono">
                                <span class="text-xs text-gray-500">Để trống nếu sản phẩm có biến thể.</span>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Thương hiệu</span>
                                <input v-model="form.brand"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Giá bán (VNĐ) *</span>
                                <input v-model.number="form.price" type="number" min="0" step="100" required
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Giá giảm (VNĐ)</span>
                                <input v-model.number="form.sale_price" type="number" min="0" step="100"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Giá vốn (VNĐ)</span>
                                <input v-model.number="form.cost_price" type="number" min="0" step="100"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="text-sm font-medium text-gray-700">Mô tả</span>
                                <textarea v-model="form.description" rows="3"
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                            </label>
                        </div>

                        <!-- Thuộc tính & biến thể -->
                        <ProductAttributesEditor
                            v-if="showAttributes"
                            :attributes="form.attributes"
                            :variants="form.variants"
                            :product-price="form.price || 0"
                            @open-units="openUnitsModal"
                        />

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
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="showAttributes" type="checkbox" class="rounded">
                                Quản lý biến thể & đơn vị tính
                            </label>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                            <button type="button"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50"
                                @click="showModal = false">
                                Huỷ
                            </button>
                            <button type="submit" :disabled="saving"
                                class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50">
                                {{ saving ? 'Đang lưu...' : 'Lưu' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal đơn vị tính -->
        <Teleport to="body">
            <div
                v-if="showUnitsModal"
                class="fixed inset-0 bg-black/40 z-50 flex items-start justify-center overflow-y-auto py-0 sm:py-8"
                @click.self="showUnitsModal = false"
            >
                <div class="bg-white shadow-xl w-full max-w-3xl rounded-t-2xl sm:rounded-xl sm:my-auto my-0">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">
                            Đơn vị tính — {{ currentUnitVariant?.name || 'Biến thể' }}
                        </h3>
                        <button class="text-gray-400 hover:text-gray-700" @click="showUnitsModal = false">
                            ✕
                        </button>
                    </div>

                    <div class="p-4 sm:p-6 space-y-4">
                        <ProductUnitsEditor
                            :units="currentUnitVariant?.units || []"
                            :base-price="currentUnitVariant?.price || 0"
                        />
                    </div>

                    <div class="flex justify-end gap-2 px-4 sm:px-6 py-4 border-t border-gray-100">
                        <button type="button"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50"
                            @click="showUnitsModal = false">
                            Huỷ
                        </button>
                        <button type="button" :disabled="saving"
                            class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50"
                            @click="saveUnits">
                            {{ saving ? 'Đang lưu...' : 'Lưu đơn vị' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import DataTable from '../../components/admin/DataTable.vue';
import ProductAttributesEditor from '../../components/admin/ProductAttributesEditor.vue';
import ProductUnitsEditor from '../../components/admin/ProductUnitsEditor.vue';
import api from '../../services/api';
import { errorMessage, formatCurrency } from '../../composables/useFormat';

const router = useRouter();

const columns = [
    { key: 'select', label: '', class: 'w-8' },
    { key: 'name', label: 'Sản phẩm', primary: true },
    { key: 'sku', label: 'SKU' },
    { key: 'price', label: 'Giá' },
    { key: 'stock', label: 'Tồn' },
    { key: 'source', label: 'Nguồn' },
    { key: 'is_active', label: 'Trạng thái' },
    { key: 'actions', label: 'Thao tác' },
];

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const saving = ref(false);
const categories = ref([]);
const showModal = ref(false);
const showAttributes = ref(true);

// Thao tác hàng loạt.
const selectedIds = ref([]);

// Modal đơn vị tính.
const showUnitsModal = ref(false);
const currentUnitVariant = ref(null);

const filters = reactive({
    search: '',
    category_id: '',
    source: '',
    page: 1,
});

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

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get('/admin/products', {
            params: {
                search: filters.search || undefined,
                category_id: filters.category_id || undefined,
                source: filters.source || undefined,
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

// -------------------------------------------------------------------------
// Chọn / thao tác hàng loạt
// -------------------------------------------------------------------------

function toggleSelect(id) {
    const index = selectedIds.value.indexOf(id);

    if (index >= 0) {
        selectedIds.value.splice(index, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function clearSelection() {
    selectedIds.value = [];
}

async function bulkAction(action) {
    if (action === 'delete') {
        const confirmed = window.confirm(
            `Xoá ${selectedIds.value.length} sản phẩm? Sản phẩm đã có đơn hàng sẽ được ẩn thay vì xoá hẳn.`,
        );

        if (!confirmed) {
            return;
        }
    }

    try {
        const { data } = await api.post('/admin/products-bulk', {
            ids: selectedIds.value,
            action,
        });

        window.alert(data.message || 'Đã cập nhật.');
        clearSelection();
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

// -------------------------------------------------------------------------
// Mở form tạo / sửa
// -------------------------------------------------------------------------

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

function openCreate() {
    resetForm();
    showModal.value = true;
}

async function openEdit(row) {
    try {
        const { data } = await api.get(`/admin/products/${row.id}`);
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

        showModal.value = true;
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

/**
 * Mở màn chi tiết biến thể (clone Haravan).
 *
 * Nếu sản phẩm chỉ có 1 biến thể thì vào thẳng màn chi tiết biến thể.
 */
async function openVariants(row) {
    if (!row.variants?.length) {
        window.alert('Sản phẩm này chưa có biến thể. Bật "Quản lý biến thể" khi sửa để tạo.');
        return;
    }

    await router.push({
        name: 'admin-product-variant',
        params: { id: row.id, variantId: row.variants[0].id },
    });
}

// -------------------------------------------------------------------------
// Đơn vị tính
// -------------------------------------------------------------------------

function openUnitsModal(variant) {
    currentUnitVariant.value = variant;
    showUnitsModal.value = true;
}

async function saveUnits() {
    if (!currentUnitVariant.value) {
        return;
    }

    saving.value = true;

    try {
        const units = (currentUnitVariant.value.units || []).map((unit) => ({
            id: unit.id ?? null,
            name: unit.name,
            conversion_rate: unit.conversion_rate,
            is_base: unit.is_base,
            price: unit.price ?? null,
            is_sellable: unit.is_sellable,
            barcode: unit.barcode ?? null,
        }));

        await api.put(
            `/admin/products/${form.id}/variants/${currentUnitVariant.value.id}/units`,
            { units },
        );

        showUnitsModal.value = false;
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    } finally {
        saving.value = false;
    }
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

        showModal.value = false;
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    } finally {
        saving.value = false;
    }
}

// -------------------------------------------------------------------------
// Nhân bản / xoá
// -------------------------------------------------------------------------

async function duplicate(row) {
    if (!window.confirm(`Nhân bản sản phẩm "${row.name}"? Bản sao sẽ có SKU mới và chưa được bán.`)) {
        return;
    }

    try {
        const { data } = await api.post(`/admin/products/${row.id}/duplicate`);

        window.alert(data.message || 'Đã nhân bản sản phẩm.');
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

async function remove(row) {
    if (!window.confirm(`Xóa sản phẩm "${row.name}"?`)) {
        return;
    }

    try {
        await api.delete(`/admin/products/${row.id}`);
        await load();
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

onMounted(async () => {
    await load();

    try {
        const { data } = await api.get('/admin/categories');
        categories.value = data.data || [];
    } catch {
        categories.value = [];
    }
});
</script>
