<template>
    <div class="flex gap-4 py-4 border-b border-gray-100 last:border-0">
        <div class="w-20 h-20 rounded-lg bg-gray-50 overflow-hidden shrink-0">
            <img
                v-if="item.image"
                :src="item.image"
                :alt="item.product_name"
                class="w-full h-full object-cover"
            />
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ item.product_name }}</p>
            <p v-if="item.variant_name" class="text-xs text-gray-500 mt-0.5">{{ item.variant_name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">SKU: {{ item.sku }}</p>

            <div class="flex items-center justify-between mt-3">
                <div class="flex items-center border border-gray-300 rounded-lg">
                    <button
                        type="button"
                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-l-lg transition"
                        @click="$emit('update', item.sku, item.quantity - 1)"
                        aria-label="Giảm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                        </svg>
                    </button>
                    <input
                        type="number"
                        :value="item.quantity"
                        min="1"
                        :max="item.max_stock || undefined"
                        @change="$emit('update', item.sku, $event.target.value)"
                        class="w-12 h-8 text-center text-sm border-0 outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                    />
                    <button
                        type="button"
                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-r-lg transition"
                        @click="$emit('update', item.sku, item.quantity + 1)"
                        aria-label="Tăng"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>

                <div class="flex items-center gap-4">
                    <span class="text-sm font-bold text-gray-900">
                        {{ formatCurrency(item.price * item.quantity) }}
                    </span>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-red-500 transition"
                        @click="$emit('remove', item.sku)"
                        aria-label="Xoá"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>

            <p v-if="item.max_stock > 0 && item.quantity >= item.max_stock" class="text-xs text-amber-600 mt-2">
                Đã đạt tối đa tồn kho hiện có ({{ item.max_stock }}).
            </p>
        </div>
    </div>
</template>

<script setup>
import { formatCurrency } from '../composables/useFormat';

defineProps({
    item: { type: Object, required: true },
});

defineEmits(['update', 'remove']);
</script>
