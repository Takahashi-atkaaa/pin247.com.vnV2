<template>
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="max-w-7xl mx-auto px-4 py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Logo -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-9 h-9 rounded-lg bg-brand-600 text-white font-bold text-sm flex items-center justify-center">
                        P247
                    </span>
                    <span class="text-xl font-extrabold text-white">
                        PIN<span class="text-brand-400">247</span>
                    </span>
                </div>
                <p class="text-sm leading-relaxed text-gray-400">
                    Chuyên cung cấp pin lithium, pin máy khoan, cell 18650, 21700, mạch BMS,
                    bộ sạc và linh kiện đóng pin chính hãng.
                </p>
            </div>

            <!-- Danh mục -->
            <div>
                <h3 class="text-white font-semibold mb-4">Danh mục sản phẩm</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="category in catalog.categories" :key="category.id">
                        <RouterLink
                            :to="{ name: 'products', query: { category: category.slug } }"
                            class="hover:text-brand-400 transition"
                        >
                            {{ category.name }}
                        </RouterLink>
                    </li>
                </ul>
            </div>

            <!-- Chính sách -->
            <div>
                <h3 class="text-white font-semibold mb-4">Hỗ trợ</h3>
                <ul class="space-y-2 text-sm">
                    <li><RouterLink to="/order-tracking" class="hover:text-brand-400 transition">Tra cứu đơn hàng</RouterLink></li>
                    <li><span class="hover:text-brand-400 transition cursor-pointer">Chính sách bảo hành</span></li>
                    <li><span class="hover:text-brand-400 transition cursor-pointer">Chính sách vận chuyển</span></li>
                    <li><span class="hover:text-brand-400 transition cursor-pointer">Đổi trả sản phẩm</span></li>
                    <li><span class="hover:text-brand-400 transition cursor-pointer">Hướng dẫn mua hàng</span></li>
                </ul>
            </div>

            <!-- Liên hệ -->
            <div>
                <h3 class="text-white font-semibold mb-4">Liên hệ</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li>Hotline: 1900 6800</li>
                    <li>Email: contact@pin247.vn</li>
                    <li>Giờ làm việc: 8:00 - 21:00 (T2 - Ch7)</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-gray-800">
            <div class="max-w-7xl mx-auto px-4 py-5 text-xs text-gray-500 text-center">
                © {{ new Date().getFullYear() }} PIN247. Tồn kho được đồng bộ tập trung từ BigSeller.
            </div>
        </div>
    </footer>
</template>

<script setup>
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useCatalogStore } from '../../stores/catalog';

const catalog = useCatalogStore();

onMounted(() => {
    catalog.fetchCategories().catch(() => {});
});
</script>
