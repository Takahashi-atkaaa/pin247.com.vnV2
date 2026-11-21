import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { APP_BASE } from '../config';

const api = axios.create({
    // `APP_BASE` giúp API hoạt động khi app được cài trong thư mục con
    // (ví dụ XAMPP: /duanma/).
    baseURL: `${APP_BASE}/api`,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

api.interceptors.request.use((config) => {
    const auth = useAuthStore();

    if (auth.token) {
        config.headers.Authorization = `Bearer ${auth.token}`;
    }

    return config;
});

/**
 * Chuyển về trang đăng nhập, giữ lại đường dẫn hiện tại.
 *
 * Cờ `redirecting` chống vòng lặp: khi đã chuyển hướng rồi mà vẫn
 * gặp 401/403 thì không chuyển lần nữa.
 */
let redirecting = false;

function redirectToLogin() {
    if (redirecting) {
        return;
    }

    redirecting = true;

    const auth = useAuthStore();

    auth.clearSession();

    // Token hết hạn giữa chừng (vd. đang thanh toán) => đưa khách
    // về trang đăng nhập, giữ lại đường dẫn hiện tại để quay lại
    // sau khi đăng nhập.
    const current = window.location.pathname + window.location.search;

    if (!current.includes('/login')) {
        window.location.assign(
            `${APP_BASE}/login?redirect=${encodeURIComponent(current)}`,
        );
    }
}

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;

        // 401 — token không hợp lệ / hết hạn.
        if (status === 401) {
            const auth = useAuthStore();

            if (auth.token) {
                redirectToLogin();
            }
        }

        // 403 — token hợp lệ nhưng không đủ quyền (ví dụ user thường
        // cố truy cập API admin).
        //
        // Xử lý như 401: xoá session + về trang đăng nhập. Nếu không,
        // người dùng sẽ ở lại trang admin rỗng mà không hiểu vì sao.
        if (status === 403) {
            const auth = useAuthStore();

            if (auth.token) {
                redirectToLogin();
            }
        }

        return Promise.reject(error);
    },
);

export default api;
