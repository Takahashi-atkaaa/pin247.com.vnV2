import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { APP_BASE } from '../config';

const routes = [
    /*
    |--------------------------------------------------------------------------
    | Storefront
    |--------------------------------------------------------------------------
    |
    | Toàn bộ giao diện khách hàng nằm trong `StorefrontLayout` (header +
    | footer). Các route con viết KHÔNG có dấu "/" ở đầu vì chúng kế thừa
    | path của route cha.
    |
    */
    {
        path: '/',
        component: () => import('../layouts/StorefrontLayout.vue'),
        children: [
            {
                path: '',
                name: 'home',
                component: () => import('../pages/HomePage.vue'),
                meta: { title: 'PIN247 - Pin Lithium, Pin Máy Khoan, Mạch BMS' },
            },
            {
                path: 'products',
                name: 'products',
                component: () => import('../pages/ProductListPage.vue'),
                meta: { title: 'Sản phẩm - PIN247', pinCategories: true },
            },
            {
                path: 'bang-gia',
                name: 'price-list',
                component: () => import('../pages/PriceListPage.vue'),
                meta: { title: 'Bảng giá - PIN247' },
            },
            {
                path: 'products/:slug',
                name: 'product-detail',
                component: () => import('../pages/ProductDetailPage.vue'),
                meta: { title: 'Chi tiết sản phẩm - PIN247', pinCategories: true },
            },
            {
                path: 'cart',
                name: 'cart',
                component: () => import('../pages/CartPage.vue'),
                meta: { title: 'Giỏ hàng - PIN247', pinCategories: true },
            },
            {
                path: 'checkout',
                name: 'checkout',
                component: () => import('../pages/CheckoutPage.vue'),
                // Bắt buộc đăng nhập trước khi thanh toán (khớp với API
                // `/api/checkout` nằm trong middleware `auth:sanctum`).
                meta: { title: 'Thanh toán - PIN247', pinCategories: true, requiresAuth: true },
            },
            {
                path: 'order-tracking',
                name: 'order-tracking',
                component: () => import('../pages/OrderTrackingPage.vue'),
                meta: { title: 'Tra cứu đơn hàng - PIN247' },
            },
            {
                path: 'tai-khoan',
                name: 'account',
                component: () => import('../pages/AccountPage.vue'),
                meta: { title: 'Tài khoản của tôi - PIN247', requiresAuth: true },
            },
            {
                path: 'login',
                name: 'login',
                component: () => import('../pages/LoginPage.vue'),
                meta: { title: 'Đăng nhập - PIN247', guestOnly: true },
            },
            {
                path: 'register',
                name: 'register',
                component: () => import('../pages/RegisterPage.vue'),
                meta: { title: 'Đăng ký - PIN247', guestOnly: true },
            },
            {
                path: ':pathMatch(.*)*',
                name: 'not-found',
                component: () => import('../pages/NotFoundPage.vue'),
                meta: { title: 'Không tìm thấy - PIN247' },
            },
        ],
    },
    {
        path: '/admin',
        component: () => import('../layouts/AdminLayout.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
            { path: '', redirect: { name: 'admin-dashboard' } },
            {
                path: 'dashboard',
                name: 'admin-dashboard',
                component: () => import('../pages/admin/DashboardPage.vue'),
                meta: { title: 'Tổng quan - Admin PIN247' },
            },
            {
                path: 'products',
                name: 'admin-products',
                component: () => import('../pages/admin/ProductsPage.vue'),
                meta: { title: 'Quản lý sản phẩm - Admin PIN247' },
            },
            {
                // Trang riêng — Tạo / Sửa sản phẩm (không dùng popup).
                path: 'products/create',
                name: 'admin-product-create',
                component: () => import('../pages/admin/ProductFormPage.vue'),
                meta: { title: 'Tạo sản phẩm - Admin PIN247' },
            },
            {
                path: 'products/:id/edit',
                name: 'admin-product-edit',
                component: () => import('../pages/admin/ProductFormPage.vue'),
                meta: { title: 'Cập nhật sản phẩm - Admin PIN247' },
            },
            {
                // GĐ3 — Màn "Chi tiết biến thể" (clone Haravan).
                path: 'products/:id/variants/:variantId',
                name: 'admin-product-variant',
                component: () => import('../pages/admin/ProductVariantPage.vue'),
                meta: { title: 'Chi tiết biến thể - Admin PIN247' },
            },
            {
                // Trang riêng — Quản lý đơn vị tính của 1 biến thể.
                path: 'products/:id/variants/:variantId/units',
                name: 'admin-product-variant-units',
                component: () => import('../pages/admin/ProductUnitsPage.vue'),
                meta: { title: 'Đơn vị tính - Admin PIN247' },
            },
            {
                path: 'inventory',
                name: 'admin-inventory',
                component: () => import('../pages/admin/InventoryPage.vue'),
                meta: { title: 'Quản lý tồn kho - Admin PIN247' },
            },
            {
                // Trang riêng — Lịch sử tồn kho của 1 SKU.
                path: 'inventory/:sku/history',
                name: 'admin-inventory-history',
                component: () => import('../pages/admin/InventoryHistoryPage.vue'),
                meta: { title: 'Lịch sử tồn kho - Admin PIN247' },
            },
            {
                path: 'orders',
                name: 'admin-orders',
                component: () => import('../pages/admin/OrdersPage.vue'),
                meta: { title: 'Quản lý đơn hàng - Admin PIN247' },
            },
            {
                // Trang riêng — Chi tiết đơn hàng.
                path: 'orders/:id',
                name: 'admin-order-detail',
                component: () => import('../pages/admin/OrderDetailPage.vue'),
                meta: { title: 'Chi tiết đơn hàng - Admin PIN247' },
            },
            {
                path: 'bang-gia',
                name: 'admin-price-list',
                component: () => import('../pages/admin/PriceListPage.vue'),
                meta: { title: 'Quản lý bảng giá - Admin PIN247' },
            },
            {
                // Trang riêng — Thêm / Sửa bậc giá.
                path: 'bang-gia/tiers/create',
                name: 'admin-price-tier-create',
                component: () => import('../pages/admin/PriceTierFormPage.vue'),
                meta: { title: 'Thêm bậc giá - Admin PIN247' },
            },
            {
                path: 'bang-gia/tiers/:id/edit',
                name: 'admin-price-tier-edit',
                component: () => import('../pages/admin/PriceTierFormPage.vue'),
                meta: { title: 'Cập nhật bậc giá - Admin PIN247' },
            },
            {
                path: 'customers',
                name: 'admin-customers',
                component: () => import('../pages/admin/CustomersPage.vue'),
                meta: { title: 'Quản lý khách hàng - Admin PIN247' },
            },
            {
                // Trang riêng — Lịch sử mua hàng của 1 khách.
                path: 'customers/:id',
                name: 'admin-customer-detail',
                component: () => import('../pages/admin/CustomerDetailPage.vue'),
                meta: { title: 'Chi tiết khách hàng - Admin PIN247' },
            },
            {
                path: 'support',
                name: 'admin-support',
                component: () => import('../pages/admin/SupportPage.vue'),
                meta: { title: 'Hỗ trợ khách hàng - Admin PIN247' },
            },
            {
                path: 'bigseller',
                name: 'admin-bigseller',
                component: () => import('../pages/admin/BigSellerPage.vue'),
                meta: { title: 'Đồng bộ BigSeller - Admin PIN247' },
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(APP_BASE),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        }

        if (to.hash) {
            return { el: to.hash, behavior: 'smooth' };
        }

        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // --- Khu vực quản trị: BẮT BUỘC hỏi server ---
    //
    // `is_admin` KHÔNG được lấy từ localStorage (ai cũng sửa được).
    // Guard gọi `GET /api/me` để server xác thực token và trả về quyền
    // thật. Token giả/rỗng => 401 => session bị xoá => về trang đăng nhập.
    if (to.meta.requiresAdmin) {
        if (!auth.token) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        const user = await auth.fetchUser();

        if (!user) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        if (!user.is_admin) {
            return { name: 'home' };
        }

        return true;
    }

    if (to.meta.requiresAuth) {
        if (!auth.token) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        // Token có thể đã bị thu hồi (đổi mật khẩu, đăng xuất ở nơi khác).
        // Hỏi server để chắc chắn session còn hợp lệ trước khi vào trang.
        const user = await auth.fetchUser();

        if (!user) {
            auth.clearSession();

            return { name: 'login', query: { redirect: to.fullPath } };
        }

        return true;
    }

    if (to.meta.guestOnly) {
        if (!auth.token) {
            return true;
        }

        // Token còn hiệu lực => đã đăng nhập => không vào trang đăng nhập.
        // Token chết => coi như khách vãng lai, cho qua.
        const user = await auth.fetchUser();

        if (user) {
            return { name: 'home' };
        }

        auth.clearSession();

        return true;
    }

    return true;
});

router.afterEach((to) => {
    document.title = to.meta.title || 'PIN247';
});

export default router;
