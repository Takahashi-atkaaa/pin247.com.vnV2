<template>
    <!--
        Biến thể "home": khối "Danh mục sản phẩm" nằm trong nội dung trang
        chủ (không ghim), khớp với bản gốc.
    -->
    <section v-if="variant === 'home'" class="max-w-7xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Danh mục sản phẩm</h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <RouterLink
                v-for="category in catalog.categories"
                :key="category.id"
                :to="{ name: 'products', query: { category: category.slug } }"
                class="border border-gray-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-md transition group"
            >
                <h3 class="font-semibold text-gray-900 group-hover:text-brand-600 transition">{{ category.name }}</h3>
                <p class="text-xs text-gray-400 mt-1">Xem sản phẩm →</p>
            </RouterLink>
        </div>
    </section>

    <!--
        Biến thể "bar": thanh danh mục ghim sticky dưới header, dùng cho các
        trang mua hàng (danh sách SP, chi tiết SP, giỏ hàng, thanh toán).
    -->
    <section
        v-else
        class="bg-white border-b border-gray-200"
        aria-label="Danh mục sản phẩm"
    >
        <div class="max-w-7xl mx-auto px-4 py-3 lg:py-4">
            <!--
                Tiêu đề chỉ hiện trên desktop. Trên mobile, thanh chip cuộn
                ngang tự nó đã nói lên mục đích nên không cần tiêu đề để tiết
                kiệm không gian màn hình.
            -->
            <h2 class="hidden lg:block text-lg font-bold text-gray-900 mb-3">Danh mục sản phẩm</h2>

            <!--
                Desktop: lưới 8 ô. Mobile: 1 hàng chip cuộn ngang để không
                chiếm quá nhiều màn hình.
            -->
            <div class="hidden lg:grid lg:grid-cols-8 gap-3">
                <RouterLink
                    v-for="category in catalog.categories"
                    :key="category.id"
                    :to="{ name: 'products', query: { category: category.slug } }"
                    class="border rounded-xl p-3 transition group"
                    :class="isActive(category.slug)
                        ? 'border-brand-500 bg-brand-50'
                        : 'border-gray-200 hover:border-brand-400 hover:shadow-md'"
                >
                    <h3
                        class="text-sm font-semibold transition group-hover:text-brand-600"
                        :class="isActive(category.slug) ? 'text-brand-700' : 'text-gray-900'"
                    >
                        {{ category.name }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">Xem sản phẩm →</p>
                </RouterLink>
            </div>

            <nav class="lg:hidden -mx-4 px-4 flex gap-2 overflow-x-auto no-scrollbar">
                <RouterLink
                    v-for="category in catalog.categories"
                    :key="category.id"
                    :to="{ name: 'products', query: { category: category.slug } }"
                    class="shrink-0 whitespace-nowrap border rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="isActive(category.slug)
                        ? 'border-brand-500 bg-brand-50 text-brand-700'
                        : 'border-gray-200 text-gray-700 active:bg-gray-100'"
                >
                    {{ category.name }}
                </RouterLink>
            </nav>
        </div>
    </section>
</template>

<script setup>
import { onMounted } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useCatalogStore } from '../../stores/catalog';

const props = defineProps({
    /**
     * `bar` — thanh danh mục ghim sticky (mặc định).
     * `home` — khối danh mục nằm trong nội dung trang chủ.
     */
    variant: { type: String, default: 'bar' },
});

const route = useRoute();
const catalog = useCatalogStore();

/** Đánh dấu danh mục đang được lọc trên URL (chỉ dùng cho thanh ghim). */
const isActive = (slug) => props.variant !== 'home' && route.query.category === slug;

onMounted(() => {
    catalog.fetchCategories().catch(() => {});
});
</script>
