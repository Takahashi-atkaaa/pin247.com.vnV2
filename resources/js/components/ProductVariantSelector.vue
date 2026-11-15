<template>
    <div v-if="variants.length" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ optionLabel }}
            </label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="variant in variants"
                    :key="variant.id"
                    type="button"
                    @click="$emit('select', variant)"
                    :disabled="variant.available_stock <= 0"
                    class="px-4 py-2 rounded-lg border text-sm font-medium transition
                           disabled:opacity-40 disabled:cursor-not-allowed"
                    :class="selectedId === variant.id
                        ? 'bg-brand-600 text-white border-brand-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-brand-400 hover:text-brand-600'"
                >
                    {{ variant.option_1_value || variant.name }}
                    <span class="text-xs opacity-75 block">{{ formatCurrency(variant.effective_price) }}</span>
                </button>
            </div>
        </div>

        <!-- Tồn kho biến thể đang chọn -->
        <div v-if="selected" class="flex items-center gap-2 text-sm">
            <span class="text-gray-500">Tồn kho biến thể này:</span>
            <span
                class="font-semibold"
                :class="selected.available_stock > 0 ? 'text-green-600' : 'text-red-500'"
            >
                {{ selected.available_stock > 0 ? `${selected.available_stock} sản phẩm` : 'Tạm hết hàng' }}
            </span>
            <span class="text-gray-400">· SKU: {{ selected.sku }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from '../composables/useFormat';

const props = defineProps({
    variants: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
});

defineEmits(['select']);

const optionLabel = computed(() => {
    const first = props.variants[0];

    return first?.option_1_name || 'Phiên bản';
});

const selected = computed(() =>
    props.variants.find((v) => v.id === props.selectedId) || null,
);
</script>
