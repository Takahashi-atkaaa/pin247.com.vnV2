<template>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 text-sm">{{ title }}</h3>
            <span class="text-xs text-gray-500">{{ rows.length }} bản ghi</span>
        </div>

        <!-- DESKTOP (>= md): bảng 6 cột, cuộn ngang -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-gray-600 text-xs uppercase bg-white">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Thời điểm</th>
                        <th class="px-4 py-3 text-left font-semibold">Thao tác</th>
                        <th class="px-4 py-3 text-left font-semibold">Mã lần đồng bộ</th>
                        <th class="px-4 py-3 text-right font-semibold">HTTP</th>
                        <th class="px-4 py-3 text-right font-semibold">Thời gian</th>
                        <th class="px-4 py-3 text-left font-semibold">Kết quả</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                            {{ formatDate(row.created_at, true) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">
                                {{ row.operation }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ row.sync_run_id?.slice(0, 18) }}</td>
                        <td class="px-4 py-3 text-right text-xs" :class="statusClass(row)">
                            {{ row.response_status ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-gray-500">{{ row.duration_ms ?? '—' }} ms</td>
                        <td class="px-4 py-3">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="row.success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                            >
                                {{ row.success ? 'Thành công' : 'Lỗi' }}
                            </span>
                            <p v-if="row.error_message" class="text-xs text-red-600 mt-1 max-w-md">
                                {{ row.error_message }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- MOBILE (< md): dạng card xếp dọc -->
        <div class="md:hidden divide-y divide-gray-100">
            <div v-for="row in rows" :key="row.id" class="p-4 space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <span class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 break-all">
                        {{ row.operation }}
                    </span>
                    <span
                        class="px-2 py-1 rounded-full text-xs font-medium shrink-0"
                        :class="row.success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                    >
                        {{ row.success ? 'Thành công' : 'Lỗi' }}
                    </span>
                </div>

                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">HTTP</dt>
                        <dd class="text-xs font-medium" :class="statusClass(row)">
                            {{ row.response_status ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Thời gian</dt>
                        <dd class="text-xs text-gray-500">{{ row.duration_ms ?? '—' }} ms</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Thời điểm</dt>
                        <dd class="text-xs text-gray-600 text-right">{{ formatDate(row.created_at, true) }}</dd>
                    </div>
                </dl>

                <p v-if="row.error_message" class="text-xs text-red-600 pt-1 border-t border-gray-100">
                    {{ row.error_message }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { formatDate } from '../../composables/useFormat';

defineProps({
    rows: { type: Array, default: () => [] },
    title: { type: String, default: 'Lịch sử đồng bộ' },
});

function statusClass(row) {
    if (!row.response_status) {
        return 'text-red-600';
    }

    return row.response_status < 400 ? 'text-green-600' : 'text-red-600';
}
</script>
