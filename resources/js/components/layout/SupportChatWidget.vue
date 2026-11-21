<template>
    <!--
        Nút nổi + khung chat hỗ trợ cho KHÁCH (chưa đăng nhập).
        Dùng để khách yêu cầu admin đặt lại mật khẩu mà không cần
        dịch vụ email/SMS (InfinityFree chặn gửi mail).
    -->
    <div>
        <!-- Nút nổi góc phải -->
        <button
            class="fixed bottom-[72px] md:bottom-6 right-4 z-40 w-14 h-14 rounded-full bg-brand-600 text-white shadow-lg flex items-center justify-center hover:bg-brand-700 transition"
            @click="toggle"
            :aria-label="open ? 'Đóng chat hỗ trợ' : 'Mở chat hỗ trợ'"
        >
            <svg v-if="!open" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
            </svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Overlay (mobile) -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 bg-black/40 z-40 md:hidden"
                @click.self="open = false"
            />
        </Transition>

        <!-- Khung chat -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-4 md:translate-y-0 md:translate-x-4"
            leave-active-class="transition-all duration-150"
            leave-to-class="opacity-0 translate-y-4 md:translate-y-0 md:translate-x-4"
        >
            <div
                v-if="open"
                class="fixed z-50 bg-white shadow-2xl flex flex-col
                       inset-x-0 bottom-0 top-16 rounded-t-2xl
                       md:inset-auto md:bottom-24 md:right-4 md:top-auto md:w-[380px] md:h-[520px] md:rounded-2xl"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 bg-brand-600 text-white rounded-t-2xl shrink-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-sm">Hỗ trợ PIN247</p>
                        <p class="text-[11px] opacity-90 truncate">
                            {{ token ? 'Đang kết nối với admin...' : 'Quên mật khẩu? Chat với admin' }}
                        </p>
                    </div>
                    <button
                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg hover:bg-brand-700 transition"
                        @click="open = false"
                        aria-label="Đóng chat"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Bước 1: tạo yêu cầu -->
                <form v-if="!token" class="flex-1 overflow-y-auto p-4 space-y-3" @submit.prevent="createRequest">
                    <p class="text-sm text-gray-600">
                        Nhập thông tin tài khoản, admin sẽ tìm và đặt lại mật khẩu cho bạn.
                    </p>

                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="Họ tên"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                    >
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="Email đăng ký"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                    >
                    <input
                        v-model="form.phone"
                        type="tel"
                        placeholder="Số điện thoại"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                    >
                    <textarea
                        v-model="form.message"
                        rows="3"
                        required
                        maxlength="2000"
                        placeholder="Mô tả vấn đề (vd: quên mật khẩu, không nhớ email)"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500 resize-none"
                    />

                    <p v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">{{ error }}</p>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full px-4 py-2.5 bg-brand-600 text-white font-semibold rounded-lg hover:bg-brand-700 disabled:bg-gray-300 transition text-sm"
                    >
                        {{ loading ? 'Đang gửi...' : 'Gửi yêu cầu' }}
                    </button>

                    <p class="text-xs text-gray-400 text-center">
                        Khung chat này chỉ dành cho bạn — admin sẽ phản hồi sớm nhất có thể.
                    </p>
                </form>

                <!-- Bước 2: chat -->
                <div v-else class="flex-1 flex flex-col min-h-0">
                    <div ref="listEl" class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div
                            v-for="m in messages"
                            :key="m.id"
                            class="flex"
                            :class="m.sender_type === 'customer' ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                class="max-w-[80%] px-3 py-2 rounded-2xl text-sm whitespace-pre-wrap break-words"
                                :class="m.sender_type === 'customer'
                                    ? 'bg-brand-600 text-white rounded-br-sm'
                                    : 'bg-gray-100 text-gray-800 rounded-bl-sm'"
                            >
                                {{ m.body }}
                            </div>
                        </div>

                        <p v-if="!messages.length" class="text-center text-xs text-gray-400 py-6">
                            Đã gửi yêu cầu. Admin sẽ phản hồi trong vài phút.
                        </p>
                    </div>

                    <form class="p-3 border-t border-gray-200 flex gap-2 shrink-0" @submit.prevent="sendReply">
                        <input
                            v-model="replyBody"
                            type="text"
                            placeholder="Nhắn thêm cho admin..."
                            maxlength="2000"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                        >
                        <button
                            type="submit"
                            :disabled="!replyBody.trim()"
                            class="px-3 py-2 bg-brand-600 text-white rounded-lg text-sm font-semibold disabled:bg-gray-300 transition shrink-0"
                        >
                            Gửi
                        </button>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import api from '../../services/api';
import { errorMessage } from '../../composables/useFormat';

/** Key lưu token chat trong localStorage (khách tải lại trang vẫn còn chat). */
const STORAGE_KEY = 'pin247_support_token';

const open = ref(false);
const loading = ref(false);
const error = ref('');
const token = ref(localStorage.getItem(STORAGE_KEY) || '');
const messages = ref([]);
const replyBody = ref('');
const listEl = ref(null);

let timer = null;

const form = reactive({ name: '', email: '', phone: '', message: '' });

function toggle() {
    open.value = !open.value;

    if (open.value && token.value) {
        startPolling();
    } else {
        stopPolling();
    }
}

async function createRequest() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.post('/support/requests', {
            name: form.name || null,
            email: form.email || null,
            phone: form.phone || null,
            message: form.message,
        });

        token.value = data.data.public_token;
        localStorage.setItem(STORAGE_KEY, token.value);

        await loadMessages();
        startPolling();
    } catch (err) {
        error.value = errorMessage(err, 'Không gửi được yêu cầu.');
    } finally {
        loading.value = false;
    }
}

async function loadMessages() {
    if (!token.value) {
        return;
    }

    try {
        const { data } = await api.get(`/support/messages/${token.value}`);

        messages.value = data.data.messages || [];

        await nextTick();

        if (listEl.value) {
            listEl.value.scrollTop = listEl.value.scrollHeight;
        }
    } catch {
        // Im lặng — vòng poll sau sẽ thử lại.
    }
}

async function sendReply() {
    const body = replyBody.value.trim();

    if (!body || !token.value) {
        return;
    }

    replyBody.value = '';

    try {
        const { data } = await api.post(`/support/messages/${token.value}`, { body });

        messages.value.push(data.data);
    } catch (err) {
        error.value = errorMessage(err, 'Không gửi được tin nhắn.');
    }
}

function startPolling() {
    stopPolling();

    timer = setInterval(() => {
        // Không poll khi tab ẩn — shared hosting giới hạn request.
        if (document.visibilityState === 'visible' && open.value) {
            loadMessages();
        }
    }, 5000);
}

function stopPolling() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

onMounted(() => {
    if (token.value) {
        loadMessages();
    }
});

onBeforeUnmount(stopPolling);
</script>
