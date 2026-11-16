/**
 * QA modal quản trị trên mobile (390x844).
 *
 * Modal cũ dùng `max-w-3xl` + `p-6` -> tràn ngang và padding quá dày.
 * Sau khi sửa: modal là "bottom sheet" full-width, bo góc trên, padding gọn.
 */
const BASE = 'http://127.0.0.1:8123';

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('input[type="email"]', { timeout: 20000 });
    await page.locator('input[type="email"]').fill('admin@pin247.vn');
    await page.locator('input[type="password"]').fill('admin1234');
    await page.locator('form button[type="submit"]').click();
    await page.waitForURL(/\/admin\//, { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(1500);
}

export default async function run(page, ui) {
    const results = [];

    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);

    // ---- Modal Tạo sản phẩm ----
    await page.goto(`${BASE}/admin/products`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    const opened = await page.evaluate(async () => {
        const btn = Array.from(document.querySelectorAll('button'))
            .find((b) => b.textContent.includes('Thêm sản phẩm'));
        btn?.click();
        await new Promise((r) => setTimeout(r, 700));
        return { clicked: !!btn };
    });

    const modal = await page.evaluate(() => {
        const overlay = document.querySelector('.fixed.inset-0.z-50');
        const panel = overlay?.firstElementChild;
        const r = panel?.getBoundingClientRect();
        const form = panel?.querySelector('form');

        return {
            overlayPresent: !!overlay,
            panelWidth: r ? Math.round(r.width) : 0,
            panelLeft: r ? Math.round(r.left) : null,
            panelRight: r ? Math.round(r.right) : null,
            viewport: window.innerWidth,
            overflowsViewport: r ? r.right > window.innerWidth + 2 : null,
            formPadding: form ? getComputedStyle(form).padding : null,
            // Field nào tràn ngang?
            overflowingFields: Array.from(panel?.querySelectorAll('input, select, textarea') || [])
                .filter((el) => el.getBoundingClientRect().right > window.innerWidth + 2)
                .length,
            gridCols: panel?.querySelector('.grid') ? getComputedStyle(panel.querySelector('.grid')).gridTemplateColumns : null,
        };
    });

    await page.screenshot({ path: '/tmp/qa-mobile-modal-product.png' });
    results.push({ step: 'modal-product', ...opened, ...modal });

    // ---- Modal chi tiết đơn hàng ----
    await page.goto(`${BASE}/admin/orders`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    const orderModal = await page.evaluate(async () => {
        const btn = Array.from(document.querySelectorAll('button'))
            .find((b) => b.textContent.trim() === 'Chi tiết');
        btn?.click();
        await new Promise((r) => setTimeout(r, 1200));

        const overlay = document.querySelector('.fixed.inset-0.z-50');
        const panel = overlay?.firstElementChild;
        const r = panel?.getBoundingClientRect();

        return {
            clicked: !!btn,
            panelWidth: r ? Math.round(r.width) : 0,
            overflowsViewport: r ? r.right > window.innerWidth + 2 : null,
            // Bảng sản phẩm trong modal có tràn không?
            tableOverflow: (() => {
                const t = panel?.querySelector('table');
                if (!t) return null;
                const w = t.parentElement;
                return { tableWidth: Math.round(t.getBoundingClientRect().width), wrapper: Math.round(w.clientWidth) };
            })(),
        };
    });

    await page.screenshot({ path: '/tmp/qa-mobile-modal-order.png' });
    results.push({ step: 'modal-order', ...orderModal });

    // ---- Đóng modal ----
    const closed = await page.evaluate(async () => {
        const overlay = document.querySelector('.fixed.inset-0.z-50');
        const btn = Array.from(overlay?.querySelectorAll('button') || [])
            .find((b) => b.textContent.includes('✕'));
        btn?.click();
        await new Promise((r) => setTimeout(r, 500));
        return { closed: !document.querySelector('.fixed.inset-0.z-50') };
    });
    results.push({ step: 'modal-close', ...closed });

    return results;
}
