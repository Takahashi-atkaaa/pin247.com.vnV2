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

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Xử lý 401: xoá token hết hạn và chuyển về trang đăng nhập.
        if (error.response?.status === 401) {
            const auth = useAuthStore();

            if (auth.token) {
                auth.clearSession();

                // Token hết hạn giữa chừng (vd. đang thanh toán) => đưa khách
                // về trang đăng nhập, giữ lại đường dẫn hiện tại để quay lại
                // sau khi đăng nhập.
                const redirect = window.location.pathname + window.location.search;

                if (!redirect.includes('/login')) {
                    window.location.assign(
                        `${APP_BASE}/login?redirect=${encodeURIComponent(redirect)}`,
                    );
                }
            }
        }

        return Promise.reject(error);
    },
);

export default api;
