<template>
    <form @submit.prevent="submit" class="relative w-full">
        <input
            ref="inputRef"
            v-model="keyword"
            type="search"
            :placeholder="placeholder"
            class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 bg-gray-50 text-base sm:text-sm
                   focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition"
        />
        <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <button
            v-if="keyword"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-gray-200 text-gray-500"
            @click="clear"
            aria-label="Xoá tìm kiếm"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </form>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({
    placeholder: {
        type: String,
        default: 'Tìm theo tên sản phẩm, SKU, mã sản phẩm...',
    },
    /** Tự động focus vào ô khi component được mount (dùng cho drawer mobile). */
    autoFocus: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const router = useRouter();
const keyword = ref('');
const inputRef = ref(null);

function submit() {
    const value = keyword.value.trim();

    router.push({
        name: 'products',
        query: value ? { search: value } : {},
    });

    emit('submit', value);
}

function clear() {
    keyword.value = '';
    router.push({ name: 'products', query: {} });
}

watch(
    () => props.autoFocus,
    async (shouldFocus) => {
        if (!shouldFocus) {
            return;
        }

        await nextTick();
        inputRef.value?.focus();
    },
    { immediate: true },
);
</script>
