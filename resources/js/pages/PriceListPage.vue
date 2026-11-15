<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500 mb-6">
            <RouterLink to="/" class="hover:text-brand-600">Trang chủ</RouterLink>
            <span class="mx-2">/</span>
            <span class="text-gray-800">Bảng giá</span>
        </nav>

        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Bảng giá</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Giá bán theo số lượng yêu cầu — càng mua nhiều giá càng tốt.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <!-- Toggle ngang / dọc -->
                <div class="hidden md:flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    <button
                        class="px-3 py-2 text-xs font-medium transition"
                        :class="mode === 'horizontal' ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                        @click="mode = 'horizontal'"
                    >
                        Bảng ngang
                    </button>
                    <button
                        class="px-3 py-2 text-xs font-medium transition"
                        :class="mode === 'vertical' ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                        @click="mode = 'vertical'"
                    >
                        Bảng dọc
                    </button>
                </div>

                <!-- In / Lưu PDF -->
                <button
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition print:hidden"
                    @click="printPage"
                >
                    In / Lưu PDF
                </button>
            </div>
        </div>

        <!-- File bảng giá tải về -->
        <section class="mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Tải bảng giá</h2>
            <DocumentList :documents="documents" />
        </section>

        <!-- Bộ lọc -->
        <div class="mb-5 space-y-3">
            <!-- Tìm kiếm -->
            <form class="relative max-w-md" @submit.prevent="applySearch">
                <input
                    v-model="keyword"
                    type="search"
                    placeholder="Tìm theo tên sản phẩm hoặc SKU..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 bg-white text-base sm:text-sm
                           focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition"
                />
                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </form>

            <!-- Chip danh mục -->
            <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
                <button
                    class="shrink-0 whitespace-nowrap border rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="filters.category === ''
                        ? 'border-brand-500 bg-brand-50 text-brand-700'
                        : 'border-gray-200 text-gray-700 hover:border-brand-400'"
                    @click="setCategory('')"
                >
                    Tất cả
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    class="shrink-0 whitespace-nowrap border rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="filters.category === category.slug
                        ? 'border-brand-500 bg-brand-50 text-brand-700'
                        : 'border-gray-200 text-gray-700 hover:border-brand-400'"
                    @click="setCategory(category.slug)"
                >
                    {{ category.name }}
                </button>
            </div>
        </div>

        <!-- Bảng giá -->
        <PriceTable
            :rows="rows"
            :tier-columns="tierColumns"
            :labels="labels"
            :view="tableView"
        />

        <!-- Phân trang -->
        <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 mt-8 print:hidden">
            <button
                v-for="page in pagination.last_page"
                :key="page"
                class="w-9 h-9 rounded-lg text-sm font-medium transition"
                :class="page === pagination.current_page
                    ? 'bg-brand-600 text-white'
                    : 'bg-white border border-gray-300 text-gray-700 hover:border-brand-400'"
                @click="goToPage(page)"
            >
                {{ page }}
            </button>
        </div>

        <!-- Ghi chú -->
        <p class="mt-8 text-xs text-gray-400 leading-relaxed">
            Bảng giá được cập nhật thường xuyên. Giá trên website mang tính tham khảo,
            giá thực tế sẽ được xác nhận khi đặt hàng. Số lượng yêu cầu tối thiểu
            được tính theo bậc giá thấp nhất áp dụng.
        </p>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import DocumentList from '../components/pricelist/DocumentList.vue';
import PriceTable from '../components/pricelist/PriceTable.vue';
import { usePriceListStore } from '../stores/pricelist';
import { useCatalogStore } from '../stores/catalog';

const route = useRoute();
const router = useRouter();
const priceList = usePriceListStore();
const catalog = useCatalogStore();

const rows = ref([]);
const tierColumns = ref([]);
const documents = ref([]);
const pagination = ref({});
const loading = ref(false);

const keyword = ref(route.query.search || '');
const mode = ref('horizontal');

/**
 * Desktop dùng bảng ngang, mobile dùng bảng dọc (dễ đọc trên màn nhỏ).
 * Người dùng có thể ép kiểu hiển thị bằng nút toggle.
 */
const tableView = computed(() => mode.value);

const filters = reactive({
    category: route.query.category || '',
    search: route.query.search || '',
});

const categories = computed(() => catalog.categories);

/**
 * Nhãn hiển thị của các cột bậc giá: "1", "10-49", "200+".
 */
const labels = computed(() =>
    tierColumns.value.map((column) => {
        if (column.max_qty === null) {
            return `${column.min_qty}+`;
        }

        if (column.min_qty === column.max_qty) {
            return `${column.min_qty}`;
        }

        return `${column.min_qty}-${column.max_qty}`;
    }),
);

function syncQuery() {
    const query = {};

    if (filters.category) {
        query.category = filters.category;
    }

    if (filters.search) {
        query.search = filters.search;
    }

    router.push({ name: 'price-list', query });
}

function setCategory(slug) {
    filters.category = slug;
    syncQuery();
    load();
}

function applySearch() {
    filters.search = keyword.value.trim();
    syncQuery();
    load();
}

function goToPage(page) {
    router.push({ name: 'price-list', query: { ...route.query, page } });
}

function printPage() {
    window.print();
}

async function load() {
    loading.value = true;

    try {
        const params = { per_page: 30 };

        if (filters.category) {
            params.category = filters.category;
        }

        if (filters.search) {
            params.search = filters.search;
        }

        const page = route.query.page;

        if (page) {
            params.page = page;
        }

        const data = await priceList.fetchPriceList(params);

        rows.value = data.data || [];
        tierColumns.value = data.tier_columns || [];
        pagination.value = data.meta || {};
    } finally {
        loading.value = false;
    }
}

watch(() => route.query, (query) => {
    keyword.value = query.search || '';
    filters.category = query.category || '';
    filters.search = query.search || '';

    load();
}, { deep: true });

onMounted(async () => {
    await catalog.fetchCategories().catch(() => {});

    await load();

    try {
        documents.value = await priceList.fetchDocuments();
    } catch {
        documents.value = [];
    }
});
</script>
