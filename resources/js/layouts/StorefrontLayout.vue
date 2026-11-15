<template>
    <div class="min-h-screen flex flex-col bg-white pb-[60px] md:pb-0">
        <!--
            Header luôn ghim ở đầu trang. Thanh danh mục chỉ xuất hiện ở các
            trang mua hàng (route có meta.pinCategories) để khách chuyển danh
            mục nhanh mà không chiếm không gian ở trang đăng nhập / tra cứu.
        -->
        <div class="sticky top-0 z-40 bg-white shadow-sm">
            <AppHeader />

            <CategoryNav v-if="showCategoryNav" />
        </div>

        <!--
            `pb-20` trên mobile chừa chỗ cho thanh điều hướng dưới cùng
            (fixed) để nội dung cuối trang không bị che.
        -->
        <main class="flex-1">
            <RouterView />
        </main>

        <AppFooter />

        <MobileBottomNav />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import AppHeader from '../components/layout/AppHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';
import CategoryNav from '../components/layout/CategoryNav.vue';
import MobileBottomNav from '../components/layout/MobileBottomNav.vue';

const route = useRoute();

/** Chỉ ghim thanh danh mục ở các trang mua hàng. */
const showCategoryNav = computed(() => route.meta.pinCategories === true);
</script>
