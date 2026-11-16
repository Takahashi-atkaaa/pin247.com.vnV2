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

        <!-- Bảng -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :meta="meta"
            :loading="loading"
            empty-text="Chưa có sản phẩm nào."
            @page="changePage"
        >
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
                            <span v-if="row.has_variants"> • {{ row.variants?.length || 0 }} biến thể</span>
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
                <div class="flex gap-2">
                    <button
                        class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                        @click="openEdit(row)"
                    >
                        Sửa
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
                <div class="bg-white shadow-xl w-full max-w-3xl rounded-t-2xl sm:rounded-xl sm:my-auto my-0">
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
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import DataTable from '../../components/admin/DataTable.vue';
import api from '../../services/api';
import { errorMessage, formatCurrency } from '../../composables/useFormat';

const columns = [
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

function openCreate() {
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
    });

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
        });

        showModal.value = true;
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

async function save() {
    saving.value = true;

    try {
        const payload = {
            ...form,
            category_id: form.category_id || null,
            sale_price: form.sale_price || null,
            cost_price: form.cost_price || null,
        };

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
