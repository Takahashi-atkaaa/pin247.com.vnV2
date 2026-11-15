import { defineStore } from 'pinia';
import api from '../services/api';

const TOKEN_KEY = 'pin247_token';
const USER_KEY = 'pin247_user';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem(TOKEN_KEY) || null,
        user: JSON.parse(localStorage.getItem(USER_KEY) || 'null'),
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        isAdmin: (state) => Boolean(state.user?.is_admin),
    },

    actions: {
        async login(email, password) {
            const { data } = await api.post('/auth/login', { email, password });

            this.setSession(data.data.token, data.data);

            return data.data;
        },

        async register(payload) {
            const { data } = await api.post('/auth/register', payload);

            this.setSession(data.data.token, data.data);

            return data.data;
        },

        async logout() {
            try {
                await api.post('/auth/logout');
            } catch {
                // Bỏ qua lỗi mạng khi đăng xuất.
            } finally {
                this.clearSession();
            }
        },

        setSession(token, user) {
            this.token = token;
            this.user = user;

            localStorage.setItem(TOKEN_KEY, token);
            localStorage.setItem(USER_KEY, JSON.stringify(user));
        },

        clearSession() {
            this.token = null;
            this.user = null;

            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem(USER_KEY);
        },
    },
});
