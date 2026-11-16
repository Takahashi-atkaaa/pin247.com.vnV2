/**
 * QA giao diện quản trị: MOBILE (390x844) + DESKTOP (1280x800).
 *
 * Kiểm tra:
 *  - Mobile: sidebar ẩn, hamburger có, bảng chuyển sang card, không tràn ngang.
 *  - Desktop: sidebar cố định hiện, bảng dạng table, nút thu gọn hoạt động.
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

    return page.evaluate(() => Boolean(localStorage.getItem('pin247_token')));
}

async function inspect(page, label, path) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    const data = await page.evaluate(() => {
        const doc = document.documentElement;
        const visible = (el) => {
            if (!el) return false;
            let p = el;
            while (p && p !== document.body) {
                if (getComputedStyle(p).display === 'none') return false;
                p = p.parentElement;
            }
            return true;
        };

        const aside = document.querySelector('aside:not([role="dialog"])');
        const drawer = document.querySelector('aside[role="dialog"]');
        const tables = Array.from(document.querySelectorAll('table')).filter(visible);
        const cards = Array.from(document.querySelectorAll('main .md\\:hidden > div')).filter(visible);

        // Label/value của card đầu tiên (kiểm tra card có đủ nội dung)
        const firstCardText = cards[0] ? cards[0].innerText.replace(/\n+/g, ' | ').slice(0, 260) : null;

        // Header: có bao nhiêu nút, có hamburger không
        const header = document.querySelector('header');
        const headerButtons = header ? Array.from(header.querySelectorAll('button')).length : 0;
        const hamburger = header ? header.querySelector('button[aria-label="Mở menu quản trị"]') : null;

        // Toolbar inputs có tràn không
        const overflowing = [];
        document.querySelectorAll('main input, main select, main button').forEach((el) => {
            const r = el.getBoundingClientRect();
            if (r.width > 0 && r.right > window.innerWidth + 2) {
                overflowing.push(el.tagName.toLowerCase() + ' right=' + Math.round(r.right));
            }
        });

        return {
            viewport: window.innerWidth,
            docScrollWidth: doc.scrollWidth,
            horizontalOverflow: doc.scrollWidth > window.innerWidth + 2,
            sidebarVisible: visible(aside),
            sidebarWidth: aside && visible(aside) ? Math.round(aside.getBoundingClientRect().width) : 0,
            drawerVisible: visible(drawer),
            hamburgerVisible: hamburger ? visible(hamburger) : false,
            headerButtons,
            visibleTables: tables.length,
            visibleCards: cards.length,
            firstCardText,
            overflowingControls: overflowing.slice(0, 5),
            title: document.querySelector('header h1')?.innerText || null,
        };
    });

    await page.screenshot({ path: `/tmp/qa-${label}-${path.replace(/\W+/g, '_')}.png` });

    return { label, path, ...data };
}

export default async function run(page, ui) {
    const results = [];

    // ---------------- MOBILE ----------------
    await page.setViewportSize({ width: 390, height: 844 });
    const hasToken = await login(page);
    results.push({ step: 'login', hasToken });
    if (!hasToken) return { results, error: 'login failed' };

    for (const path of [
        '/admin/dashboard',
        '/admin/products',
        '/admin/inventory',
        '/admin/orders',
        '/admin/bang-gia',
        '/admin/customers',
        '/admin/bigseller',
    ]) {
        results.push(await inspect(page, 'mobile', path));
    }

    // Mở drawer trên mobile
    await page.goto(`${BASE}/admin/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    const drawerOpen = await page.evaluate(async () => {
        const btn = document.querySelector('button[aria-label="Mở menu quản trị"]');
        if (!btn) return { error: 'no hamburger' };
        btn.click();
        await new Promise((r) => setTimeout(r, 600));
        const drawer = document.querySelector('aside[role="dialog"]');
        const r = drawer?.getBoundingClientRect();
        return {
            drawerOpen: !!drawer,
            drawerLeft: r ? Math.round(r.left) : null,
            drawerWidth: r ? Math.round(r.width) : 0,
            menuItems: drawer ? drawer.querySelectorAll('nav a').length : 0,
            bodyLocked: document.body.style.overflow,
        };
    });
    results.push({ step: 'drawer-open', ...drawerOpen });
    await page.screenshot({ path: '/tmp/qa-mobile-drawer-open.png' });

    // Đóng drawer khi bấm menu
    const drawerClose = await page.evaluate(async () => {
        const link = document.querySelector('aside[role="dialog"] nav a');
        link?.click();
        await new Promise((r) => setTimeout(r, 600));
        return {
            drawerClosed: !document.querySelector('aside[role="dialog"]'),
            bodyUnlocked: document.body.style.overflow === '',
            navigatedTo: location.pathname,
        };
    });
    results.push({ step: 'drawer-close', ...drawerClose });

    // ---------------- DESKTOP ----------------
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.waitForTimeout(500);

    for (const path of ['/admin/dashboard', '/admin/products', '/admin/orders']) {
        results.push(await inspect(page, 'desktop', path));
    }

    // Nút thu gọn sidebar
    const collapse = await page.evaluate(async () => {
        const aside = document.querySelector('aside:not([role="dialog"])');
        const before = aside ? Math.round(aside.getBoundingClientRect().width) : 0;
        const btn = Array.from(document.querySelectorAll('aside button')).find((b) =>
            b.textContent.includes('Thu gọn'));
        btn?.click();
        await new Promise((r) => setTimeout(r, 600));
        const after = aside ? Math.round(aside.getBoundingClientRect().width) : 0;
        return { before, after, works: before !== after };
    });
    results.push({ step: 'collapse-sidebar', ...collapse });

    return results;
}
