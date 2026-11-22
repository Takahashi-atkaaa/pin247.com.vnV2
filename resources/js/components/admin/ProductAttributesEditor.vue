<template>
    <div class="space-y-4">
        <!-- ============ THUỘC TÍNH ============ -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Thuộc tính sản phẩm</h4>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Tối đa 3 thuộc tính (như Haravan). Biến thể được tự sinh từ tích hợp các giá trị.
                    </p>
                </div>
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-40"
                    :disabled="attributes.length >= maxAttributes"
                    @click="addAttribute"
                >
                    + Thêm thuộc tính
                </button>
            </div>

            <div class="p-4 space-y-3">
                <p v-if="!attributes.length" class="text-sm text-gray-500 text-center py-3">
                    Chưa có thuộc tính. Thêm thuộc tính để hệ thống tự sinh biến thể.
                </p>

                <div
                    v-for="(attribute, index) in attributes"
                    :key="attribute._key"
                    class="border border-gray-200 rounded-lg p-3 bg-white"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <input
                                v-model="attribute.name"
                                type="text"
                                placeholder="Tên thuộc tính (VD: Kiểu đầu)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium"
                            >
                        </div>
                        <button
                            type="button"
                            class="px-2.5 py-2 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50 shrink-0"
                            title="Xoá thuộc tính"
                            @click="removeAttribute(index)"
                        >
                            Xoá
                        </button>
                    </div>

                    <!-- Giá trị -->
                    <div class="mt-3">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="(value, valueIndex) in attribute.values"
                                :key="value._key"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-brand-50 text-brand-700 border border-brand-200 rounded-full text-xs font-medium"
                            >
                                {{ value.value }}
                                <button
                                    type="button"
                                    class="text-brand-500 hover:text-brand-700"
                                    @click="removeValue(index, valueIndex)"
                                >
                                    ✕
                                </button>
                            </span>

                            <input
                                v-if="addingValueFor === index"
                                ref="valueInput"
                                v-model="newValue"
                                type="text"
                                placeholder="Nhập giá trị rồi Enter"
                                class="px-2.5 py-1 border border-brand-300 rounded-full text-xs outline-none focus:border-brand-500"
                                @keydown.enter.prevent="commitValue(index)"
                                @keydown.esc="cancelValue"
                                @blur="commitValue(index)"
                            >
                            <button
                                v-else
                                type="button"
                                class="px-2.5 py-1 border border-dashed border-gray-300 text-gray-500 rounded-full text-xs hover:border-brand-400 hover:text-brand-600"
                                :disabled="attribute.values.length >= maxValues"
                                @click="startAddingValue(index)"
                            >
                                + Giá trị
                            </button>
                        </div>
                        <p v-if="attribute.values.length >= maxValues" class="text-xs text-amber-600 mt-1.5">
                            Đã đạt giới hạn {{ maxValues }} giá trị cho một thuộc tính.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ BIẾN THỂ (tự sinh) ============ -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">
                        Biến thể
                        <span class="ml-1 text-xs font-normal text-gray-500">({{ generatedVariants.length }})</span>
                    </h4>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Tự sinh từ thuộc tính. Có thể chỉnh giá, SKU, tên và trạng thái.
                    </p>
                </div>
            </div>

            <div v-if="generatedVariants.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Biến thể</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">SKU</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Giá bán</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Giá vốn</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Trạng thái</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Đơn vị</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="variant in generatedVariants" :key="variant._key" class="hover:bg-gray-50/50">
                            <td class="px-3 py-2.5">
                                <input
                                    v-model="variant.name"
                                    type="text"
                                    class="w-full px-2 py-1.5 border border-gray-200 rounded text-xs"
                                >
                                <p class="text-[11px] text-gray-400 mt-1">{{ variant.optionLabel }}</p>
                            </td>
                            <td class="px-3 py-2.5">
                                <input
                                    v-model="variant.sku"
                                    type="text"
                                    class="w-full px-2 py-1.5 border border-gray-200 rounded font-mono text-xs"
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <input
                                    v-model.number="variant.price"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    class="w-28 px-2 py-1.5 border border-gray-200 rounded text-xs"
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <input
                                    v-model.number="variant.cost_price"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    class="w-28 px-2 py-1.5 border border-gray-200 rounded text-xs"
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                    <input v-model="variant.is_active" type="checkbox" class="rounded">
                                    Bán
                                </label>
                            </td>
                            <td class="px-3 py-2.5">
                                <button
                                    type="button"
                                    class="px-2 py-1 text-[11px] border border-gray-300 rounded hover:border-brand-400 hover:text-brand-600 whitespace-nowrap"
                                    @click="$emit('open-units', variant)"
                                >
                                    {{ variant.units?.length || 0 }} đơn vị
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="text-sm text-gray-500 text-center py-6 px-4">
                Chưa có biến thể. Thêm thuộc tính và giá trị để hệ thống tự sinh.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    attributes: { type: Array, default: () => [] },
    variants: { type: Array, default: () => [] },
    productPrice: { type: Number, default: 0 },
});

const emit = defineEmits(['open-units']);

// Giới hạn giống Haravan.
const maxAttributes = 3;
const maxValues = 200;

const addingValueFor = ref(null);
const newValue = ref('');
const valueInput = ref(null);

let keySeed = 0;

function nextKey() {
    keySeed += 1;
    return `k${keySeed}`;
}

function addAttribute() {
    if (props.attributes.length >= maxAttributes) {
        return;
    }

    props.attributes.push({
        _key: nextKey(),
        name: '',
        values: [],
    });
}

function removeAttribute(index) {
    props.attributes.splice(index, 1);
}

async function startAddingValue(index) {
    addingValueFor.value = index;
    newValue.value = '';

    await nextTick();

    const el = Array.isArray(valueInput.value) ? valueInput.value[0] : valueInput.value;
    el?.focus();
}

function commitValue(index) {
    if (addingValueFor.value !== index) {
        return;
    }

    const value = newValue.value.trim();

    if (value !== '') {
        const attribute = props.attributes[index];
        const exists = attribute.values.some((v) => v.value.toLowerCase() === value.toLowerCase());

        if (!exists && attribute.values.length < maxValues) {
            attribute.values.push({ _key: nextKey(), value });
        }
    }

    cancelValue();
}

function cancelValue() {
    addingValueFor.value = null;
    newValue.value = '';
}

/**
 * Tích hợp các giá trị thuộc tính → danh sách biến thể (cartesian product).
 *
 * Giữ nguyên dữ liệu đã nhập (giá, SKU, tên) của biến thể cũ bằng cách
 * đối chiếu theo chữ ký option value.
 */
const generatedVariants = computed(() => {
    const validAttributes = props.attributes.filter((a) => a.name.trim() && a.values.length);

    if (!validAttributes.length) {
        return [];
    }

    let combinations = [[]];

    validAttributes.forEach((attribute) => {
        const next = [];

        combinations.forEach((combination) => {
            attribute.values.forEach((value) => {
                next.push([...combination, value.value]);
            });
        });

        combinations = next;
    });

    // Index dữ liệu cũ theo chữ ký option.
    const existingBySignature = {};

    props.variants.forEach((variant) => {
        const signature = [
            variant.option_1_value || '',
            variant.option_2_value || '',
            variant.option_3_value || '',
        ].join('|');

        existingBySignature[signature] = variant;
    });

    return combinations.map((values, index) => {
        const signature = [
            values[0] || '',
            values[1] || '',
            values[2] || '',
        ].join('|');

        const existing = existingBySignature[signature];
        const optionNames = validAttributes.map((a) => a.name.trim());
        const optionValues = values;

        return {
            _key: existing?._key || `gen${index}`,
            id: existing?.id || null,
            sku: existing?.sku || '',
            name: existing?.name || buildName(optionNames, optionValues),
            option_1_name: optionNames[0] || null,
            option_1_value: optionValues[0] || null,
            option_2_name: optionNames[1] || null,
            option_2_value: optionValues[1] || null,
            option_3_name: optionNames[2] || null,
            option_3_value: optionValues[2] || null,
            price: existing?.price ?? props.productPrice ?? 0,
            sale_price: existing?.sale_price ?? null,
            cost_price: existing?.cost_price ?? null,
            image: existing?.image ?? null,
            is_active: existing?.is_active ?? true,
            units: existing?.units || [],
            optionLabel: optionNames
                .map((name, i) => (optionValues[i] ? `${name}: ${optionValues[i]}` : null))
                .filter(Boolean)
                .join(' · '),
        };
    });
});

function buildName(names, values) {
    const parts = names
        .map((name, i) => (values[i] ? `${name} ${values[i]}` : null))
        .filter(Boolean);

    return parts.length ? parts.join(' — ') : 'Biến thể';
}
</script>
