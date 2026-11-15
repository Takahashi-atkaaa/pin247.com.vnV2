<template>
    <div class="max-w-7xl mx-auto px-4 py-16 text-center">
        <p class="text-6xl font-extrabold text-brand-600 mb-4">404</p>
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Không tìm thấy trang bạn yêu cầu</h1>
        <p class="text-gray-500 mb-6">Đường dẫn có thể đã thay đổi hoặc không tồn tại.</p>
        <RouterLink
            :to="{ name: 'home' }"
            class="inline-block px-6 py-3 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 transition"
        >
            Về trang chủ
        </RouterLink>
    </div>
</template>

<script setup>
import { RouterLink } from 'vue-router';
</script>
