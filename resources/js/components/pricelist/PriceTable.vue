<template>
    <div>
        <!-- ================= BẢNG NGANG (desktop) ================= -->
        <div
            v-if="showHorizontal"
            class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white"
        >
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-gray-600 text-xs uppercase">
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">SKU</th>
                        <th class="px-4 py-3 text-left font-semibold">Tên sản phẩm</th>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">
                            SL yêu cầu
                            <span class="block normal-case font-normal text-gray-400 text-[10px]">số lượng tối thiểu</span>
                        </th>
                        <th
                            v-for="(column, index) in tierColumns"
                            :key="`${column.min_qty}-${column.max_qty ?? 'x'}`"
                            class="px-4 py-3 text-right font-semibold whitespace-nowrap"
                        >
                            {{ labels[index] }}
                            <span class="block normal-case font-normal text-brand-600 text-[10px]">
                                SL từ {{ column.min_qty }}
                            </span>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in rows" :key="row.sku" class="hover:bg-brand-50/40 transition">
                        <td class="px-4 py-3 font-mono text-xs text-gray-600 whitespace-nowrap">
                            {{ row.sku }}
                        </td>

                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ row.product_name }}</p>
                            <p v-if="row.variant_name" class="text-xs text-gray-500">{{ row.variant_name }}</p>
                            <p v-if="row.category" class="text-xs text-gray-400">{{ row.category.name }}</p>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <span
                                v-if="row.min_order_qty"
                                class="inline-flex items-center px-2 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-semibold"
                            >
                                từ {{ row.min_order_qty }}
                            </span>
                            <span v-else class="text-gray-400 text-xs">—</span>
                        </td>

                        <td
                            v-for="(column, index) in tierColumns"
                            :key="`${row.sku}-${column.min_qty}`"
                            class="px-4 py-3 text-right whitespace-nowrap"
                        >
                            <span
                                v-if="priceFor(row, column.min_qty) !== null"
                                class="font-semibold text-gray-900"
                            >
                                {{ formatCurrency(priceFor(row, column.min_qty)) }}
                            </span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!rows.length" class="text-center py-12 text-gray-500 text-sm">
                Chưa có dữ liệu bảng giá.
            </p>
        </div>

        <!-- ================= BẢNG DỌC (mobile / tuỳ chọn) ================= -->
        <div
            v-if="!showHorizontal"
            class="space-y-3"
        >
            <div
                v-for="row in rows"
                :key="row.sku"
                class="bg-white border border-gray-200 rounded-xl overflow-hidden"
            >
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="font-semibold text-gray-900 text-sm">{{ row.product_name }}</p>
                    <p v-if="row.variant_name" class="text-xs text-gray-500 mt-0.5">{{ row.variant_name }}</p>
                    <p class="font-mono text-[11px] text-gray-400 mt-1">{{ row.sku }}</p>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-[11px] uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">SL yêu cầu</th>
                            <th class="px-4 py-2 text-left font-semibold">Bậc giá</th>
                            <th class="px-4 py-2 text-right font-semibold">Đơn giá</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="tier in row.tiers" :key="tier.id">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="font-semibold text-brand-700">từ {{ tier.min_qty }}</span>
                                <span v-if="tier.max_qty !== null" class="text-xs text-gray-400">
                                    đến {{ tier.max_qty }}
                                </span>
                                <span v-else class="text-xs text-gray-400">trở lên</span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-600 text-xs">{{ tier.label }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-900 whitespace-nowrap">
                                {{ formatCurrency(tier.unit_price) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p
                v-if="!rows.length"
                class="text-center py-12 text-gray-500 text-sm bg-white border border-gray-200 rounded-xl"
            >
                Chưa có dữ liệu bảng giá.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { formatCurrency } from '../../composables/useFormat';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    tierColumns: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    /**
     * 'horizontal' = bảng ngang, 'vertical' = bảng dọc.
     */
    view: { type: String, default: 'horizontal' },
});

/**
 * Trên mobile luôn dùng bảng dọc (dễ đọc); desktop theo lựa chọn của người dùng.
 * Chỉ render MỘT bảng để không tốn DOM.
 */
const isDesktop = ref(true);

const showHorizontal = computed(() => isDesktop.value && props.view === 'horizontal');

function syncViewport() {
    isDesktop.value = window.matchMedia('(min-width: 768px)').matches;
}

onMounted(() => {
    syncViewport();
    window.addEventListener('resize', syncViewport);
});

onUnmounted(() => {
    window.removeEventListener('resize', syncViewport);
});

/**
 * Lấy giá của SKU tại một mốc bậc (theo min_qty).
 */
function priceFor(row, minQty) {
    const tier = (row.tiers || []).find((t) => t.min_qty === minQty);

    return tier ? tier.unit_price : null;
}
</script>
