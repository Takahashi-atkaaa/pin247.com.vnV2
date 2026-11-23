<template>
    <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-4">
        <!-- ============ DANH SÁCH YÊU CẦU ============ -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="p-3 border-b border-gray-200">
                <select
                    v-model="filters.status"
                    class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-brand-500"
                    @change="load"
                >
                    <option value="open">Đang xử lý</option>
                    <option value="resolved">Đã xử lý</option>
                    <option value="">Tất cả</option>
                </select>
            </div>

            <div v-if="loading" class="p-4 space-y-2">
                <div v-for="i in 4" :key="i" class="h-14 bg-gray-100 rounded animate-pulse" />
            </div>

            <ul v-else class="divide-y divide-gray-100 max-h-[55vh] lg:max-h-[70vh] overflow-y-auto">
                <li v-for="r in rows" :key="r.id">
                    <button
                        class="w-full text-left px-3 py-3 hover:bg-gray-50 transition"
                        :class="current?.id === r.id ? 'bg-brand-50 border-l-2 border-brand-600' : ''"
                        @click="openRequest(r)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                {{ r.name || r.email || r.phone || 'Khách vãng lai' }}
                            </p>
                            <span
                                class="px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                :class="r.status === 'open' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                            >
                                {{ r.status === 'open' ? 'Mới' : 'Xong' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 truncate mt-0.5">{{ r.message }}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5 truncate">
                            {{ r.email || r.phone || '—' }} · {{ formatDate(r.created_at, true) }}
                        </p>
                    </button>
                </li>

                <li v-if="!rows.length" class="p-6 text-center text-sm text-gray-500">
                    Chưa có yêu cầu nào.
                </li>
            </ul>
        </div>

        <!-- ============ CHI TIẾT + CHAT ============ -->
        <div v-if="current" class="space-y-4">
            <!-- Thông tin khách + tìm tài khoản -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 truncate">
                            {{ current.name || 'Khách vãng lai' }}
                        </h3>
                        <p class="text-sm text-gray-600 break-all">
                            {{ current.email || '—' }} · {{ current.phone || '—' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1 break-words">
                            Nội dung: {{ current.message }}
                        </p>
                    </div>

                    <button
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-100 transition shrink-0"
                        @click="resolve"
                    >
                        Đóng yêu cầu
                    </button>
                </div>

                <!-- Tìm tài khoản -->
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-sm font-medium text-gray-700 mb-2">Tìm tài khoản khách</p>

                    <div class="flex flex-col sm:flex-row gap-2">
                        <input
                            v-model="lookup.email"
                            type="email"
                            placeholder="Email"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                        >
                        <input
                            v-model="lookup.phone"
                            type="tel"
                            placeholder="Số điện thoại"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                        >
                        <button
                            class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900 transition shrink-0"
                            @click="doLookup"
                        >
                            Tìm
                        </button>
                    </div>

                    <!-- Kết quả tìm kiếm -->
                    <div v-if="candidates.length" class="mt-3 space-y-2">
                        <button
                            v-for="u in candidates"
                            :key="u.id"
                            class="w-full text-left px-3 py-2 border rounded-lg text-sm transition"
                            :class="selectedUser?.id === u.id
                                ? 'border-brand-600 bg-brand-50'
                                : 'border-gray-200 hover:bg-gray-50'"
                            @click="selectedUser = u"
                        >
                            <p class="font-medium text-gray-900 truncate">{{ u.name }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ u.email }} · {{ u.phone || '—' }} · {{ u.total_orders }} đơn
                            </p>
                        </button>
                    </div>

                    <!-- Đặt lại mật khẩu -->
                    <div
                        v-if="selectedUser"
                        class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg"
                    >
                        <p class="text-sm text-amber-900 mb-2 break-all">
                            Đặt lại mật khẩu cho <strong>{{ selectedUser.email }}</strong>
                        </p>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <input
                                v-model="newPassword"
                                type="text"
                                placeholder="Để trống = tự sinh mật khẩu"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                            >
                            <button
                                class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-semibold hover:bg-brand-700 transition shrink-0"
                                @click="doReset"
                            >
                                Đặt lại mật khẩu
                            </button>
                        </div>
                    </div>

                    <!-- Mật khẩu hiện 1 lần -->
                    <div
                        v-if="revealedPassword"
                        class="mt-3 p-3 bg-green-50 border border-green-300 rounded-lg"
                    >
                        <p class="text-xs text-green-800 mb-1">
                            Mật khẩu mới (chỉ hiện 1 lần — hãy gửi cho khách ngay qua Zalo/điện thoại):
                        </p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 px-3 py-2 bg-white border border-green-200 rounded-lg font-mono text-sm text-gray-900 break-all">
                                {{ revealedPassword }}
                            </code>
                            <button
                                class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition shrink-0"
                                @click="copyPassword"
                            >
                                {{ copied ? 'Đã copy' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat -->
            <div class="bg-white border border-gray-200 rounded-xl flex flex-col h-[420px]">
                <div ref="listEl" class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div
                        v-for="m in messages"
                        :key="m.id"
                        class="flex"
                        :class="m.sender_type === 'admin' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[80%] px-3 py-2 rounded-2xl text-sm whitespace-pre-wrap break-words"
                            :class="m.sender_type === 'admin'
                                ? 'bg-brand-600 text-white rounded-br-sm'
                                : 'bg-gray-100 text-gray-800 rounded-bl-sm'"
                        >
                            {{ m.body }}
                        </div>
                    </div>

                    <p v-if="!messages.length" class="text-center text-xs text-gray-400 py-6">
                        Chưa có tin nhắn nào.
                    </p>
                </div>

                <form class="p-3 border-t border-gray-200 flex gap-2" @submit.prevent="sendReply">
                    <input
                        v-model="replyBody"
                        type="text"
                        placeholder="Trả lời khách..."
                        maxlength="2000"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:border-brand-500"
                    >
                    <button
                        type="submit"
                        :disabled="!replyBody.trim()"
                        class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-semibold disabled:bg-gray-300 transition shrink-0"
                    >
                        Gửi
                    </button>
                </form>
            </div>
        </div>

        <div v-else class="bg-white border border-gray-200 rounded-xl p-10 text-center text-gray-500">
            Chọn một yêu cầu để xem chat và đặt lại mật khẩu.
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue';
import api from '../../services/api';
import { errorMessage, formatDate } from '../../composables/useFormat';
import { notify } from '../../composables/useToast';

const rows = ref([]);
const loading = ref(false);
const current = ref(null);
const messages = ref([]);
const candidates = ref([]);
const selectedUser = ref(null);
const revealedPassword = ref('');
const copied = ref(false);
const replyBody = ref('');
const listEl = ref(null);

const filters = reactive({ status: 'open' });
const lookup = reactive({ email: '', phone: '' });
const newPassword = ref('');

async function load() {
    loading.value = true;

    try {
        const { data } = await api.get('/admin/support/requests', {
            params: {
                status: filters.status || undefined,
                per_page: 50,
            },
        });

        rows.value = data.data || [];
    } catch (error) {
        notify(errorMessage(error), 'error');
    } finally {
        loading.value = false;
    }
}

async function openRequest(row) {
    try {
        const { data } = await api.get(`/admin/support/requests/${row.id}`);

        current.value = data.data;
        messages.value = data.data.messages || [];
        candidates.value = [];
        selectedUser.value = null;
        revealedPassword.value = '';

        // Điền sẵn thông tin tìm kiếm từ request.
        lookup.email = row.email || '';
        lookup.phone = row.phone || '';

        await nextTick();

        if (listEl.value) {
            listEl.value.scrollTop = listEl.value.scrollHeight;
        }
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function doLookup() {
    try {
        const { data } = await api.get('/admin/support/lookup', {
            params: {
                email: lookup.email || undefined,
                phone: lookup.phone || undefined,
            },
        });

        candidates.value = data.data || [];
        selectedUser.value = null;

        if (!candidates.value.length) {
            notify('Không tìm thấy tài khoản nào khớp. Kiểm tra lại email/SĐT.', 'success');
        }
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function doReset() {
    if (!selectedUser.value) {
        return;
    }

    try {
        const { data } = await api.post(
            `/admin/support/requests/${current.value.id}/reset-password`,
            {
                user_id: selectedUser.value.id,
                password: newPassword.value || undefined,
            },
        );

        newPassword.value = '';

        // Load lại chat để thấy message hệ thống.
        // PHẢI gán mật khẩu SAU khi reload vì `openRequest()` xoá
        // `revealedPassword` để tránh lộ mật khẩu cũ khi chuyển request khác.
        await openRequest(current.value);

        revealedPassword.value = data.data.password;
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function copyPassword() {
    try {
        await navigator.clipboard.writeText(revealedPassword.value);

        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        notify('Không copy được, hãy chọn và copy thủ công.', 'success');
    }
}

async function sendReply() {
    const body = replyBody.value.trim();

    if (!body) {
        return;
    }

    replyBody.value = '';

    try {
        const { data } = await api.post(`/admin/support/requests/${current.value.id}/reply`, { body });

        messages.value.push(data.data);

        await nextTick();

        if (listEl.value) {
            listEl.value.scrollTop = listEl.value.scrollHeight;
        }
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

async function resolve() {
    try {
        await api.post(`/admin/support/requests/${current.value.id}/resolve`);

        await load();
        await openRequest(current.value);
    } catch (error) {
        notify(errorMessage(error), 'error');
    }
}

onMounted(load);
</script>
