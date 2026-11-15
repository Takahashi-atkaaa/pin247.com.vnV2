<template>
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-30">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">{{ title }}</h1>
        </div>

        <div class="flex items-center gap-3">
            <!-- Mock mode badge -->
            <span
                v-if="isMock"
                class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-medium"
                title="Đang dùng BigSeller MOCK client — KHÔNG phải kết nối thật"
            >
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500" />
                Chế độ MOCK
            </span>

            <RouterLink
                to="/"
                class="px-3 py-2 text-sm text-gray-600 hover:text-brand-600 hover:bg-gray-100 rounded-lg transition"
            >
                Xem website
            </RouterLink>

            <div class="flex items-center gap-2 pl-3 border-l border-gray-200">
                <div class="w-8 h-8 rounded-full bg-brand-600 text-white text-xs font-bold flex items-center justify-center">
                    {{ initials }}
                </div>
                <div class="hidden md:block">
                    <p class="text-sm font-medium text-gray-900 leading-tight">{{ auth.user?.name }}</p>
                    <p class="text-xs text-gray-500 leading-tight">Administrator</p>
                </div>
                <button
                    class="p-2 text-gray-400 hover:text-red-500 transition"
                    title="Đăng xuất"
                    @click="logout"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const isMock = ref(false);

const title = computed(() => {
    const map = {
        'admin-dashboard': 'Tổng quan',
        'admin-products': 'Quản lý sản phẩm',
        'admin-inventory': 'Quản lý tồn kho',
        'admin-orders': 'Quản lý đơn hàng',
        'admin-price-list': 'Quản lý bảng giá',
        'admin-customers': 'Quản lý khách hàng',
        'admin-bigseller': 'Đồng bộ BigSeller',
    };

    return map[router.currentRoute.value.name] || 'Quản trị';
});

const initials = computed(() => {
    const name = auth.user?.name || 'A';

    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
});

async function logout() {
    await auth.logout();
    router.push('/login');
}

onMounted(async () => {
    try {
        const { data } = await api.get('/admin/bigseller/status');
        isMock.value = data.data.is_mock;
    } catch {
        isMock.value = false;
    }
});
</script>
