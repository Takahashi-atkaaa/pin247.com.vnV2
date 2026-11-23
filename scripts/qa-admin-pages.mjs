/**
 * QA các TRANG RIÊNG quản trị trên mobile (390x844).
 *
 * Trước đây thêm/sửa/chi tiết nằm trong popup (`.fixed.inset-0.z-50`).
 * Nay mỗi luồng là 1 route riêng — kiểm tra:
 *   - route điều hướng đúng
 *   - KHÔNG còn overlay popup nào
 *   - nội dung trang không tràn ngang viewport
 *   - bảng bên trong được bọc overflow-x-auto
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

/**
 * Đo trạng thái layout của trang hiện tại.
 */
async function inspect(page) {
    return page.evaluate(() => {
        const overlay = document.querySelector('.fixed.inset-0.z-50');
        const doc = document.documentElement;

        const overflowingFields = Array.from(
            document.querySelectorAll('main input, main select, main textarea, main table'),
        ).filter((el) => el.getBoundingClientRect().right > window.innerWidth + 2).length;

        return {
            overlayPresent: !!overlay,
            url: window.location.pathname,
            viewport: window.innerWidth,
            docScrollWidth: doc.scrollWidth,
            horizontalScroll: doc.scrollWidth > window.innerWidth + 2,
            overflowingFields,
            hasHeading: !!document.querySelector('main h1'),
            heading: document.querySelector('main h1')?.textContent?.trim() || null,
            tableWrapped: Array.from(document.querySelectorAll('main table')).every(
                (t) => getComputedStyle(t.parentElement).overflowX === 'auto'
                    || getComputedStyle(t.parentElement).overflowX === 'scroll',
            ),
        };
    });
}

export default async function run(page, ui) {
    const results = [];

    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);

    // ---- Danh sách sản phẩm ----
    await page.goto(`${BASE}/admin/products`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    results.push({ step: 'products-list', ...(await inspect(page)) });

    // ---- Bấm "+ Thêm sản phẩm" → phải sang route /admin/products/create ----
    await page.evaluate(() => {
        const link = Array.from(document.querySelectorAll('a'))
            .find((a) => a.textContent.includes('Thêm sản phẩm'));
        link?.click();
    });
    await page.waitForTimeout(1800);
    await page.screenshot({ path: '/tmp/qa-mobile-page-product-create.png', fullPage: true });
    results.push({ step: 'product-create', ...(await inspect(page)) });

    // ---- Quay lại, bấm "Sửa" ở dòng đầu → /admin/products/:id/edit ----
    await page.goto(`${BASE}/admin/products`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    await page.evaluate(() => {
        const link = Array.from(document.querySelectorAll('a'))
            .find((a) => a.textContent.trim() === 'Sửa');
        link?.click();
    });
    await page.waitForTimeout(1800);
    await page.screenshot({ path: '/tmp/qa-mobile-page-product-edit.png', fullPage: true });
    results.push({ step: 'product-edit', ...(await inspect(page)) });

    // ---- Chi tiết đơn hàng → /admin/orders/:id ----
    await page.goto(`${BASE}/admin/orders`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    await page.evaluate(() => {
        const link = Array.from(document.querySelectorAll('a'))
            .find((a) => a.textContent.trim() === 'Chi tiết');
        link?.click();
    });
    await page.waitForTimeout(1800);
    await page.screenshot({ path: '/tmp/qa-mobile-page-order-detail.png', fullPage: true });
    results.push({ step: 'order-detail', ...(await inspect(page)) });

    // ---- Lịch sử tồn kho → /admin/inventory/:sku/history ----
    await page.goto(`${BASE}/admin/inventory`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button'))
            .find((b) => b.className.includes('font-mono'));
        btn?.click();
    });
    await page.waitForTimeout(1800);
    await page.screenshot({ path: '/tmp/qa-mobile-page-inventory-history.png', fullPage: true });
    results.push({ step: 'inventory-history', ...(await inspect(page)) });

    // ---- Toast phải hiện khi bấm xoá (dùng confirm dialog) ----
    await page.goto(`${BASE}/admin/products`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    const toast = await page.evaluate(async () => {
        const btn = Array.from(document.querySelectorAll('button'))
            .find((b) => b.textContent.trim() === 'Xóa');
        btn?.click();
        await new Promise((r) => setTimeout(r, 800));

        const dialog = document.querySelector('.fixed.z-\\[110\\]');
        const okBtn = Array.from(dialog?.querySelectorAll('button') || [])
            .find((b) => b.textContent.includes('Đồng ý'));

        okBtn?.click();
        await new Promise((r) => setTimeout(r, 2500));

        return {
            confirmShown: !!dialog,
            toastShown: !!document.querySelector('#app-toasts'),
            toastText: document.querySelector('#app-toasts')?.textContent?.trim() || null,
            overlayPresent: !!document.querySelector('.fixed.inset-0.z-50'),
        };
    });

    await page.screenshot({ path: '/tmp/qa-mobile-page-toast.png' });
    results.push({ step: 'confirm-toast', ...toast });

    return results;
}
