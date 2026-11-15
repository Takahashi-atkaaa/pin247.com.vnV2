<template>
    <aside class="bg-white border border-gray-200 rounded-xl p-5 space-y-6">
        <!-- Danh mục -->
        <div v-if="categories.length">
            <h3 class="font-semibold text-gray-900 mb-3">Danh mục</h3>
            <ul class="space-y-2 text-sm">
                <li>
                    <button
                        class="w-full text-left hover:text-brand-600 transition"
                        :class="{ 'text-brand-600 font-semibold': !filters.category }"
                        @click="$emit('update:category', '')"
                    >
                        Tất cả sản phẩm
                    </button>
                </li>
                <li v-for="category in categories" :key="category.id">
                    <button
                        class="w-full text-left hover:text-brand-600 transition"
                        :class="{ 'text-brand-600 font-semibold': filters.category === category.slug }"
                        @click="$emit('update:category', category.slug)"
                    >
                        {{ category.name }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- Giá -->
        <div>
            <h3 class="font-semibold text-gray-900 mb-3">Khoảng giá</h3>
            <div class="flex items-center gap-2">
                <input
                    type="number"
                    min="0"
                    placeholder="Từ"
                    :value="filters.min_price"
                    @input="$emit('update:minPrice', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg outline-none focus:border-brand-500"
                />
                <span class="text-gray-400">-</span>
                <input
                    type="number"
                    min="0"
                    placeholder="Đến"
                    :value="filters.max_price"
                    @input="$emit('update:maxPrice', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg outline-none focus:border-brand-500"
                />
            </div>
        </div>

        <!-- Thương hiệu -->
        <div v-if="brands.length">
            <h3 class="font-semibold text-gray-900 mb-3">Thương hiệu</h3>
            <ul class="space-y-2 text-sm max-h-48 overflow-y-auto">
                <li v-for="brand in brands" :key="brand">
                    <label class="flex items-center gap-2 cursor-pointer hover:text-brand-600 transition">
                        <input
                            type="radio"
                            name="brand"
                            :checked="filters.brand === brand"
                            class="accent-brand-600"
                            @change="$emit('update:brand', brand)"
                        />
                        {{ brand }}
                    </label>
                </li>
            </ul>
        </div>

        <!-- Tồn kho -->
        <div>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input
                    type="checkbox"
                    :checked="filters.in_stock"
                    class="accent-brand-600"
                    @change="$emit('update:inStock', $event.target.checked)"
                />
                Chỉ hiện sản phẩm còn hàng
            </label>
        </div>

        <button
            class="w-full py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            @click="$emit('reset')"
        >
            Xoá bộ lọc
        </button>
    </aside>
</template>

<script setup>
defineProps({
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

defineEmits([
    'update:category',
    'update:minPrice',
    'update:maxPrice',
    'update:brand',
    'update:inStock',
    'reset',
]);
</script>
