import { defineStore } from 'pinia';

const CART_KEY = 'pin247_cart';

export const useCartStore = defineStore('cart', {
    state: () => ({
        items: JSON.parse(localStorage.getItem(CART_KEY) || '[]'),
    }),

    getters: {
        count: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),

        total: (state) =>
            state.items.reduce((sum, item) => sum + item.price * item.quantity, 0),

        isEmpty: (state) => state.items.length === 0,

        /**
         * Danh sách SKU -> số lượng (dùng để gọi API kiểm tra tồn kho).
         */
        quantities: (state) => {
            const map = {};

            state.items.forEach((item) => {
                map[item.sku] = (map[item.sku] || 0) + item.quantity;
            });

            return map;
        },
    },

    actions: {
        persist() {
            localStorage.setItem(CART_KEY, JSON.stringify(this.items));
        },

        add(product, variant = null, quantity = 1) {
            const sku = variant?.sku || product.sku;

            if (!sku) {
                return { ok: false, message: 'Sản phẩm chưa có SKU, không thể thêm vào giỏ.' };
            }

            const available = variant?.available_stock ?? product.total_available_stock ?? 0;
            const existing = this.items.find((i) => i.sku === sku);

            const newQuantity = (existing?.quantity || 0) + quantity;

            if (available > 0 && newQuantity > available) {
                return {
                    ok: false,
                    message: `Chỉ còn ${available} sản phẩm trong kho.`,
                };
            }

            if (existing) {
                existing.quantity = newQuantity;
            } else {
                this.items.push({
                    sku,
                    product_id: product.id,
                    product_name: product.name,
                    variant_name: variant?.name || null,
                    image: variant?.image || product.image,
                    price: variant?.effective_price ?? product.effective_price,
                    quantity,
                    max_stock: available,
                });
            }

            this.persist();

            return { ok: true };
        },

        updateQuantity(sku, quantity) {
            const item = this.items.find((i) => i.sku === sku);

            if (!item) {
                return;
            }

            const newQuantity = Math.max(1, Number(quantity) || 1);

            if (item.max_stock > 0 && newQuantity > item.max_stock) {
                item.quantity = item.max_stock;
            } else {
                item.quantity = newQuantity;
            }

            this.persist();
        },

        remove(sku) {
            this.items = this.items.filter((i) => i.sku !== sku);
            this.persist();
        },

        clear() {
            this.items = [];
            this.persist();
        },
    },
});
