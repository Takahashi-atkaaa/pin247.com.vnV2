import { defineStore } from 'pinia';
import api from '../services/api';

const TOKEN_KEY = 'pin247_token';

/**
 * Store phiên đăng nhập.
 *
 * NGUYÊN TẮC BẢO MẬT
 * ------------------
 * localStorage chỉ chứa TOKEN. Thông tin người dùng (đặc biệt là
 * `is_admin`) KHÔNG được lưu và được xem là không đáng tin — ai cũng có
 * thể sửa localStorage trong DevTools.
 *
 * `is_admin` chỉ được lấy từ server qua `GET /api/me` (token được
 * xác thực bằng chữ ký Sanctum). Router guard vì vậy luôn hỏi server
 * trước khi cho vào khu vực quản trị.
 *
 * Lý do không cache `is_admin`:
 *  - Cache lại chính là tạo lại lỗ hổng (sửa localStorage → thành admin).
 *  - Ngoài ra còn gây lỗi khi admin bị hạ quyền mà phiên trình duyệt
 *    vẫn còn cache quyền cũ.
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem(TOKEN_KEY) || null,

        // Thông tin user do server trả về (null = chưa xác thực).
        user: null,

        // Đã gọi /api/me trong phiên này chưa — tránh gọi lặp.
        userLoaded: false,
    }),

    getters: {
        /**
         * Đã có token. CHƯA đảm bảo token hợp lệ — cần `fetchUser()`
         * để xác thực thật sự với server.
         */
        isAuthenticated: (state) => Boolean(state.token),

        /**
         * Có phải admin hay không — theo dữ liệu server đã xác thực.
         * `null` nghĩa là chưa xác thực (không nên dùng để quyết định).
         */
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

        /**
         * Lấy thông tin user từ server (xác thực token).
         *
         * Đây là nguồn sự thật duy nhất cho `is_admin`. Token giả/rỗng/hết
         * hạn => server trả 401 => ném lỗi, session được xoá.
         *
         * @returns {Promise<object|null>} user từ server, hoặc null nếu
         *                               token không hợp lệ.
         */
        async fetchUser(force = false) {
            if (!this.token) {
                this.user = null;
                this.userLoaded = true;

                return null;
            }

            // Đã xác thực rồi thì không gọi lại (trừ khi force=true).
            if (this.userLoaded && !force) {
                return this.user;
            }

            try {
                const { data } = await api.get('/me');

                this.user = data.data;
                this.userLoaded = true;

                return this.user;
            } catch {
                // Token hợp lệ? Không => coi như chưa đăng nhập.
                this.user = null;
                this.userLoaded = true;

                return null;
            }
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

            // `user` đến từ response đăng nhập (server đã xác thực) nên
            // dùng được — nhưng vẫn gọi lại /api/me khi cần đảm bảo.
            this.user = user || null;
            this.userLoaded = false;

            localStorage.setItem(TOKEN_KEY, token);
        },

        clearSession() {
            this.token = null;
            this.user = null;
            this.userLoaded = false;

            localStorage.removeItem(TOKEN_KEY);
        },
    },
});
