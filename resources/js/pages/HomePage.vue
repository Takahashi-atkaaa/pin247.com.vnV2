<template>
    <div>
        <!-- Banner -->
        <section class="bg-gradient-to-r from-brand-700 via-brand-600 to-brand-500 text-white">
            <div class="max-w-7xl mx-auto px-4 py-16 md:py-24 grid md:grid-cols-2 gap-10 items-center">
                <div>
                    <span class="inline-block bg-white/20 backdrop-blur px-3 py-1 rounded-full text-xs font-semibold mb-4">
                        Giải pháp năng lượng chuyên nghiệp
                    </span>
                    <h1 class="text-3xl md:text-5xl font-extrabold leading-tight mb-4">
                        Pin Lithium & Pin Máy Khoan chính hãng
                    </h1>
                    <p class="text-brand-50 text-base md:text-lg mb-8 max-w-lg">
                        Cell 18650, 21700, mạch BMS, bộ sạc và linh kiện đóng pin —
                        tồn kho đồng bộ tập trung từ BigSeller, giao hàng toàn quốc.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <RouterLink
                            :to="{ name: 'products' }"
                            class="px-6 py-3 bg-white text-brand-700 font-semibold rounded-lg hover:bg-brand-50 transition"
                        >
                            Khám phá sản phẩm
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'order-tracking' }"
                            class="px-6 py-3 bg-white/15 backdrop-blur border border-white/40 font-semibold rounded-lg hover:bg-white/25 transition"
                        >
                            Tra cứu đơn hàng
                        </RouterLink>
                    </div>
                </div>

                <div class="hidden md:grid grid-cols-2 gap-4">
                    <div
                        v-for="item in highlights"
                        :key="item.title"
                        class="bg-white/10 backdrop-blur border border-white/20 rounded-xl p-5"
                    >
                        <svg class="w-8 h-8 mb-3" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                    <h3 class="font-semibold mb-1">{{ item.title }}</h3>
                    <p class="text-sm text-brand-50/90">{{ item.text }}</p>
                </div>
            </div>
            </div>
        </section>

        <!-- Danh mục nhanh -->
        <CategoryNav variant="home" />

        <!-- Sản phẩm nổi bật -->
        <section class="max-w-7xl mx-auto px-4 pb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Sản phẩm nổi bật</h2>
                <RouterLink :to="{ name: 'products' }" class="text-sm text-brand-600 hover:underline font-medium">
                    Xem tất cả →
                </RouterLink>
            </div>
            <ProductGrid :products="featured" :loading="loading" />
        </section>

        <!-- Sản phẩm mới -->
        <section class="bg-gray-50 py-12">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Sản phẩm mới</h2>
                    <RouterLink :to="{ name: 'products', query: { sort: 'newest' } }" class="text-sm text-brand-600 hover:underline font-medium">
                        Xem tất cả →
                    </RouterLink>
                </div>
                <ProductGrid :products="newProducts" :loading="loading" />
            </div>
        </section>

        <!-- Sản phẩm bán chạy -->
        <section class="max-w-7xl mx-auto px-4 py-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Sản phẩm bán chạy</h2>
                <RouterLink :to="{ name: 'products', query: { sort: 'best_selling' } }" class="text-sm text-brand-600 hover:underline font-medium">
                    Xem tất cả →
                </RouterLink>
            </div>
            <ProductGrid :products="bestSelling" :loading="loading" />
        </section>

        <!-- Chính sách -->
        <section class="bg-gray-900 text-white py-14">
            <div class="max-w-7xl mx-auto px-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <div v-for="policy in policies" :key="policy.title">
                    <svg class="w-10 h-10 text-brand-400 mb-3" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                    </svg>
                    <h3 class="font-semibold text-lg mb-2">{{ policy.title }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ policy.text }}</p>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import ProductGrid from '../components/ProductGrid.vue';
import CategoryNav from '../components/layout/CategoryNav.vue';
import { useCatalogStore } from '../stores/catalog';

const catalog = useCatalogStore();

const loading = ref(true);
const featured = ref([]);
const newProducts = ref([]);
const bestSelling = ref([]);

const highlights = [
    { title: 'Cell chính hãng', text: '18650, 21700 từ Samsung, LG, Molicel' },
    { title: 'Mạch BMS chuẩn', text: 'Bảo vệ quá tải, quá nhiệt, ngắn mạch' },
    { title: 'Bộ sạc chuyên dụng', text: 'Sạc nhanh, cân bằng cell chuẩn' },
    { title: 'Tồn kho chuẩn', text: 'Đồng bộ tập trung từ BigSeller' },
];

const policies = [
    {
        title: 'Chính sách bảo hành',
        text: 'Bảo hành 12 tháng cho pin và 6 tháng cho mạch BMS, bộ sạc. Đổi mới sản phẩm lỗi nhà sản xuất trong 7 ngày.',
    },
    {
        title: 'Chính sách vận chuyển',
        text: 'Giao hàng toàn quốc 24-48 giờ. Miễn phí vận chuyển cho đơn từ 500.000đ. Đóng gói chống sốc cho cell pin.',
    },
    {
        title: 'Đổi trả dễ dàng',
        text: 'Trong vòng 7 ngày nếu sản phẩm không đúng cam kết. Hoàn tiền trong 3-5 ngày làm việc sau khi nhận hàng.',
    },
];

onMounted(async () => {
    loading.value = true;

    try {
        catalog.fetchCategories().catch(() => {});

        const [f, n, b] = await Promise.all([
            catalog.fetchProducts({ is_featured: 1, per_page: 8 }),
            catalog.fetchProducts({ sort: 'newest', per_page: 8 }),
            catalog.fetchProducts({ sort: 'best_selling', per_page: 8 }),
        ]);

        featured.value = f.data;
        newProducts.value = n.data;
        bestSelling.value = b.data;
    } finally {
        loading.value = false;
    }
});
</script>
