<template>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="font-semibold text-gray-900 text-sm">{{ title }}</h3>
        </div>

        <!-- DESKTOP (>= md): bảng 9 cột, cuộn ngang -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-gray-600 text-xs uppercase bg-white">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">SKU</th>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Sản phẩm</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Khả dụng</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Giữ</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Thực tế</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">An toàn</th>
                        <th class="px-4 py-3 text-right font-semibold whitespace-nowrap">Bán được</th>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Trạng thái</th>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Đồng bộ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">
                            <button class="underline hover:text-brand-600" @click="$emit('history', row)">
                                {{ row.sku }}
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-gray-900">{{ row.product?.name || row.name || '—' }}</p>
                            <p v-if="row.variant?.name" class="text-xs text-gray-500">{{ row.variant.name }}</p>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ row.available_stock }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ row.reserved_stock }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ row.on_hand_stock }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ row.safety_stock }}</td>
                        <td
                            class="px-4 py-3 text-right font-bold"
                            :class="row.sellable_quantity > 0 ? 'text-green-600' : 'text-red-500'"
                        >
                            {{ row.sellable_quantity }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="row.is_in_stock ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                            >
                                {{ row.is_in_stock ? 'Còn hàng' : 'Hết hàng' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="row.sync_state"
                                :label="row.sync_state_label"
                            />
                            <p v-if="row.last_synced_at" class="text-xs text-gray-400 mt-1">
                                {{ formatDate(row.last_synced_at, true) }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- MOBILE (< md): dạng card xếp dọc -->
        <div class="md:hidden divide-y divide-gray-100">
            <div v-for="row in rows" :key="row.id" class="p-4 space-y-2.5">
                <div>
                    <button
                        class="font-mono text-xs text-brand-600 underline"
                        @click="$emit('history', row)"
                    >
                        {{ row.sku }}
                    </button>
                    <p class="font-medium text-gray-900 text-sm mt-0.5">
                        {{ row.product?.name || row.name || '—' }}
                    </p>
                    <p v-if="row.variant?.name" class="text-xs text-gray-500">{{ row.variant.name }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">Khả dụng</dt>
                        <dd class="font-semibold text-gray-900">{{ row.available_stock }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Bán được</dt>
                        <dd
                            class="font-bold"
                            :class="row.sellable_quantity > 0 ? 'text-green-600' : 'text-red-500'"
                        >
                            {{ row.sellable_quantity }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Giữ</dt>
                        <dd class="text-gray-700">{{ row.reserved_stock }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Thực tế</dt>
                        <dd class="text-gray-700">{{ row.on_hand_stock }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Tồn an toàn</dt>
                        <dd class="text-gray-700">{{ row.safety_stock }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Trạng thái</dt>
                        <dd class="mt-0.5">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium inline-block"
                                :class="row.is_in_stock ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                            >
                                {{ row.is_in_stock ? 'Còn hàng' : 'Hết hàng' }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="pt-2 border-t border-gray-100 flex items-center justify-between gap-2">
                    <StatusBadge :status="row.sync_state" :label="row.sync_state_label" />
                    <span v-if="row.last_synced_at" class="text-xs text-gray-400">
                        {{ formatDate(row.last_synced_at, true) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import StatusBadge from '../StatusBadge.vue';
import { formatDate } from '../../composables/useFormat';

defineProps({
    rows: { type: Array, default: () => [] },
    title: { type: String, default: 'Tồn kho' },
});

defineEmits(['history']);
</script>
