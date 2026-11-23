/**
 * Toast notification — thay thế `window.alert()` / `window.confirm()`.
 *
 * Dùng:
 *   import { notify, confirmDialog } from '../composables/useToast';
 *   notify('Đã lưu.');
 *   if (await confirmDialog('Xoá sản phẩm này?')) { ... }
 */

import { reactive } from 'vue';

export const toasts = reactive([]);

let seed = 0;
let container = null;

/**
 * Tạo container chứa toast (mount 1 lần).
 */
function ensureContainer() {
    if (container && document.body.contains(container)) {
        return container;
    }

    container = document.createElement('div');
    container.id = 'app-toasts';
    container.setAttribute('aria-live', 'polite');
    container.className = 'fixed z-[100] bottom-4 right-4 left-4 sm:left-auto sm:w-96 flex flex-col gap-2 pointer-events-none';
    document.body.appendChild(container);

    return container;
}

/**
 * Hiện 1 toast.
 *
 * @param {string} message  Nội dung.
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {number} duration  Thời gian hiển thị (ms).
 */
export function notify(message, type = 'success', duration = 3500) {
    if (typeof document === 'undefined') {
        return;
    }

    seed += 1;
    const id = `t${seed}`;

    const styles = {
        success: 'bg-green-600 text-white',
        error: 'bg-red-600 text-white',
        warning: 'bg-amber-500 text-white',
        info: 'bg-gray-800 text-white',
    };

    const icons = {
        success: '✓',
        error: '✕',
        warning: '!',
        info: 'i',
    };

    toasts.push({ id, message: String(message), type });

    ensureContainer();

    const el = document.createElement('div');
    el.className = `pointer-events-auto flex items-start gap-2 px-4 py-3 rounded-xl shadow-lg text-sm font-medium ${styles[type] || styles.info}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<span class="font-bold leading-none mt-0.5">${icons[type] || icons.info}</span>`
        + `<span class="flex-1">${escapeHtml(String(message))}</span>`;

    const close = () => {
        el.remove();
        const index = toasts.findIndex((t) => t.id === id);
        if (index >= 0) {
            toasts.splice(index, 1);
        }
    };

    el.addEventListener('click', close);
    container.appendChild(el);

    if (duration > 0) {
        setTimeout(close, duration);
    }
}

/**
 * Hộp thoại xác nhận (thay `window.confirm`).
 *
 * @param {string} message
 * @param {object} options
 * @returns {Promise<boolean>}
 */
export function confirmDialog(message, options = {}) {
    const {
        title = 'Xác nhận',
        confirmText = 'Đồng ý',
        cancelText = 'Huỷ',
        danger = false,
    } = options;

    if (typeof document === 'undefined') {
        return Promise.resolve(false);
    }

    return new Promise((resolve) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'fixed inset-0 z-[110] flex items-center justify-center p-4';
        wrapper.innerHTML = `
            <div class="absolute inset-0 bg-black/50"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
                <h3 class="font-semibold text-gray-900">${escapeHtml(title)}</h3>
                <p class="text-sm text-gray-600 whitespace-pre-line">${escapeHtml(message)}</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button data-act="cancel"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        ${escapeHtml(cancelText)}
                    </button>
                    <button data-act="ok"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-white ${
                            danger ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-600 hover:bg-brand-700'
                        }">
                        ${escapeHtml(confirmText)}
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(wrapper);

        const finish = (result) => {
            wrapper.remove();
            resolve(result);
        };

        wrapper.querySelector('[data-act="cancel"]').addEventListener('click', () => finish(false));
        wrapper.querySelector('[data-act="ok"]').addEventListener('click', () => finish(true));
        wrapper.querySelector('.bg-black\\/50').addEventListener('click', () => finish(false));

        document.addEventListener('keydown', function onKey(event) {
            if (event.key === 'Escape') {
                document.removeEventListener('keydown', onKey);
                finish(false);
            }
        });
    });
}

/**
 * Escape HTML để tránh XSS khi nội dung chứa ký tự đặc biệt.
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
