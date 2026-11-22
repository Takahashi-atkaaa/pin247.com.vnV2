<template>
    <div class="space-y-3">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h4 class="text-sm font-semibold text-gray-900">Đơn vị tính</h4>
                <p class="text-xs text-gray-500 mt-0.5">
                    Đơn vị cơ bản = giá của biến thể. Đơn vị quy đổi có giá tự tính theo tỷ lệ.
                </p>
            </div>
            <button
                type="button"
                class="px-3 py-1.5 text-xs font-medium bg-brand-600 text-white rounded-lg hover:bg-brand-700 shrink-0"
                @click="addUnit"
            >
                + Thêm đơn vị
            </button>
        </div>

        <div v-if="!units.length" class="text-sm text-gray-500 text-center py-4 border border-dashed border-gray-200 rounded-lg">
            Chưa có đơn vị tính.
        </div>

        <div v-else class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Tên đơn vị</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Tỷ lệ</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Giá</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Bán hàng</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(unit, index) in units" :key="unit._key" class="align-top">
                        <td class="px-3 py-2.5">
                            <input
                                v-model="unit.name"
                                type="text"
                                placeholder="VD: viên, lô, hộp"
                                class="w-full px-2 py-1.5 border border-gray-200 rounded text-xs"
                            >
                            <p v-if="unit.is_base" class="text-[11px] text-brand-600 mt-1">Đơn vị cơ bản</p>
                        </td>
                        <td class="px-3 py-2.5">
                            <input
                                v-model.number="unit.conversion_rate"
                                type="number"
                                min="1"
                                step="1"
                                :disabled="unit.is_base || unit.locked"
                                class="w-20 px-2 py-1.5 border border-gray-200 rounded text-xs disabled:bg-gray-100 disabled:text-gray-500"
                            >
                            <p v-if="unit.locked" class="text-[10px] text-gray-400 mt-1">Không sửa được</p>
                        </td>
                        <td class="px-3 py-2.5">
                            <input
                                v-model.number="unit.price"
                                type="number"
                                min="0"
                                step="1000"
                                :placeholder="computedPrice(unit)"
                                class="w-28 px-2 py-1.5 border border-gray-200 rounded text-xs"
                            >
                            <p class="text-[10px] text-gray-400 mt-1">
                                <span v-if="unit.price">Giá riêng</span>
                                <span v-else>Tự tính: {{ formatCurrency(computedPrice(unit)) }}</span>
                            </p>
                        </td>
                        <td class="px-3 py-2.5">
                            <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                <input v-model="unit.is_sellable" type="checkbox" class="rounded">
                                Bán
                            </label>
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            <button
                                type="button"
                                class="px-2 py-1 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50"
                                @click="removeUnit(index)"
                            >
                                Xoá
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500">
            <strong>Lưu ý:</strong> tỷ lệ quy đổi không thể sửa sau khi đã tạo (giống Haravan) —
            nếu sai hãy xoá và tạo lại đơn vị đó.
        </p>
    </div>
</template>

<script setup>
import { formatCurrency } from '../../composables/useFormat';

const props = defineProps({
    units: { type: Array, default: () => [] },
    basePrice: { type: Number, default: 0 },
});

const emit = defineEmits(['change']);

let keySeed = 0;

function nextKey() {
    keySeed += 1;
    return `u${keySeed}`;
}

function addUnit() {
    props.units.push({
        _key: nextKey(),
        id: null,
        name: '',
        conversion_rate: props.units.length === 0 ? 1 : 2,
        is_base: props.units.length === 0,
        price: null,
        is_sellable: true,
        barcode: null,
        locked: false,
    });

    emit('change');
}

function removeUnit(index) {
    props.units.splice(index, 1);

    // Đơn vị đầu tiên luôn là đơn vị cơ bản.
    if (props.units.length && !props.units.some((u) => u.is_base)) {
        props.units[0].is_base = true;
        props.units[0].conversion_rate = 1;
    }

    emit('change');
}

/**
 * Giá tự tính: giá cơ bản × tỷ lệ quy đổi.
 */
function computedPrice(unit) {
    return Math.round((props.basePrice || 0) * (unit.conversion_rate || 1));
}
</script>
