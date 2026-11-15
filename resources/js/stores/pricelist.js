import { defineStore } from 'pinia';
import api from '../services/api';
import { APP_BASE } from '../config';

export const usePriceListStore = defineStore('pricelist', {
    state: () => ({
        rows: [],
        tierColumns: [],
        documents: [],
        meta: {},
        loading: false,
    }),

    getters: {
        /**
         * Chuỗi hiển thị của một bậc giá, ví dụ "1", "10-49", "200+".
         */
        columnLabels: (state) =>
            state.tierColumns.map((column) => {
                if (column.max_qty === null) {
                    return `${column.min_qty}+`;
                }

                if (column.min_qty === column.max_qty) {
                    return `${column.min_qty}`;
                }

                return `${column.min_qty}-${column.max_qty}`;
            }),

        /**
         * Tổng số SKU trong bảng giá.
         */
        total: (state) => state.meta?.total ?? state.rows.length,
    },

    actions: {
        /**
         * Ghép đường dẫn tải file với APP_BASE (app nằm trong thư mục con XAMPP).
         */
        downloadUrl(document) {
            if (document.download_path) {
                return `${APP_BASE}/${document.download_path}`;
            }

            return document.download_url || '';
        },

        async fetchPriceList(params = {}) {
            this.loading = true;

            try {
                const { data } = await api.get('/price-list', { params });

                this.rows = data.data || [];
                this.tierColumns = data.tier_columns || [];
                this.meta = data.meta || {};

                return data;
            } finally {
                this.loading = false;
            }
        },

        async fetchDocuments() {
            const { data } = await api.get('/price-list/documents');

            this.documents = data.data || [];

            return this.documents;
        },

        /**
         * Tìm giá của một bậc theo mốc min_qty.
         */
        priceFor(row, minQty) {
            const tier = (row.tiers || []).find((t) => t.min_qty === minQty);

            return tier ? tier.unit_price : null;
        },
    },
});
