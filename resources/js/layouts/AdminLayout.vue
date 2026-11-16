<template>
    <div class="min-h-screen bg-gray-50 flex">
        <AdminSidebar />

        <div class="flex-1 flex flex-col min-w-0">
            <AdminHeader />

            <!--
                `overflow-x-auto` ở main + `min-w-0` ở cột chứa giúp các bảng
                admin rộng (tồn kho 9 cột, đơn hàng 7 cột) cuộn ngang bên
                trong vùng nội dung thay vì làm vỡ toàn bộ layout.

                Padding giảm ở mobile: `p-3` thay vì `p-6` — nếu giữ `p-6`
                (24px mỗi cạnh) thì trên màn 390px chỉ còn 342px cho bảng.
            -->
            <main class="flex-1 p-3 sm:p-4 lg:p-6 overflow-x-auto">
                <div class="min-w-0">
                    <RouterView />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { RouterView } from 'vue-router';
import AdminSidebar from '../components/admin/AdminSidebar.vue';
import AdminHeader from '../components/admin/AdminHeader.vue';
</script>
