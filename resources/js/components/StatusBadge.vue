<template>
    <span
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
        :class="colorClass"
    >
        <span class="w-1.5 h-1.5 rounded-full bg-current" />
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: '' },
});

const label = computed(() => props.label || props.status);

const colorClass = computed(() => {
    const map = {
        PENDING_SYNC: 'bg-amber-50 text-amber-700',
        SYNCING: 'bg-blue-50 text-blue-700',
        SYNCED: 'bg-green-50 text-green-700',
        SYNC_FAILED: 'bg-red-50 text-red-700',
        CANCEL_PENDING: 'bg-orange-50 text-orange-700',
        CANCELLED: 'bg-gray-100 text-gray-600',
        PROCESSING: 'bg-indigo-50 text-indigo-700',
        SHIPPED: 'bg-cyan-50 text-cyan-700',
        COMPLETED: 'bg-green-100 text-green-800',
        PENDING: 'bg-amber-50 text-amber-700',
        FAILED: 'bg-red-50 text-red-700',
        PAID: 'bg-green-50 text-green-700',
        UNPAID: 'bg-gray-100 text-gray-600',
    };

    return map[props.status] || 'bg-gray-100 text-gray-600';
});
</script>
