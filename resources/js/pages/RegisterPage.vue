<template>
    <div class="flex items-center justify-center px-4 py-16">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-xl font-bold text-gray-900">Tạo tài khoản</h1>
                <p class="text-sm text-gray-500 mt-1">Đăng ký để theo dõi đơn hàng của bạn</p>
            </div>

            <form class="bg-white border border-gray-200 rounded-xl p-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Họ tên</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        maxlength="255"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Số điện thoại</label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        maxlength="30"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mật khẩu</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Xác nhận mật khẩu</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                    />
                </div>

                <p v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">{{ error }}</p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full px-4 py-2.5 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 disabled:bg-gray-300 transition"
                >
                    {{ loading ? 'Đang đăng ký...' : 'Đăng ký' }}
                </button>

                <p class="text-sm text-center text-gray-500">
                    Đã có tài khoản?
                    <RouterLink to="/login" class="text-brand-600 hover:underline font-medium">Đăng nhập</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../composables/useFormat';

const auth = useAuthStore();
const router = useRouter();

const loading = ref(false);
const error = ref('');

const form = reactive({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await auth.register(form);

        router.push('/');
    } catch (err) {
        error.value = errorMessage(err, 'Đăng ký thất bại.');
    } finally {
        loading.value = false;
    }
}
</script>
