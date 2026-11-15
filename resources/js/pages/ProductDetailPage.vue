<template>
    <div v-if="loading" class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid lg:grid-cols-2 gap-10">
            <div class="aspect-square bg-gray-100 rounded-xl animate-pulse" />
            <div class="space-y-4">
                <div class="h-8 bg-gray-100 rounded animate-pulse" />
                <div class="h-6 w-1/3 bg-gray-100 rounded animate-pulse" />
                <div class="h-24 bg-gray-100 rounded animate-pulse" />
            </div>
        </div>
    </div>

    <div v-else-if="product" class="max-w-7xl mx-auto px-4 py-8">
        <nav class="text-sm text-gray-500 mb-6">
            <RouterLink to="/" class="hover:text-brand-600">Trang chủ</RouterLink>
            <span class="mx-2">/</span>
            <RouterLink :to="{ name: 'products' }" class="hover:text-brand-600">Sản phẩm</RouterLink>
            <template v-if="product.category">
                <span class="mx-2">/</span>
                <RouterLink
                    :to="{ name: 'products', query: { category: product.category.slug } }"
                    class="hover:text-brand-600"
                >
                    {{ product.category.name }}
                </RouterLink>
            </template>
            <span class="mx-2">/</span>
            <span class="text-gray-800">{{ product.name }}</span>
        </nav>

        <div class="grid lg:grid-cols-2 gap-10">
            <!-- Ảnh -->
            <div>
                <div class="aspect-square bg-gray-50 rounded-xl overflow-hidden border border-gray-200">
                    <img
                        v-if="activeImage"
                        :src="activeImage"
                        :alt="product.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                        <svg class="w-20 h-20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021 19.5V6a1.5 1.5 0 00-1.5-1.5H4.5A1.5 1.5 0 003 6v13.5A1.5 1.5 0 004.5 21z" />
                        </svg>
                    </div>
                </div>

                <div v-if="product.images?.length > 1" class="flex gap-2 mt-3 overflow-x-auto no-scrollbar">
                    <button
                        v-for="image in product.images"
                        :key="image.id"
                        @click="activeImage = image.url"
                        class="w-16 h-16 rounded-lg overflow-hidden border-2 shrink-0 transition"
                        :class="activeImage === image.url ? 'border-brand-500' : 'border-gray-200'"
                    >
                        <img :src="image.url" :alt="image.alt_text" class="w-full h-full object-cover" />
                    </button>
                </div>
            </div>

            <!-- Thông tin -->
            <div>
                <p v-if="product.brand" class="text-sm text-brand-600 font-medium mb-2">{{ product.brand }}</p>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-3">{{ product.name }}</h1>

                <p class="text-sm text-gray-500 mb-5">
                    SKU: <span class="font-mono">{{ selectedVariant?.sku || product.sku || '—' }}</span>
                </p>

                <!-- Giá -->
                <div class="flex items-baseline gap-3 mb-5">
                    <span class="text-3xl font-bold text-gray-900">
                        {{ formatCurrency(currentPrice) }}
                    </span>
                    <span v-if="isOnSale" class="text-lg text-gray-400 line-through">
                        {{ formatCurrency(product.price) }}
                    </span>
                    <span
                        v-if="isOnSale"
                        class="px-2 py-0.5 bg-red-50 text-red-600 text-xs font-bold rounded"
                    >
                        -{{ discountPercent }}%
                    </span>
                </div>

                <!-- Biến thể -->
                <ProductVariantSelector
                    v-if="product.has_variants && product.variants?.length"
                    :variants="product.variants"
                    :selected-id="selectedVariantId"
                    @select="selectVariant"
                />

                <!-- Tồn kho -->
                <div class="mt-5 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm">
                        <span class="text-gray-600">Tồn kho khả dụng:</span>
                        <span :class="currentStock > 0 ? 'text-green-600 font-semibold' : 'text-red-500 font-semibold'">
                            {{ currentStock > 0 ? `${currentStock} sản phẩm` : 'Tạm hết hàng' }}
                        </span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        Tồn kho được đồng bộ tập trung từ BigSeller và cập nhật định kỳ.
                    </p>
                </div>

                <!-- Số lượng + thêm giỏ -->
                <div class="flex flex-wrap items-center gap-4 mt-6">
                    <div class="flex items-center border border-gray-300 rounded-lg">
                        <button
                            class="w-10 h-11 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-l-lg transition"
                            @click="quantity = Math.max(1, quantity - 1)"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                            </svg>
                        </button>
                        <input
                            v-model.number="quantity"
                            type="number"
                            min="1"
                            :max="currentStock || undefined"
                            class="w-14 h-11 text-center border-0 outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <button
                            class="w-10 h-11 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-r-lg transition"
                            @click="quantity = Math.min(currentStock || 999, quantity + 1)"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>

                    <button
                        class="flex-1 min-w-[180px] px-6 py-3 bg-brand-600 text-white font-semibold rounded-lg
                               hover:bg-brand-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition"
                        :disabled="currentStock <= 0"
                        @click="addToCart"
                    >
                        {{ currentStock > 0 ? 'Thêm vào giỏ hàng' : 'Tạm hết hàng' }}
                    </button>
                </div>

                <p v-if="message" class="mt-3 text-sm" :class="messageType === 'error' ? 'text-red-600' : 'text-green-600'">
                    {{ message }}
                </p>

                <!-- Chính sách -->
                <div class="grid grid-cols-2 gap-3 mt-6 text-xs text-gray-600">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Bảo hành 12 tháng
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.5 1.5 0 01-1.5-1.5V15m17.25 0h1.875a1.5 1.5 0 001.5-1.5V15m-17.25 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                        </svg>
                        Giao 24-48 giờ
                    </div>
                </div>
            </div>
        </div>

        <!-- Mô tả & thông số -->
        <div class="grid lg:grid-cols-3 gap-8 mt-14">
            <div class="lg:col-span-2 space-y-8">
                <section v-if="product.description">
                    <h2 class="text-xl font-bold text-gray-900 mb-3">Mô tả sản phẩm</h2>
                    <div class="text-gray-600 leading-relaxed whitespace-pre-line">{{ product.description }}</div>
                </section>

                <section v-if="specifications && Object.keys(specifications).length">
                    <h2 class="text-xl font-bold text-gray-900 mb-3">Thông số kỹ thuật</h2>
                    <table class="w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
                        <tbody>
                            <tr v-for="(value, key) in specifications" :key="key" class="border-b border-gray-100 last:border-0">
                                <th class="bg-gray-50 text-left font-medium text-gray-700 px-4 py-3 w-1/3">{{ key }}</th>
                                <td class="px-4 py-3 text-gray-600">{{ value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <aside class="space-y-4">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2 text-sm">Chính sách bảo hành</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Bảo hành 12 tháng đối với pin, 6 tháng đối với mạch BMS và bộ sạc.
                        Đổi mới trong 7 ngày nếu lỗi nhà sản xuất.
                    </p>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2 text-sm">Chính sách vận chuyển</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Giao toàn quốc 24-48 giờ. Miễn phí vận chuyển đơn từ 500.000đ.
                        Đóng gói chống sốc cho cell pin.
                    </p>
                </div>
            </aside>
        </div>

        <!-- Sản phẩm liên quan -->
        <section v-if="relatedProducts.length" class="mt-14">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Sản phẩm liên quan</h2>
            <ProductGrid :products="relatedProducts" />
        </section>
    </div>

    <div v-else class="max-w-7xl mx-auto px-4 py-20 text-center">
        <p class="text-gray-500">Không tìm thấy sản phẩm.</p>
        <RouterLink :to="{ name: 'products' }" class="inline-block mt-4 text-brand-600 hover:underline">
            ← Quay lại danh sách sản phẩm
        </RouterLink>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import ProductVariantSelector from '../components/ProductVariantSelector.vue';
import ProductGrid from '../components/ProductGrid.vue';
import { useCartStore } from '../stores/cart';
import { useCatalogStore } from '../stores/catalog';
import { formatCurrency } from '../composables/useFormat';

const route = useRoute();
const cart = useCartStore();
const catalog = useCatalogStore();

const product = ref(null);
const relatedProducts = ref([]);
const loading = ref(true);
const quantity = ref(1);
const selectedVariantId = ref(null);
const activeImage = ref(null);
const message = ref('');
const messageType = ref('success');

const selectedVariant = computed(() =>
    product.value?.variants?.find((v) => v.id === selectedVariantId.value) || null,
);

const currentStock = computed(() => {
    if (selectedVariant.value) {
        return selectedVariant.value.available_stock ?? 0;
    }

    return product.value?.total_available_stock ?? 0;
});

const currentPrice = computed(() => {
    if (selectedVariant.value) {
        return selectedVariant.value.effective_price;
    }

    return product.value?.effective_price ?? 0;
});

const isOnSale = computed(() => {
    if (selectedVariant.value) {
        return selectedVariant.value.sale_price !== null
            && selectedVariant.value.sale_price < selectedVariant.value.price;
    }

    return product.value?.is_on_sale;
});

const discountPercent = computed(() => {
    const base = selectedVariant.value?.price ?? product.value?.price ?? 0;
    const sale = selectedVariant.value?.sale_price ?? product.value?.sale_price;

    if (!base || !sale || sale >= base) {
        return 0;
    }

    return Math.round(((base - sale) / base) * 100);
});

const specifications = computed(() => product.value?.specifications || {});

function selectVariant(variant) {
    selectedVariantId.value = variant.id;
    quantity.value = 1;

    if (variant.image) {
        activeImage.value = variant.image;
    }
}

function addToCart() {
    const result = cart.add(product.value, selectedVariant.value, quantity.value);

    message.value = result.message || 'Đã thêm sản phẩm vào giỏ hàng.';
    messageType.value = result.ok ? 'success' : 'error';

    if (result.ok) {
        quantity.value = 1;
    }
}

async function load() {
    loading.value = true;
    product.value = null;

    try {
        const data = await catalog.fetchProduct(route.params.slug);

        product.value = data;
        relatedProducts.value = data.related_products || [];

        activeImage.value = product.value.images?.[0]?.url || product.value.image;

        // Mặc định chọn biến thể đầu tiên còn hàng.
        const firstAvailable = product.value.variants?.find((v) => v.available_stock > 0);

        if (firstAvailable) {
            selectedVariantId.value = firstAvailable.id;
        }
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
}

watch(() => route.params.slug, load);

onMounted(load);
</script>
