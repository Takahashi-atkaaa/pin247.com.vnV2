import { defineStore } from 'pinia';
import api from '../services/api';

export const useCatalogStore = defineStore('catalog', {
    state: () => ({
        categories: [],
        loading: false,
    }),

    actions: {
        async fetchCategories() {
            if (this.categories.length) {
                return this.categories;
            }

            this.loading = true;

            try {
                const { data } = await api.get('/categories');
                this.categories = data.data;
            } finally {
                this.loading = false;
            }

            return this.categories;
        },

        async fetchProducts(params = {}) {
            const { data } = await api.get('/products', { params });

            return data;
        },

        async fetchProduct(slug) {
            const { data } = await api.get(`/products/${slug}`);

            return data.data;
        },

        async fetchStock(skus) {
            if (!skus?.length) {
                return {};
            }

            const { data } = await api.get('/stock', { params: { skus } });

            return data.data;
        },
    },
});
