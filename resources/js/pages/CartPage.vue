<template>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Giỏ hàng</h1>

        <div v-if="cart.isEmpty" class="text-center py-20">
            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
            <p class="text-gray-500 mb-6">Giỏ hàng của bạn đang trống.</p>
            <RouterLink
                :to="{ name: 'products' }"
                class="inline-block px-6 py-3 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 transition"
            >
                Mua sắm ngay
            </RouterLink>
        </div>

        <div v-else class="grid lg:grid-cols-[1fr_360px] gap-8">
            <!-- Danh sách -->
            <div class="bg-white border border-gray-200 rounded-xl px-5">
                <CartItem
                    v-for="item in cart.items"
                    :key="item.sku"
                    :item="item"
                    @update="cart.updateQuantity"
                    @remove="cart.remove"
                />

                <div class="flex justify-between py-4 border-t border-gray-200">
                    <button
                        class="text-sm text-gray-500 hover:text-red-600 transition"
                        @click="cart.clear()"
                    >
                        Xoá tất cả
                    </button>
                    <RouterLink :to="{ name: 'products' }" class="text-sm text-brand-600 hover:underline">
                        ← Tiếp tục mua hàng
                    </RouterLink>
                </div>
            </div>

            <!-- Tổng tiền -->
            <aside class="lg:sticky lg:top-24 h-fit bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Tổng tiền</h2>

                <div class="space-y-2 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tạm tính ({{ cart.count }} sản phẩm)</span>
                        <span class="font-medium">{{ formatCurrency(cart.total) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Phí vận chuyển</span>
                        <span class="font-medium">{{ formatCurrency(shippingFee) }}</span>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-4 flex justify-between items-baseline">
                    <span class="font-semibold text-gray-900">Tổng cộng</span>
                    <span class="text-2xl font-bold text-brand-600">{{ formatCurrency(grandTotal) }}</span>
                </div>

                <p v-if="stockErrors.length" class="mt-3 text-xs text-red-600 bg-red-50 p-2 rounded">
                    {{ stockErrors.join(' ') }}
                </p>

                <RouterLink
                    :to="{ name: 'checkout' }"
                    class="mt-5 block w-full text-center px-6 py-3 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 transition"
                >
                    Tiến hành thanh toán
                </RouterLink>
            </aside>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import CartItem from '../components/CartItem.vue';
import api from '../services/api';
import { useCartStore } from '../stores/cart';
import { formatCurrency } from '../composables/useFormat';

const cart = useCartStore();

const stockErrors = ref([]);

const shippingFee = computed(() => (cart.total >= 500000 ? 0 : 30000));

const grandTotal = computed(() => cart.total + shippingFee.value);

/** Kiểm tra tồn kho thực tế trước khi thanh toán. */
async function checkStock() {
    const skus = cart.items.map((i) => i.sku);

    if (!skus.length) {
        return;
    }

    try {
        const { data } = await api.get('/stock', { params: { skus } });

        const errors = [];

        cart.items.forEach((item) => {
            const stock = data.data[item.sku];

            if (!stock) {
                errors.push(`SKU ${item.sku} không còn trong hệ thống.`);

                return;
            }

            if (item.quantity > stock.sellable) {
                errors.push(`${item.product_name} chỉ còn ${stock.sellable} sản phẩm.`);
            }
        });

        stockErrors.value = errors;
    } catch {
        // Không chặn giỏ hàng nếu API lỗi; server sẽ kiểm tra lại khi checkout.
    }
}

onMounted(checkStock);
</script>
