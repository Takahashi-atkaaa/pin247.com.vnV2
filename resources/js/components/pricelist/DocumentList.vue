<template>
    <div v-if="documents.length" class="space-y-2">
        <a
            v-for="doc in documents"
            :key="doc.id"
            :href="downloadUrl(doc)"
            target="_blank"
            rel="noopener"
            class="flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-xl hover:border-brand-400 hover:shadow-sm transition group"
        >
            <span class="w-10 h-10 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </span>

            <span class="min-w-0 flex-1">
                <span class="block font-medium text-gray-900 text-sm truncate group-hover:text-brand-600 transition">
                    {{ doc.title }}
                </span>
                <span class="block text-xs text-gray-500 truncate">
                    {{ doc.original_name }} • {{ doc.size_label }}
                </span>
                <span v-if="doc.description" class="block text-xs text-gray-400 truncate">
                    {{ doc.description }}
                </span>
            </span>

            <svg class="w-5 h-5 text-gray-300 group-hover:text-brand-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
        </a>
    </div>

    <div v-else class="p-4 bg-gray-50 border border-dashed border-gray-300 rounded-xl text-sm text-gray-500">
        Chưa có file bảng giá để tải. Liên hệ PIN247 để nhận bảng giá mới nhất.
    </div>
</template>

<script setup>
import { APP_BASE } from '../../config';

defineProps({
    documents: { type: Array, default: () => [] },
});

/**
 * Đường dẫn tải file — ghép APP_BASE vì app nằm trong thư mục con (XAMPP).
 */
function downloadUrl(doc) {
    if (doc.download_path) {
        return `${APP_BASE}/${doc.download_path}`;
    }

    return doc.download_url || '#';
}
</script>
