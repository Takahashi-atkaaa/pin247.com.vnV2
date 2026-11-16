<template>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <!--
            DESKTOP (>= md): bảng dạng table, cuộn ngang nếu quá rộng.
            MOBILE (< md) : bảng 7-9 cột bị bóp vỡ và khó đọc trên màn 390px
                          => chuyển sang dạng "card" xếp dọc, mỗi hàng là
                          một card, mỗi cột là một dòng label/value.
        -->
        <div class="hidden md:block overflow-x-auto">
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

        <!-- Mobile: dạng card -->
        <div class="md:hidden divide-y divide-gray-100">
            <div
                v-for="(row, index) in rows"
                :key="row.id ?? index"
                class="p-4 space-y-2.5"
            >
                <!-- Cột chính (thường là tên sản phẩm / khách hàng / mã đơn) -->
                <div
                    v-for="column in primaryColumns"
                    :key="column.key"
                    class="text-sm font-medium text-gray-900"
                >
                    <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                        {{ row[column.key] ?? '—' }}
                    </slot>
                </div>

                <!-- Các cột còn lại: label / value -->
                <dl class="space-y-1.5 pt-1">
                    <div
                        v-for="column in secondaryColumns"
                        v-show="hasValue(row, column.key)"
                        :key="column.key"
                        class="flex items-start justify-between gap-3 text-sm"
                    >
                        <dt class="text-gray-500 shrink-0">{{ column.label }}</dt>
                        <dd class="text-gray-800 text-right min-w-0 break-words">
                            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                {{ row[column.key] ?? '—' }}
                            </slot>
                        </dd>
                    </div>
                </dl>

                <!-- Thao tác -->
                <div
                    v-if="actionColumn"
                    class="flex flex-wrap gap-2 pt-2 border-t border-gray-100"
                >
                    <slot :name="`cell-${actionColumn.key}`" :row="row" :value="row[actionColumn.key]">
                        {{ row[actionColumn.key] ?? '—' }}
                    </slot>
                </div>
            </div>
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
            class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-gray-200 text-sm"
        >
            <p class="text-gray-500 text-center sm:text-left">
                Trang {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} bản ghi
            </p>
            <div class="flex gap-1 flex-wrap justify-center">
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

/** Cột thao tác (nếu có) — hiển thị riêng cuối card ở mobile. */
const actionColumn = computed(() => props.columns.find((c) => c.key === 'actions') || null);

/**
 * Cột hiển thị tiêu đề ở mobile.
 *
 * Ưu tiên cột được đánh dấu `primary: true` trong `columns`. Nếu không có
 * cột nào được đánh dấu thì lấy cột đầu tiên.
 */
const primaryColumns = computed(() => {
    const marked = props.columns.filter((c) => c.primary);

    if (marked.length) {
        return marked;
    }

    const first = props.columns[0];

    return first ? [first] : [];
});

/** Các cột còn lại hiển thị dạng label/value. */
const secondaryColumns = computed(() => {
    const primary = new Set(primaryColumns.value.map((c) => c.key));

    return props.columns.filter((c) => !primary.has(c.key) && c.key !== 'actions');
});

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

/**
 * Ẩn dòng label/value khi dữ liệu trống ở chế độ mobile.
 *
 * Ví dụ card sản phẩm có SKU rỗng -> bỏ dòng "SKU | —" cho gọn.
 */
function hasValue(row, key) {
    const value = row?.[key];

    return value !== null && value !== undefined && value !== '';
}
</script>
