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

            <router-link
                :to="{ name: 'admin-product-create' }"
                class="w-full sm:w-auto inline-block text-center px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 transition"
            >
                + Thêm sản phẩm
            </router-link>
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
                    <router-link
                        :to="{ name: 'admin-product-edit', params: { id: row.id } }"
                        class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                    >
                        Sửa
                    </router-link>
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


    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import DataTable from '../../components/admin/DataTable.vue';
import api from '../../services/api';
import { errorMessage, formatCurrency } from '../../composables/useFormat';
import { confirmDialog, notify } from '../../composables/useToast';

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
const categories = ref([]);

// Thao tác hàng loạt.
const selectedIds = ref([]);

const filters = reactive({
    search: '',
    category_id: '',
    source: '',
    page: 1,
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
        const confirmed = (await confirmDialog(
            `Xoá ${selectedIds.value.length} sản phẩm? Sản phẩm đã có đơn hàng sẽ được ẩn thay vì xoá hẳn.`, { danger: true }));

        if (!confirmed) {
            return;
        }
    }

    try {
        const { data } = await api.post('/admin/products-bulk', {
            ids: selectedIds.value,
            action,
        });

        notify(data.message || 'Đã cập nhật.', 'success');
        clearSelection();
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}



/**
 * Mở màn chi tiết biến thể (clone Haravan).
 *
 * Nếu sản phẩm chỉ có 1 biến thể thì vào thẳng màn chi tiết biến thể.
 */
async function openVariants(row) {
    if (!row.variants?.length) {
        notify('Sản phẩm này chưa có biến thể. Bật "Quản lý biến thể" khi sửa để tạo.', 'success');
        return;
    }

    await router.push({
        name: 'admin-product-variant',
        params: { id: row.id, variantId: row.variants[0].id },
    });
}



// -------------------------------------------------------------------------
// Nhân bản / xoá
// -------------------------------------------------------------------------

async function duplicate(row) {
    if (!(await confirmDialog(`Nhân bản sản phẩm "${row.name}"? Bản sao sẽ có SKU mới và chưa được bán.`, { danger: true }))) {
        return;
    }

    try {
        const { data } = await api.post(`/admin/products/${row.id}/duplicate`);

        notify(data.message || 'Đã nhân bản sản phẩm.', 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function remove(row) {
    if (!(await confirmDialog(`Xóa sản phẩm "${row.name}"?`, { danger: true }))) {
        return;
    }

    try {
        await api.delete(`/admin/products/${row.id}`);
        notify(`Đã xóa sản phẩm "${row.name}".`, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
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
