<template>
    <div class="flex items-center justify-center px-4 py-16">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-xl font-bold text-gray-900">Đăng nhập</h1>
                <p class="text-sm text-gray-500 mt-1">Đăng nhập tài khoản PIN247 của bạn</p>
            </div>

            <form class="bg-white border border-gray-200 rounded-xl p-6 space-y-4" @submit.prevent="submit">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mật khẩu</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition"
                    />
                </div>

                <p v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">{{ error }}</p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full px-4 py-2.5 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 disabled:bg-gray-300 transition"
                >
                    {{ loading ? 'Đang đăng nhập...' : 'Đăng nhập' }}
                </button>

                <p class="text-sm text-center text-gray-500">
                    Chưa có tài khoản?
                    <RouterLink to="/register" class="text-brand-600 hover:underline font-medium">Đăng ký</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../composables/useFormat';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const loading = ref(false);
const error = ref('');

const form = reactive({ email: '', password: '' });

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await auth.login(form.email, form.password);

        router.push(route.query.redirect || (auth.isAdmin ? '/admin/dashboard' : '/'));
    } catch (err) {
        error.value = errorMessage(err, 'Đăng nhập thất bại.');
    } finally {
        loading.value = false;
    }
}
</script>
