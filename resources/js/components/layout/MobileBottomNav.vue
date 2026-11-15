<template>
    <!--
        Thanh điều hướng dưới cùng, chỉ hiển thị trên mobile.
        Giúp khách chạm bằng ngón cái tới các mục chính mà không phải cuộn
        lên đầu trang.
    -->
    <nav
        class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]"
        aria-label="Điều hướng nhanh"
    >
        <ul class="grid grid-cols-4">
            <li v-for="item in items" :key="item.key">
                <RouterLink
                    :to="item.to"
                    class="relative flex flex-col items-center justify-center gap-0.5 h-[56px] text-[11px] font-medium transition"
                    :class="isActive(item.key) ? 'text-brand-600' : 'text-gray-500'"
                >
                    <span
                        class="absolute top-0 left-1/2 -translate-x-1/2 h-0.5 w-8 rounded-b-full transition"
                        :class="isActive(item.key) ? 'bg-brand-600' : 'bg-transparent'"
                    />

                    <span class="relative">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>

                        <span
                            v-if="item.key === 'cart' && cart.count > 0"
                            class="absolute -top-1.5 -right-2 min-w-[16px] h-[16px] px-1 bg-brand-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
                        >
                            {{ cart.count }}
                        </span>
                    </span>

                    {{ item.label }}
                </RouterLink>
            </li>
        </ul>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useCartStore } from '../../stores/cart';

const route = useRoute();
const cart = useCartStore();
const auth = useAuthStore();

const items = computed(() => [
    {
        key: 'home',
        label: 'Trang chủ',
        to: { name: 'home' },
        icon: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
    },
    {
        key: 'products',
        label: 'Sản phẩm',
        to: { name: 'products' },
        icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
    },
    {
        key: 'cart',
        label: 'Giỏ hàng',
        to: { name: 'cart' },
        icon: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
    },
    {
        key: 'account',
        label: 'Tài khoản',
        to: auth.isAuthenticated
            ? (auth.isAdmin ? { name: 'admin-dashboard' } : { name: 'account' })
            : { name: 'login' },
        icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
    },
]);

/** Đánh dấu mục đang active theo route hiện tại. */
function isActive(key) {
    if (key === 'home') {
        return route.name === 'home';
    }

    if (key === 'products') {
        return route.name === 'products' || route.name === 'product-detail';
    }

    if (key === 'cart') {
        return route.name === 'cart' || route.name === 'checkout';
    }

    if (key === 'account') {
        return route.name === 'login' || route.name === 'register' || route.name === 'account';
    }

    return false;
}
</script>
