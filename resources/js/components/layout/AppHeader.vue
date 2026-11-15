<template>
    <header class="bg-white">
        <!-- Top bar -->
        <div class="hidden sm:block bg-brand-600 text-white text-xs">
            <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between">
                <span>Chuyên pin lithium, pin máy khoan, mạch BMS — Hàng chính hãng</span>
                <RouterLink to="/order-tracking" class="hover:underline font-medium">
                    Tra cứu đơn hàng
                </RouterLink>
            </div>
        </div>

        <!-- Main bar -->
        <div class="max-w-7xl mx-auto px-4 h-14 sm:h-auto sm:py-4 flex items-center gap-3 sm:gap-4">
            <RouterLink to="/" class="flex items-center gap-2 shrink-0">
                <span class="w-9 h-9 rounded-lg bg-brand-600 text-white font-bold text-sm flex items-center justify-center">
                    P247
                </span>
                <span class="text-xl font-extrabold tracking-tight text-gray-900 hidden sm:block">
                    PIN<span class="text-brand-600">247</span>
                </span>
            </RouterLink>

            <ProductSearch class="hidden md:block flex-1" />

            <div class="flex items-center gap-1 ml-auto">
                <!--
                    Mobile: nút tìm kiếm mở drawer, giúp thanh header gọn
                    lại (không cần ô search chiếm nguyên một hàng).
                -->
                <button
                    class="md:hidden w-11 h-11 -mr-1 flex items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 transition"
                    @click="searchOpen = true"
                    aria-label="Tìm kiếm"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </button>

                <RouterLink
                    to="/cart"
                    class="relative w-11 h-11 flex items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 transition"
                    aria-label="Giỏ hàng"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                    <span
                        v-if="cart.count > 0"
                        class="absolute top-1 right-0.5 min-w-[18px] h-[18px] px-1 bg-brand-600 text-white text-[11px] font-bold rounded-full flex items-center justify-center"
                    >
                        {{ cart.count }}
                    </span>
                </RouterLink>

                <RouterLink
                    v-if="auth.isAuthenticated"
                    to="/admin/dashboard"
                    class="hidden md:inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:text-brand-600 hover:bg-gray-100 rounded-lg transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    {{ auth.isAdmin ? 'Quản trị' : 'Tài khoản' }}
                </RouterLink>
                <RouterLink
                    v-else
                    to="/login"
                    class="hidden md:inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:text-brand-600 hover:bg-gray-100 rounded-lg transition"
                >
                    Đăng nhập
                </RouterLink>

                <button
                    class="md:hidden w-11 h-11 -mr-2 flex items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    :aria-expanded="mobileMenuOpen"
                    aria-label="Menu"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Drawer tìm kiếm (mobile) -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="searchOpen" class="md:hidden fixed inset-0 z-50 bg-black/40" @click.self="searchOpen = false">
                <div class="bg-white px-4 pt-4 pb-3 shadow-lg">
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <ProductSearch auto-focus @submit="searchOpen = false" />
                        </div>
                        <button
                            class="w-11 h-11 -mr-2 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100"
                            @click="searchOpen = false"
                            aria-label="Đóng"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Mobile menu -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="mobileMenuOpen" class="md:hidden fixed inset-0 z-50 bg-black/40" @click.self="mobileMenuOpen = false">
                <div class="bg-white max-h-[80vh] overflow-y-auto shadow-lg">
                    <div class="flex items-center justify-between px-4 h-14 border-b border-gray-200 sticky top-0 bg-white">
                        <span class="font-semibold text-gray-900">Danh mục</span>
                        <button
                            class="w-11 h-11 -mr-2 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100"
                            @click="mobileMenuOpen = false"
                            aria-label="Đóng menu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <ul class="px-4 py-2">
                        <li v-for="category in catalog.categories" :key="category.id">
                            <RouterLink
                                :to="{ name: 'products', query: { category: category.slug } }"
                                class="flex items-center justify-between py-3 text-sm font-medium text-gray-700 border-b border-gray-100 last:border-0"
                                @click="mobileMenuOpen = false"
                            >
                                {{ category.name }}
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </RouterLink>
                        </li>
                        <li class="pt-2 pb-3">
                            <RouterLink
                                v-if="auth.isAuthenticated"
                                to="/admin/dashboard"
                                class="block py-3 text-sm font-semibold text-brand-600"
                                @click="mobileMenuOpen = false"
                            >
                                {{ auth.isAdmin ? 'Quản trị' : 'Tài khoản' }}
                            </RouterLink>
                            <RouterLink
                                v-else
                                to="/login"
                                class="block py-3 text-sm font-semibold text-brand-600"
                                @click="mobileMenuOpen = false"
                            >
                                Đăng nhập
                            </RouterLink>
                        </li>
                    </ul>
                </div>
            </div>
        </Transition>
    </header>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import ProductSearch from '../ProductSearch.vue';
import { useCartStore } from '../../stores/cart';
import { useAuthStore } from '../../stores/auth';
import { useCatalogStore } from '../../stores/catalog';

const cart = useCartStore();
const auth = useAuthStore();
const catalog = useCatalogStore();

const mobileMenuOpen = ref(false);
const searchOpen = ref(false);

onMounted(() => {
    catalog.fetchCategories().catch(() => {});
});
</script>
