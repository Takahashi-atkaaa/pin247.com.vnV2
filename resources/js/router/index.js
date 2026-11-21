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
                path: 'inventory',
                name: 'admin-inventory',
                component: () => import('../pages/admin/InventoryPage.vue'),
                meta: { title: 'Quản lý tồn kho - Admin PIN247' },
            },
            {
                path: 'orders',
                name: 'admin-orders',
                component: () => import('../pages/admin/OrdersPage.vue'),
                meta: { title: 'Quản lý đơn hàng - Admin PIN247' },
            },
            {
                path: 'bang-gia',
                name: 'admin-price-list',
                component: () => import('../pages/admin/PriceListPage.vue'),
                meta: { title: 'Quản lý bảng giá - Admin PIN247' },
            },
            {
                path: 'customers',
                name: 'admin-customers',
                component: () => import('../pages/admin/CustomersPage.vue'),
                meta: { title: 'Quản lý khách hàng - Admin PIN247' },
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

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'home' };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'home' };
    }

    return true;
});

router.afterEach((to) => {
    document.title = to.meta.title || 'PIN247';
});

export default router;
