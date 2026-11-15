<template>
    <div class="min-h-screen bg-gray-50 flex">
        <AdminSidebar />

        <div class="flex-1 flex flex-col min-w-0">
            <AdminHeader />

            <main class="flex-1 p-6 overflow-x-auto">
                <RouterView />
            </main>
        </div>
    </div>
</template>

<script setup>
import { RouterView } from 'vue-router';
import AdminSidebar from '../components/admin/AdminSidebar.vue';
import AdminHeader from '../components/admin/AdminHeader.vue';
</script>
