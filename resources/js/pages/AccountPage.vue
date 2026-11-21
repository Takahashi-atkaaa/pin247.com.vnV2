<template>
    <div class="max-w-5xl mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500 mb-6">
            <RouterLink to="/" class="hover:text-brand-600">Trang chủ</RouterLink>
            <span class="mx-2">/</span>
            <span class="text-gray-800">Tài khoản của tôi</span>
        </nav>

        <!-- Header -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-brand-600 text-white flex items-center justify-center text-xl font-bold shrink-0">
                    {{ initials }}
                </div>

                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-bold text-gray-900 truncate">{{ profile.name || user.name }}</h1>
                    <p class="text-sm text-gray-500 truncate">{{ user.email }}</p>
                    <p v-if="profile.phone" class="text-sm text-gray-500">{{ profile.phone }}</p>
                </div>

                <button
                    class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition shrink-0"
                    @click="logout"
                >
                    Đăng xuất
                </button>
            </div>

            <!-- Thống kê -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-5 border-t border-gray-100">
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Tổng đơn hàng</p>
                    <p class="text-lg font-bold text-gray-900">{{ stats.total_orders }}</p>
                </div>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Tổng chi tiêu</p>
                    <p class="text-lg font-bold text-brand-600">{{ formatCurrency(stats.total_spent) }}</p>
                </div>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Ngày đăng ký</p>
                    <p class="text-sm font-semibold text-gray-900">{{ formatDate(user.created_at) }}</p>
                </div>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Loại tài khoản</p>
                    <p class="text-sm font-semibold text-gray-900">
                        {{ user.is_admin ? 'Quản trị viên' : 'Khách hàng' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 border-b border-gray-200 mb-6 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition"
                :class="activeTab === tab.key
                    ? 'border-brand-600 text-brand-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- ================= TAB: THÔNG TIN CÁ NHÂN ================= -->
        <div v-show="activeTab === 'profile'" class="space-y-5">
            <form
                class="bg-white border border-gray-200 rounded-xl p-5"
                @submit.prevent="saveProfile"
            >
                <h2 class="font-semibold text-gray-900 mb-1">Thông tin cá nhân</h2>
                <p class="text-sm text-gray-500 mb-5">
                    Thông tin này được dùng làm địa chỉ nhận hàng mặc định.
                </p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Họ tên *</span>
                        <input
                            v-model="form.name"
                            required
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Email *</span>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Số điện thoại *</span>
                        <input
                            v-model="form.phone"
                            type="tel"
                            required
                            placeholder="0901234567"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-gray-700">Địa chỉ nhận hàng</span>
                        <input
                            v-model="form.address"
                            placeholder="Số nhà, tên đường"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Phường / Xã</span>
                        <input
                            v-model="form.ward"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Quận / Huyện</span>
                        <input
                            v-model="form.district"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-gray-700">Tỉnh / Thành phố</span>
                        <input
                            v-model="form.province"
                            placeholder="TP. Hồ Chí Minh"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>
                </div>

                <div class="flex justify-end gap-2 mt-5 pt-5 border-t border-gray-100">
                    <button
                        type="button"
                        class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition"
                        @click="resetForm"
                    >
                        Hoàn tác
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-5 py-2.5 bg-brand-600 text-white rounded-lg text-sm font-semibold hover:bg-brand-700 disabled:bg-gray-300 transition"
                    >
                        {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </button>
                </div>
            </form>

            <!-- Đổi mật khẩu -->
            <form
                class="bg-white border border-gray-200 rounded-xl p-5"
                @submit.prevent="changePassword"
            >
                <h2 class="font-semibold text-gray-900 mb-1">Đổi mật khẩu</h2>
                <p class="text-sm text-gray-500 mb-5">
                    Sau khi đổi mật khẩu, các thiết bị khác sẽ bị đăng xuất.
                </p>

                <div class="grid sm:grid-cols-3 gap-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Mật khẩu hiện tại *</span>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Mật khẩu mới *</span>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Xác nhận mật khẩu *</span>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="mt-1 w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 transition"
                        >
                    </label>
                </div>

                <p class="text-xs text-gray-400 mt-2">Mật khẩu tối thiểu 8 ký tự.</p>

                <div class="flex justify-end mt-5 pt-5 border-t border-gray-100">
                    <button
                        type="submit"
                        :disabled="changingPassword"
                        class="px-5 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900 disabled:bg-gray-300 transition"
                    >
                        {{ changingPassword ? 'Đang đổi...' : 'Đổi mật khẩu' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- ================= TAB: ĐƠN HÀNG ================= -->
        <div v-show="activeTab === 'orders'" class="space-y-4">
            <div v-if="orders.length" class="space-y-3">
                <article
                    v-for="order in orders"
                    :key="order.id"
                    class="bg-white border border-gray-200 rounded-xl overflow-hidden"
                >
                    <header class="px-5 py-3.5 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-mono font-bold text-gray-900 text-sm">{{ order.order_code }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Đặt lúc {{ formatDate(order.created_at, true) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <StatusBadge :status="order.sync_status" :label="order.sync_status_label" />
                            <span class="font-bold text-brand-600">{{ formatCurrency(order.total_amount) }}</span>
                        </div>
                    </header>

                    <div class="px-5 py-4 space-y-3">
                        <!-- Sản phẩm -->
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-11 h-11 rounded bg-gray-50 overflow-hidden shrink-0">
                                <img
                                    v-if="item.image"
                                    :src="item.image"
                                    :alt="item.product_name"
                                    class="w-full h-full object-cover"
                                >
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 truncate">{{ item.product_name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ item.sku }} × {{ item.quantity }}
                                </p>
                            </div>
                            <span class="text-sm font-medium shrink-0">{{ formatCurrency(item.subtotal) }}</span>
                        </div>

                        <!-- Tổng -->
                        <div class="pt-3 border-t border-gray-100 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tạm tính</span>
                                <span>{{ formatCurrency(order.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Phí vận chuyển</span>
                                <span>{{ formatCurrency(order.shipping_fee) }}</span>
                            </div>
                            <div class="flex justify-between font-bold text-gray-900">
                                <span>Tổng cộng</span>
                                <span class="text-brand-600">{{ formatCurrency(order.total_amount) }}</span>
                            </div>
                        </div>

                        <!-- Thanh toán -->
                        <div class="flex flex-wrap gap-4 pt-2 text-xs">
                            <span class="text-gray-500">
                                Thanh toán:
                                <span class="font-medium text-gray-900">{{ order.payment_status_label }}</span>
                                ({{ order.payment_method_label }})
                            </span>
                            <span class="text-gray-500">
                                Giao hàng:
                                <span class="font-medium text-gray-900">
                                    {{ order.tracking_number || order.shipping_status || 'Chưa cập nhật' }}
                                </span>
                            </span>
                        </div>
                    </div>
                </article>

                <!-- Phân trang -->
                <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 pt-2">
                    <button
                        v-for="page in pagination.last_page"
                        :key="page"
                        class="w-9 h-9 rounded-lg text-sm font-medium transition"
                        :class="page === pagination.current_page
                            ? 'bg-brand-600 text-white'
                            : 'bg-white border border-gray-300 text-gray-700 hover:border-brand-400'"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                </div>
            </div>

            <div
                v-else
                class="text-center py-16 bg-white border border-gray-200 rounded-xl"
            >
                <svg class="w-14 h-14 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                </svg>
                <p class="text-gray-600 mb-4">Bạn chưa có đơn hàng nào.</p>
                <RouterLink
                    to="/products"
                    class="inline-block px-5 py-2.5 bg-brand-600 text-white rounded-lg text-sm font-semibold hover:bg-brand-700 transition"
                >
                    Mua sắm ngay
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import StatusBadge from '../components/StatusBadge.vue';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { errorMessage, formatCurrency, formatDate } from '../composables/useFormat';

const router = useRouter();
const auth = useAuthStore();

const tabs = [
    { key: 'profile', label: 'Thông tin cá nhân' },
    { key: 'orders', label: 'Đơn hàng của tôi' },
];

const activeTab = ref('profile');

const user = ref({});
const profile = ref({});
const stats = ref({ total_orders: 0, total_spent: 0 });
const orders = ref([]);
const pagination = ref({});

const saving = ref(false);
const changingPassword = ref(false);

const form = reactive({
    name: '',
    email: '',
    phone: '',
    address: '',
    ward: '',
    district: '',
    province: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

/**
 * Chữ cái đầu của tên để hiển thị avatar.
 */
const initials = computed(() => {
    const name = (profile.value.name || user.value.name || '?').trim();

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
});

function fillForm(data) {
    Object.assign(form, {
        name: data.name || '',
        email: data.email || '',
        phone: data.phone || '',
        address: data.address || '',
        ward: data.ward || '',
        district: data.district || '',
        province: data.province || '',
    });
}

async function loadProfile() {
    try {
        const { data } = await api.get('/account/profile');

        user.value = data.data.user || {};
        profile.value = data.data.profile || {};
        stats.value = data.data.stats || { total_orders: 0, total_spent: 0 };

        fillForm(profile.value);
    } catch (error) {
        console.error(error);
    }
}

async function loadOrders(page = 1) {
    try {
        const { data } = await api.get('/account/orders', {
            params: { page, per_page: 10 },
        });

        orders.value = data.data || [];
        pagination.value = data.meta || {};
    } catch (error) {
        console.error(error);
    }
}

function goToPage(page) {
    loadOrders(page);
}

function resetForm() {
    fillForm(profile.value);
}

async function saveProfile() {
    saving.value = true;

    try {
        const { data } = await api.put('/account/profile', form);

        profile.value = data.data.profile || profile.value;

        // Đồng bộ thông tin user trong store (tên/email có thể đã đổi).
        //
        // Cập nhật TRỰC TIẾP `auth.user` thay vì gọi `setSession()` —
        // `setSession` sẽ reset cờ `userLoaded` khiến mỗi lần lưu phải
        // gọi lại `/api/me`. Dữ liệu ở đây đến từ server nên vẫn an toàn.
        if (data.data.user) {
            auth.user = { ...auth.user, ...data.data.user };
        }

        window.alert(data.message || 'Đã cập nhật thông tin.');
    } catch (error) {
        window.alert(errorMessage(error, 'Cập nhật thông tin thất bại.'));
    } finally {
        saving.value = false;
    }
}

async function changePassword() {
    changingPassword.value = true;

    try {
        const { data } = await api.put('/account/password', passwordForm);

        Object.assign(passwordForm, {
            current_password: '',
            password: '',
            password_confirmation: '',
        });

        window.alert(data.message || 'Đã đổi mật khẩu.');
    } catch (error) {
        window.alert(errorMessage(error, 'Đổi mật khẩu thất bại.'));
    } finally {
        changingPassword.value = false;
    }
}

async function logout() {
    await auth.logout();
    router.push({ name: 'home' });
}

onMounted(async () => {
    await loadProfile();
    await loadOrders();
});
</script>
