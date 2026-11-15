<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500 mb-6">
            <RouterLink to="/" class="hover:text-brand-600">Trang chủ</RouterLink>
            <span class="mx-2">/</span>
            <RouterLink :to="{ name: 'products' }" class="hover:text-brand-600">Sản phẩm</RouterLink>
            <template v-if="currentCategory">
                <span class="mx-2">/</span>
                <span class="text-gray-800">{{ currentCategory.name }}</span>
            </template>
        </nav>

        <div class="grid lg:grid-cols-[260px_1fr] gap-8">
            <!-- Bộ lọc -->
            <div class="hidden lg:block">
                <ProductFilter
                    :categories="catalog.categories"
                    :brands="brands"
                    :filters="filters"
                    @update:category="setFilter('category', $event)"
                    @update:minPrice="setFilter('min_price', $event)"
                    @update:maxPrice="setFilter('max_price', $event)"
                    @update:brand="setFilter('brand', $event)"
                    @update:inStock="setFilter('in_stock', $event)"
                    @reset="resetFilters"
                />
            </div>

            <div>
                <!-- Header + sắp xếp -->
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ currentCategory?.name || 'Tất cả sản phẩm' }}
                        </h1>
                        <p class="text-sm text-gray-500 mt-1">{{ pagination.total ?? 0 }} sản phẩm</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Lọc mobile -->
                        <button
                            class="lg:hidden px-3 py-2 text-sm border border-gray-300 rounded-lg"
                            @click="filterOpen = !filterOpen"
                        >
                            Bộ lọc
                        </button>

                        <select
                            :value="filters.sort"
                            @change="setFilter('sort', $event.target.value)"
                            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white outline-none focus:border-brand-500"
                        >
                            <option value="newest">Mới nhất</option>
                            <option value="best_selling">Bán chạy nhất</option>
                            <option value="price_asc">Giá: Thấp đến cao</option>
                            <option value="price_desc">Giá: Cao đến thấp</option>
                            <option value="name_asc">Tên A-Z</option>
                            <option value="name_desc">Tên Z-A</option>
                        </select>
                    </div>
                </div>

                <!-- Filter mobile -->
                <div v-if="filterOpen" class="lg:hidden mb-6">
                    <ProductFilter
                        :categories="catalog.categories"
                        :brands="brands"
                        :filters="filters"
                        @update:category="setFilter('category', $event)"
                        @update:minPrice="setFilter('min_price', $event)"
                        @update:maxPrice="setFilter('max_price', $event)"
                        @update:brand="setFilter('brand', $event)"
                        @update:inStock="setFilter('in_stock', $event)"
                        @reset="resetFilters"
                    />
                </div>

                <ProductGrid :products="products" :loading="loading" />

                <!-- Phân trang -->
                <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 mt-10">
                    <button
                        v-for="page in pagination.last_page"
                        :key="page"
                        @click="goToPage(page)"
                        class="w-9 h-9 rounded-lg text-sm font-medium transition"
                        :class="page === pagination.current_page
                            ? 'bg-brand-600 text-white'
                            : 'bg-white border border-gray-300 text-gray-700 hover:border-brand-400'"
                    >
                        {{ page }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ProductFilter from '../components/ProductFilter.vue';
import ProductGrid from '../components/ProductGrid.vue';
import { useCatalogStore } from '../stores/catalog';

const route = useRoute();
const router = useRouter();
const catalog = useCatalogStore();

const products = ref([]);
const pagination = ref({});
const loading = ref(false);
const filterOpen = ref(false);
const brands = ref([]);

const filters = reactive({
    search: route.query.search || '',
    category: route.query.category || '',
    min_price: route.query.min_price || '',
    max_price: route.query.max_price || '',
    brand: route.query.brand || '',
    in_stock: route.query.in_stock === '1',
    sort: route.query.sort || 'newest',
});

const currentCategory = computed(() =>
    catalog.categories.find((c) => c.slug === filters.category) || null,
);

function setFilter(key, value) {
    filters[key] = value;
    syncQuery();
    load();
}

function resetFilters() {
    Object.assign(filters, {
        search: '',
        category: '',
        min_price: '',
        max_price: '',
        brand: '',
        in_stock: false,
        sort: 'newest',
    });

    syncQuery();
    load();
}

function syncQuery() {
    const query = {};

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== '' && value !== false && value !== null && value !== undefined) {
            query[key] = value === true ? '1' : value;
        }
    });

    router.push({ name: 'products', query });
}

function goToPage(page) {
    router.push({ name: 'products', query: { ...route.query, page } });
}

async function load() {
    loading.value = true;

    try {
        const params = { ...filters, per_page: 12 };

        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === false) {
                delete params[key];
            }
        });

        const data = await catalog.fetchProducts(params);

        products.value = data.data;
        pagination.value = data.meta || {};

        // Thu thập thương hiệu từ kết quả.
        const uniqueBrands = [...new Set(data.data.map((p) => p.brand).filter(Boolean))];
        brands.value = uniqueBrands;
    } finally {
        loading.value = false;
    }
}

watch(() => route.query, (query) => {
    filters.search = query.search || '';
    filters.category = query.category || '';
    filters.min_price = query.min_price || '';
    filters.max_price = query.max_price || '';
    filters.brand = query.brand || '';
    filters.in_stock = query.in_stock === '1';
    filters.sort = query.sort || 'newest';

    load();
}, { deep: true });

onMounted(async () => {
    await catalog.fetchCategories().catch(() => {});
    await load();
});
</script>
