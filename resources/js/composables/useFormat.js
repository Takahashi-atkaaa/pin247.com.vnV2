/**
 * Định dạng tiền tệ Việt Nam.
 */
export function formatCurrency(value) {
    const number = Number(value || 0);

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(number);
}

/**
 * Định dạng ngày tháng.
 */
export function formatDate(value, withTime = false) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(date);
}

/**
 * Lấy message lỗi từ response axios.
 */
export function errorMessage(error, fallback = 'Đã có lỗi xảy ra. Vui lòng thử lại.') {
    return (
        error?.response?.data?.message ||
        error?.response?.data?.errors?.[Object.keys(error?.response?.data?.errors || {})[0]]?.[0] ||
        error?.message ||
        fallback
    );
}
