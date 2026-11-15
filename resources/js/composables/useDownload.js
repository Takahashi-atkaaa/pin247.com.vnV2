import api from '../services/api';
import { APP_BASE } from '../config';

/**
 * Tải file từ API có xác thực (Bearer token).
 *
 * Dùng cho các endpoint admin: xuất CSV, tải file mẫu, tải file bảng giá.
 * Mở bằng window.open sẽ KHÔNG gửi header Authorization => phải dùng blob.
 *
 * @param {string} path Đường dẫn tương đối, ví dụ '/admin/price-list/export'
 * @param {string} filename Tên file lưu xuống máy
 */
export async function downloadFile(path, filename) {
    const response = await api.get(path, { responseType: 'blob' });

    const url = window.URL.createObjectURL(new Blob([response.data]));

    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    window.URL.revokeObjectURL(url);
}

/**
 * Đường dẫn tải file công khai (không cần token).
 */
export function publicUrl(path) {
    return `${APP_BASE}/${String(path).replace(/^\//, '')}`;
}
