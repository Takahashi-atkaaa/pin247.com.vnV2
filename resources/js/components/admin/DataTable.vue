<template>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 text-left font-semibold whitespace-nowrap"
                            :class="column.class"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="(row, index) in rows"
                        :key="row.id ?? index"
                        class="hover:bg-gray-50 transition"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 text-gray-700"
                            :class="column.class"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                {{ row[column.key] ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty -->
        <div v-if="!rows.length && !loading" class="text-center py-12">
            <p class="text-gray-500 text-sm">{{ emptyText }}</p>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="p-4 space-y-2">
            <div v-for="i in 5" :key="i" class="h-10 bg-gray-100 rounded animate-pulse" />
        </div>

        <!-- Pagination -->
        <div
            v-if="meta && meta.last_page > 1"
            class="flex items-center justify-between px-4 py-3 border-t border-gray-200 text-sm"
        >
            <p class="text-gray-500">
                Trang {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} bản ghi
            </p>
            <div class="flex gap-1">
                <button
                    v-for="page in visiblePages"
                    :key="page"
                    class="w-8 h-8 rounded text-sm transition"
                    :class="page === meta.current_page
                        ? 'bg-brand-600 text-white'
                        : 'border border-gray-300 text-gray-700 hover:border-brand-400'"
                    @click="$emit('page', page)"
                >
                    {{ page }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Không có dữ liệu.' },
});

defineEmits(['page']);

const visiblePages = computed(() => {
    if (!props.meta) {
        return [];
    }

    const total = props.meta.last_page;
    const current = props.meta.current_page;
    const pages = [];

    for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) {
        pages.push(i);
    }

    return pages;
});
</script>
