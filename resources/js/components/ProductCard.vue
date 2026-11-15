<template>
    <div class="group bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg hover:border-brand-300 transition-all">
        <!-- Ảnh -->
        <RouterLink
            :to="{ name: 'product-detail', params: { slug: product.slug } }"
            class="block relative aspect-square bg-gray-50 overflow-hidden"
        >
            <img
                v-if="product.image"
                :src="product.image"
                :alt="product.name"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="lazy"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a3 3 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a3 3 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021 19.5V6a1.5 1.5 0 00-1.5-1.5H4.5A1.5 1.5 0 003 6v13.5A1.5 1.5 0 004.5 21z" />
                </svg>
            </div>

            <!-- Badge -->
            <span
                v-if="product.is_on_sale"
                class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded"
            >
                Giảm giá
            </span>
            <span
                v-if="product.is_new"
                class="absolute top-2 right-2 bg-brand-600 text-white text-xs font-bold px-2 py-1 rounded"
            >
                Mới
            </span>

            <!-- Hết hàng -->
            <div
                v-if="stock <= 0"
                class="absolute inset-0 bg-white/70 flex items-center justify-center"
            >
                <span class="text-sm font-bold text-gray-700 bg-white px-3 py-1.5 rounded shadow">
                    Tạm hết hàng
                </span>
            </div>
        </RouterLink>

        <!-- Nội dung -->
        <div class="p-3 sm:p-4">
            <RouterLink :to="{ name: 'product-detail', params: { slug: product.slug } }">
                <p v-if="product.brand" class="text-xs text-brand-600 font-medium mb-1">{{ product.brand }}</p>

                <h3 class="text-xs sm:text-sm font-semibold text-gray-900 line-clamp-2 min-h-[36px] sm:min-h-[40px] group-hover:text-brand-600 transition">
                    {{ product.name }}
                </h3>

                <p v-if="product.sku" class="text-xs text-gray-400 mt-1">SKU: {{ product.sku }}</p>
            </RouterLink>

            <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                <span class="text-base sm:text-lg font-bold text-gray-900">{{ formatCurrency(product.effective_price) }}</span>
                <span
                    v-if="product.is_on_sale"
                    class="text-xs text-gray-400 line-through"
                >
                    {{ formatCurrency(product.price) }}
                </span>
            </div>

            <p v-if="stock > 0 && stock <= 5" class="text-xs text-amber-600 font-medium mt-1">
                Chỉ còn {{ stock }} sản phẩm
            </p>

            <!-- Thêm vào giỏ -->
            <button
                v-if="canQuickAdd"
                type="button"
                class="mt-3 w-full flex items-center justify-center gap-2 px-3 py-2.5 sm:py-2.5 text-sm font-semibold rounded-lg transition active:scale-[0.98]"
                :class="added
                    ? 'bg-green-50 text-green-700 border border-green-200'
                    : 'bg-brand-600 text-white active:bg-brand-700'"
                @click="quickAdd"
            >
                <svg v-if="!added" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                {{ added ? 'Đã thêm vào giỏ' : 'Thêm vào giỏ' }}
            </button>

            <!-- Nhiều biến thể: bấm card để chọn -->
            <RouterLink
                v-else-if="stock > 0"
                :to="{ name: 'product-detail', params: { slug: product.slug } }"
                class="mt-3 w-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold rounded-lg border border-brand-200 text-brand-600 transition active:scale-[0.98] active:bg-brand-50"
            >
                Chọn biến thể
            </RouterLink>

            <p v-if="message" class="mt-2 text-xs text-red-600">{{ message }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { formatCurrency } from '../composables/useFormat';
import { useCartStore } from '../stores/cart';

const props = defineProps({
    product: { type: Object, required: true },
});

const cart = useCartStore();

const added = ref(false);
const message = ref('');

const stock = computed(() => props.product.total_available_stock ?? 0);

/**
 * Chỉ thêm nhanh được khi sản phẩm có đúng 1 biến thể (hoặc không có biến
 * thể nào). Nhiều biến thể thì bắt buộc vào trang chi tiết để chọn.
 */
const canQuickAdd = computed(
    () => stock.value > 0 && (props.product.variants_count ?? 0) <= 1 && !!props.product.default_variant,
);

let addedTimer = null;

function quickAdd() {
    const variant = props.product.default_variant;

    const result = cart.add(
        {
            id: props.product.id,
            name: props.product.name,
            sku: variant.sku,
            image: variant.image ?? props.product.image,
            effective_price: variant.price,
            total_available_stock: variant.available_stock,
        },
        {
            sku: variant.sku,
            name: variant.name,
            image: variant.image,
            effective_price: variant.price,
            available_stock: variant.available_stock,
        },
        1,
    );

    if (!result.ok) {
        message.value = result.message;

        return;
    }

    message.value = '';
    added.value = true;

    clearTimeout(addedTimer);
    addedTimer = setTimeout(() => {
        added.value = false;
    }, 2000);
}
</script>
