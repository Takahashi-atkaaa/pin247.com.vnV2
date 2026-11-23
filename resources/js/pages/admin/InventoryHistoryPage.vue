<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <router-link
                    :to="{ name: 'admin-inventory' }"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600 mb-1"
                >
                    ← Quay lại danh sách tồn kho
                </router-link>
                <h1 class="text-xl font-bold text-gray-900">Lịch sử tồn kho</h1>
                <p class="text-sm text-gray-500 mt-0.5 font-mono">{{ sku }}</p>
            </div>
        </div>

        <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div v-if="loading" class="text-center py-16 text-sm text-gray-500">
                Đang tải lịch sử...
            </div>

            <div v-else-if="!history.length" class="text-center py-10 text-sm text-gray-500">
                Chưa có thay đổi nào được ghi nhận.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Thời điểm</th>
                            <th class="px-3 py-2 text-right font-semibold">Trước</th>
                            <th class="px-3 py-2 text-right font-semibold">Sau</th>
                            <th class="px-3 py-2 text-right font-semibold">Chênh lệch</th>
                            <th class="px-3 py-2 text-left font-semibold">Lý do</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="log in history" :key="log.id">
                            <td class="px-3 py-2 text-xs text-gray-600">
                                {{ formatDate(log.created_at, true) }}
                            </td>
                            <td class="px-3 py-2 text-right">{{ log.previous_available }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ log.new_available }}</td>
                            <td
                                class="px-3 py-2 text-right font-semibold"
                                :class="log.delta >= 0 ? 'text-green-600' : 'text-red-600'"
                            >
                                {{ log.delta > 0 ? '+' : '' }}{{ log.delta }}
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ log.reason }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import { errorMessage, formatDate } from '../../composables/useFormat';
import { notify } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();

const history = ref([]);
const loading = ref(false);

const sku = computed(() => route.params.sku || '');

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get(
            `/admin/inventory/history/${encodeURIComponent(sku.value)}`,
        );
        history.value = data.data || [];
    } catch (error) {
        notify(errorMessage(error), 'error');
        router.replace({ name: 'admin-inventory' });
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
