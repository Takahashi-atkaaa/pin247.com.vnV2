/**
 * Base path mà ứng dụng Vue được mount.
 *
 * - Ưu tiên giá trị do server render trong thẻ <meta name="app-base">.
 * - Dùng để hỗ trợ cài đặt trong thư mục con (XAMPP: /duanma/).
 */
export const APP_BASE = (() => {
    if (typeof document !== 'undefined') {
        const meta = document.querySelector('meta[name="app-base"]');
        const value = meta?.getAttribute('content');

        if (typeof value === 'string') {
            return value.replace(/\/$/, '');
        }
    }

    return '';
})();

export default APP_BASE;
