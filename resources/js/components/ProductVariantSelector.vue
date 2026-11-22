<template>
    <div v-if="variants.length" class="space-y-4">
        <!--
            Selector nhiều chiều (clone Haravan).

            Biến thể được nhóm theo từng thuộc tính:
              - "Kiểu đầu": Makita / Bosch / Hitachi
              - "Dung lượng": 2.0Ah / 5.0Ah

            Mỗi nhóm là 1 hàng nút. Giá trị không tồn tại trong tổ hợp đã chọn
            sẽ bị disable (Haravan cũng vậy).
        -->
        <div v-for="group in optionGroups" :key="group.name">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ group.name }}
            </label>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="value in group.values"
                    :key="value.value"
                    type="button"
                    @click="select(group.name, value.value)"
                    :disabled="!isAvailable(group.name, value.value)"
                    class="px-4 py-2 rounded-lg border text-sm font-medium transition
                           disabled:opacity-40 disabled:cursor-not-allowed"
                    :class="isSelected(group.name, value.value)
                        ? 'bg-brand-600 text-white border-brand-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-brand-400 hover:text-brand-600'"
                >
                    {{ value.value }}
                </button>
            </div>
        </div>

        <!-- Tồn kho biến thể đang chọn -->
        <div v-if="selected" class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-gray-500">Tồn kho biến thể này:</span>
            <span
                class="font-semibold"
                :class="selected.available_stock > 0 ? 'text-green-600' : 'text-red-500'"
            >
                {{ selected.available_stock > 0 ? `${selected.available_stock} sản phẩm` : 'Tạm hết hàng' }}
            </span>
            <span class="text-gray-400">· SKU: {{ selected.sku }}</span>
        </div>

        <!-- Đơn vị tính (nếu có) -->
        <div v-if="selectedUnits.length" class="text-sm">
            <label class="block font-medium text-gray-700 mb-2">Đơn vị tính</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="unit in selectedUnits"
                    :key="unit.id"
                    type="button"
                    @click="selectedUnit = unit"
                    class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                    :class="selectedUnit?.id === unit.id
                        ? 'bg-brand-600 text-white border-brand-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-brand-400'"
                >
                    {{ unit.name }}
                    <span class="opacity-75">· {{ formatCurrency(unit.price) }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { formatCurrency } from '../composables/useFormat';

const props = defineProps({
    variants: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
});

const emit = defineEmits(['select']);

const selectedUnit = ref(null);

/**
 * Nhóm các giá trị theo tên thuộc tính.
 *
 * VD: [{ name: 'Kiểu đầu', values: [{ value: 'Makita', variantIds: [1] }] }]
 */
const optionGroups = computed(() => {
    const groups = [];
    const index = new Map();

    props.variants.forEach((variant) => {
        const pairs = [
            [variant.option_1_name, variant.option_1_value],
            [variant.option_2_name, variant.option_2_value],
            [variant.option_3_name, variant.option_3_value],
        ];

        pairs.forEach(([name, value]) => {
            if (!name || !value) {
                return;
            }

            if (!index.has(name)) {
                const group = { name, values: [] };
                index.set(name, group);
                groups.push(group);
            }

            const group = index.get(name);
            let entry = group.values.find((v) => v.value === value);

            if (!entry) {
                entry = { value, variantIds: [] };
                group.values.push(entry);
            }

            entry.variantIds.push(variant.id);
        });
    });

    return groups;
});

const selected = computed(() =>
    props.variants.find((v) => v.id === props.selectedId) || null,
);

/**
 * Đơn vị tính của biến thể đang chọn (chỉ hiện đơn vị được bán).
 */
const selectedUnits = computed(() => {
    const units = selected.value?.units || [];

    return units.filter((unit) => unit.is_sellable !== false);
});

/**
 * Giá trị của thuộc tính `name` đang được chọn ở biến thể hiện tại.
 */
function currentValue(name) {
    if (!selected.value) {
        return null;
    }

    const pairs = [
        [selected.value.option_1_name, selected.value.option_1_value],
        [selected.value.option_2_name, selected.value.option_2_value],
        [selected.value.option_3_name, selected.value.option_3_value],
    ];

    const pair = pairs.find(([pairName]) => pairName === name);

    return pair ? pair[1] : null;
}

function isSelected(name, value) {
    return currentValue(name) === value;
}

/**
 * Giá trị còn khả dụng không?
 *
 * - Nếu đang chọn thuộc tính khác → kiểm tra tổ hợp có tồn tại không.
 * - Nếu là thuộc tính đang chọn → luôn cho phép (giữ nguyên).
 */
function isAvailable(name, value) {
    const variants = props.variants;

    // Tìm biến thể khớp tất cả thuộc tính khác với lựa chọn hiện tại.
    const candidates = variants.filter((variant) => {
        const pairs = [
            [variant.option_1_name, variant.option_1_value],
            [variant.option_2_name, variant.option_2_value],
            [variant.option_3_name, variant.option_3_value],
        ];

        return pairs.every(([pairName, pairValue]) => {
            if (!pairName || pairName === name) {
                return true;
            }

            return currentValue(pairName) === pairValue;
        });
    });

    // Biến thể khớp với giá trị mới.
    const match = candidates.find((variant) => {
        const pairs = [
            [variant.option_1_name, variant.option_1_value],
            [variant.option_2_name, variant.option_2_value],
            [variant.option_3_name, variant.option_3_value],
        ];

        const pair = pairs.find(([pairName]) => pairName === name);

        return pair && pair[1] === value;
    });

    return Boolean(match) && (match.available_stock ?? 0) > 0;
}

/**
 * Chọn giá trị của một thuộc tính → tìm biến thể tương ứng & emit.
 */
function select(name, value) {
    const match = props.variants.find((variant) => {
        const pairs = [
            [variant.option_1_name, variant.option_1_value],
            [variant.option_2_name, variant.option_2_value],
            [variant.option_3_name, variant.option_3_value],
        ];

        const pair = pairs.find(([pairName]) => pairName === name);

        return pair && pair[1] === value;
    });

    if (match) {
        selectedUnit.value = null;
        emit('select', match);
    }
}

// Reset đơn vị tính khi đổi biến thể.
watch(
    () => props.selectedId,
    () => {
        selectedUnit.value = null;
    },
);
</script>
