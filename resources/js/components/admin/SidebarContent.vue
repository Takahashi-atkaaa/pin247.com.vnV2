<template>
    <!-- Logo -->
    <div class="h-16 flex items-center gap-2 px-4 border-b border-gray-800 shrink-0">
        <span class="w-8 h-8 rounded-lg bg-brand-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
            P247
        </span>
        <span v-if="!collapsed" class="font-bold text-white truncate">Admin PIN247</span>

        <!-- Nút đóng (chỉ drawer mobile) -->
        <button
            v-if="variant === 'mobile'"
            class="ml-auto w-9 h-9 -mr-1 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition"
            @click="$emit('navigate')"
            aria-label="Đóng menu"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Menu -->
    <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
        <RouterLink
            v-for="item in menu"
            :key="item.name"
            :to="{ name: item.name }"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition group"
            :class="isActive(item.name)
                ? 'bg-brand-600 text-white'
                : 'hover:bg-gray-800 hover:text-white'"
            :title="collapsed ? item.label : undefined"
            @click="$emit('navigate')"
        >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
            <span
                v-if="item.badge && !collapsed"
                class="ml-auto px-2 py-0.5 rounded-full bg-red-500 text-white text-[11px] font-bold"
            >
                {{ item.badge }}
            </span>
        </RouterLink>
    </nav>

    <!-- Thu gọn (chỉ sidebar cố định desktop) -->
    <button
        v-if="variant === 'desktop'"
        class="hidden lg:flex items-center gap-2 px-4 py-3 border-t border-gray-800 text-xs text-gray-500 hover:text-white transition"
        @click="$emit('toggle-collapse')"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                :d="collapsed ? 'M9 5l7 7-7 7' : 'M15 5l-7 7 7 7'"
            />
        </svg>
        <span v-if="!collapsed">Thu gọn</span>
    </button>
</template>

<script setup>
import { RouterLink, useRoute } from 'vue-router';

defineProps({
    /** true = sidebar cố định desktop (có thể thu gọn còn 64px). */
    collapsed: { type: Boolean, default: false },
    /** 'desktop' | 'mobile' — quyết định hiện nút thu gọn hay nút đóng. */
    variant: { type: String, default: 'desktop' },
});

defineEmits(['navigate', 'toggle-collapse']);

const route = useRoute();

const menu = [
    {
        name: 'admin-dashboard',
        label: 'Tổng quan',
        icon: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25V15.75z',
    },
    {
        name: 'admin-products',
        label: 'Sản phẩm',
        icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
    },
    {
        name: 'admin-inventory',
        label: 'Tồn kho',
        icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
    },
    {
        name: 'admin-orders',
        label: 'Đơn hàng',
        icon: 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z',
    },
    {
        name: 'admin-price-list',
        label: 'Bảng giá',
        icon: 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        name: 'admin-customers',
        label: 'Khách hàng',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
    {
        name: 'admin-bigseller',
        label: 'BigSeller',
        icon: 'M7.5 21h9m-9-3h9m-9-3h9m-9-3h9M5.25 21h13.5a2.25 2.25 0 002.25-2.25V5.25A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25v13.5A2.25 2.25 0 005.25 21z',
    },
];

const isActive = (name) => route.name === name;
</script>
