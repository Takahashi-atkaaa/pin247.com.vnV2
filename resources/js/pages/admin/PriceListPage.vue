<template>
    <div class="space-y-5">
        <!-- Tabs -->
        <div class="flex gap-1 border-b border-gray-200 overflow-x-auto no-scrollbar">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition whitespace-nowrap"
                :class="activeTab === tab.key
                    ? 'border-brand-600 text-brand-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- =================== TAB: BẬC GIÁ =================== -->
        <div v-show="activeTab === 'tiers'" class="space-y-4">
            <!-- Toolbar -->
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-3">
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Tìm SKU hoặc tên sản phẩm..."
                    class="w-full sm:flex-1 sm:min-w-[220px] px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-500"
                    @input="debouncedLoad"
                />

                <button
                    class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition"
                    @click="exportCsv"
                >
                    Xuất Excel (CSV)
                </button>

                <router-link
                    :to="{ name: 'admin-price-tier-create' }"
                    class="w-full sm:w-auto inline-block text-center px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 transition"
                >
                    + Thêm bậc giá
                </router-link>
            </div>

            <DataTable
                :columns="columns"
                :rows="rows"
                :meta="meta"
                :loading="loading"
                empty-text="Chưa có bậc giá nào."
                @page="changePage"
            >
                <template #cell-sku="{ row }">
                    <span class="font-mono text-xs text-gray-700">{{ row.sku }}</span>
                </template>

                <template #cell-product_name="{ row }">
                    <div>
                        <p class="text-gray-900">{{ row.product_name || '—' }}</p>
                        <p v-if="row.variant_name" class="text-xs text-gray-500">{{ row.variant_name }}</p>
                    </div>
                </template>

                <template #cell-min_qty="{ row }">
                    <span class="font-semibold text-brand-700">từ {{ row.min_qty }}</span>
                </template>

                <template #cell-max_qty="{ row }">
                    <span class="text-gray-600">{{ row.max_qty ?? 'trở lên' }}</span>
                </template>

                <template #cell-unit_price="{ row }">
                    <span class="font-semibold text-gray-900">{{ formatCurrency(row.unit_price) }}</span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <router-link
                            :to="{ name: 'admin-price-tier-edit', params: { id: row.id } }"
                            class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                        >
                            Sửa
                        </router-link>
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

        <!-- =================== TAB: NHẬP TỪ FILE =================== -->
        <div v-show="activeTab === 'import'" class="space-y-4">
            <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                <div>
                    <h2 class="font-semibold text-gray-900">Nhập bảng giá từ file CSV</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Mỗi dòng là một bậc giá. Cột: SKU, SL yêu cầu tối thiểu, SL tối đa
                        (để trống = trở lên), đơn giá, nhãn.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition"
                        @click="downloadTemplate"
                    >
                        Tải file mẫu CSV
                    </button>
                </div>

                <!-- Dropzone -->
                <div
                    class="border-2 border-dashed rounded-xl p-6 text-center transition"
                    :class="dragOver ? 'border-brand-500 bg-brand-50' : 'border-gray-300'"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,text/csv"
                        class="hidden"
                        @change="onSelect"
                    >
                    <p class="font-medium text-gray-900">Kéo thả file CSV vào đây</p>
                    <p class="text-sm text-gray-500 mt-1">Hoặc chọn file từ máy tính</p>
                    <button
                        type="button"
                        class="mt-3 px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition"
                        @click="fileInput.click()"
                    >
                        Chọn file
                    </button>
                </div>

                <!-- Kết quả -->
                <div v-if="importResult" class="border-t border-gray-200 pt-4 space-y-3">
                    <div class="flex flex-wrap gap-3 text-sm">
                        <span class="px-3 py-1.5 rounded-lg bg-green-50 text-green-700 font-medium">
                            Thêm mới: {{ importResult.imported }}
                        </span>
                        <span class="px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 font-medium">
                            Cập nhật: {{ importResult.updated }}
                        </span>
                        <span class="px-3 py-1.5 rounded-lg bg-red-50 text-red-700 font-medium">
                            Lỗi: {{ importResult.errors.length }}
                        </span>
                    </div>

                    <div v-if="importResult.errors.length" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold w-24">Dòng</th>
                                    <th class="px-3 py-2 text-left font-semibold">Lỗi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(err, i) in importResult.errors" :key="i">
                                    <td class="px-3 py-2 text-gray-500">{{ err.line }}</td>
                                    <td class="px-3 py-2 text-red-600">{{ err.message }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================== TAB: FILE BẢNG GIÁ =================== -->
        <div v-show="activeTab === 'documents'" class="space-y-4">
            <!-- Form đăng file -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Đăng file bảng giá cho khách tải</h2>

                <form class="space-y-4" @submit.prevent="uploadDocument">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Tiêu đề *</span>
                            <input
                                v-model="docForm.title"
                                required
                                placeholder="Bảng giá cell 18650 - 12/2026"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Mô tả</span>
                            <input
                                v-model="docForm.description"
                                placeholder="Ghi chú ngắn hiển thị dưới nút tải"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            >
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-sm font-medium text-gray-700">File *</span>
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv,.pdf"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-3 file:py-1 file:px-3 file:border-0 file:rounded file:bg-brand-50 file:text-brand-700 file:text-sm"
                                @change="onDocFile"
                            >
                            <span class="block text-xs text-gray-500 mt-1">
                                Chấp nhận: .xlsx, .xls, .csv, .pdf — tối đa 40MB
                            </span>
                        </label>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="docForm.is_published" type="checkbox" class="rounded">
                        Hiển thị nút tải trên trang bảng giá
                    </label>

                    <div class="flex justify-end pt-2 border-t border-gray-100">
                        <button
                            type="submit"
                            :disabled="uploading"
                            class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50"
                        >
                            {{ uploading ? 'Đang tải lên...' : 'Đăng file' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Danh sách file -->
            <DataTable
                :columns="docColumns"
                :rows="documents"
                :loading="loadingDocuments"
                empty-text="Chưa đăng file bảng giá nào."
            >
                <template #cell-title="{ row }">
                    <div>
                        <p class="font-medium text-gray-900">{{ row.title }}</p>
                        <p v-if="row.description" class="text-xs text-gray-500">{{ row.description }}</p>
                    </div>
                </template>

                <template #cell-original_name="{ row }">
                    <div>
                        <p class="text-gray-700">{{ row.original_name }}</p>
                        <p class="text-xs text-gray-400">{{ row.size_label }}</p>
                    </div>
                </template>

                <template #cell-is_published="{ row }">
                    <button
                        class="px-2 py-1 rounded-full text-xs font-medium transition"
                        :class="row.is_published
                            ? 'bg-green-50 text-green-700'
                            : 'bg-gray-100 text-gray-600'"
                        @click="togglePublish(row)"
                    >
                        {{ row.is_published ? 'Đang hiện' : 'Đang ẩn' }}
                    </button>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-gray-600 text-xs">{{ formatDate(row.created_at) }}</span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <button
                            class="px-2 py-1 text-xs border border-gray-300 rounded hover:border-brand-500 hover:text-brand-600"
                            @click="downloadDocument(row)"
                        >
                            Tải
                        </button>
                        <button
                            class="px-2 py-1 text-xs border border-red-200 text-red-600 rounded hover:bg-red-50"
                            @click="removeDocument(row)"
                        >
                            Xóa
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import DataTable from '../../components/admin/DataTable.vue';
import api from '../../services/api';
import { downloadFile } from '../../composables/useDownload';
import { errorMessage, formatCurrency, formatDate } from '../../composables/useFormat';
import { confirmDialog, notify } from '../../composables/useToast';

const tabs = [
    { key: 'tiers', label: 'Bậc giá' },
    { key: 'import', label: 'Nhập từ file' },
    { key: 'documents', label: 'File bảng giá' },
];

const activeTab = ref('tiers');

const columns = [
    { key: 'sku', label: 'SKU', primary: true },
    { key: 'product_name', label: 'Sản phẩm' },
    { key: 'min_qty', label: 'SL yêu cầu' },
    { key: 'max_qty', label: 'SL tối đa' },
    { key: 'unit_price', label: 'Đơn giá' },
    { key: 'actions', label: 'Thao tác' },
];

const docColumns = [
    { key: 'title', label: 'Tiêu đề', primary: true },
    { key: 'original_name', label: 'File' },
    { key: 'is_published', label: 'Trạng thái' },
    { key: 'created_at', label: 'Ngày đăng' },
    { key: 'actions', label: 'Thao tác' },
];

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);

const documents = ref([]);
const loadingDocuments = ref(false);
const uploading = ref(false);

const skuOptions = ref([]);
const importResult = ref(null);
const dragOver = ref(false);
const fileInput = ref(null);
const docFile = ref(null);


const filters = reactive({
    search: '',
    page: 1,
});


const docForm = reactive({
    title: '',
    description: '',
    is_published: true,
});

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get('/admin/price-list', {
            params: {
                search: filters.search || undefined,
                page: filters.page,
                per_page: 50,
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

async function loadDocuments() {
    loadingDocuments.value = true;

    try {
        const { data } = await api.get('/admin/price-list/documents');

        documents.value = data.data || [];
    } catch (error) {
        console.error(error);
    } finally {
        loadingDocuments.value = false;
    }
}

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




async function remove(row) {
    if (!(await confirmDialog(`Xoá bậc giá "${row.sku}" từ ${row.min_qty}?`, { danger: true }))) {
        return;
    }

    try {
        await api.delete(`/admin/price-list/tiers/${row.id}`);
        notify(`Đã xoá bậc giá "${row.sku}".`, 'success');
        await load();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function exportCsv() {
    try {
        await downloadFile(
            '/admin/price-list/export',
            `bang-gia-pin247-${new Date().toISOString().slice(0, 10)}.csv`,
        );
    } catch (error) {
        notify(errorMessage(error, 'Xuất bảng giá thất bại.'), 'error');
    }
}

async function downloadTemplate() {
    try {
        await downloadFile('/admin/price-list/import-template', 'mau-bang-gia.csv');
    } catch (error) {
        notify(errorMessage(error, 'Tải file mẫu thất bại.'), 'error');
    }
}

/* ---------------- Import CSV ---------------- */

function onSelect(event) {
    const file = event.target.files?.[0];

    if (file) {
        uploadImport(file);
    }

    event.target.value = '';
}

function onDrop(event) {
    dragOver.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file) {
        uploadImport(file);
    }
}

async function uploadImport(file) {
    const body = new FormData();

    body.append('file', file);

    try {
        const { data } = await api.post('/admin/price-list/import', body, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        importResult.value = data.data;
        await load();
    } catch (error) {
        notify(errorMessage(error, 'Nhập bảng giá thất bại.'), 'error');
    }
}

/* ---------------- File bảng giá ---------------- */

function onDocFile(event) {
    docFile.value = event.target.files?.[0] || null;
}

async function uploadDocument() {
    if (!docFile.value) {
        notify('Vui lòng chọn file cần tải lên.', 'success');

        return;
    }

    uploading.value = true;

    try {
        const body = new FormData();

        body.append('title', docForm.title);
        body.append('description', docForm.description || '');
        body.append('is_published', docForm.is_published ? '1' : '0');
        body.append('file', docFile.value);

        await api.post('/admin/price-list/documents', body, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        Object.assign(docForm, { title: '', description: '', is_published: true });
        docFile.value = null;

        await loadDocuments();
    } catch (error) {
        notify(errorMessage(error, 'Tải file lên thất bại.'), 'error');
    } finally {
        uploading.value = false;
    }
}

async function togglePublish(row) {
    try {
        await api.put(`/admin/price-list/documents/${row.id}`, {
            is_published: !row.is_published,
        });

        await loadDocuments();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function downloadDocument(row) {
    try {
        await downloadFile(
            `/admin/price-list/documents/${row.id}/download`,
            row.original_name || 'bang-gia.pdf',
        );
    } catch (error) {
        notify(errorMessage(error, 'Tải file thất bại.'), 'error');
    }
}

async function removeDocument(row) {
    if (!(await confirmDialog(`Xoá file "${row.title}"?`, { danger: true }))) {
        return;
    }

    try {
        await api.delete(`/admin/price-list/documents/${row.id}`);
        notify(`Đã xoá file "${row.title}".`, 'success');
        await loadDocuments();
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

onMounted(async () => {
    await load();
    await loadDocuments();
    await loadSkuOptions();
});
</script>
