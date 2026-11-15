<template>
    <div class="bg-white border border-gray-200 rounded-xl p-4">
        <p class="text-xs text-gray-500">{{ label }}</p>
        <p class="text-2xl font-bold mt-1" :class="valueClass">{{ value }}</p>
        <p v-if="hint" class="text-xs text-gray-400 mt-1">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: 0 },
    tone: { type: String, default: 'default' },
    hint: { type: String, default: '' },
});

const valueClass = computed(() => {
    const map = {
        default: 'text-gray-900',
        success: 'text-green-600',
        warning: 'text-amber-600',
        danger: 'text-red-600',
        info: 'text-blue-600',
    };

    return map[props.tone] || map.default;
});
</script>
