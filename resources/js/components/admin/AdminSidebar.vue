<template>
    <!--
        SIDEBAR QUẢN TRỊ

        Desktop (>= lg): sidebar cố định bên trái, có thể thu gọn còn 64px.
        Mobile (< lg) : sidebar thành DRAWER trượt vào từ trái, ẩn mặc định,
                       mở bằng nút hamburger trên AdminHeader.

        Vì sao cần drawer: sidebar cố định `w-64` (256px) trên màn 390px sẽ
        chỉ còn ~134px cho nội dung -> bảng admin bị bóp vỡ, toolbar vỡ hàng.

        LƯU Ý: `v-if="isOpen"` phải nằm TRÊN chính thẻ `<aside>`/overlay
        (element con của <Transition>). Nếu đặt ở thẻ cha, Transition sẽ
        không bao giờ chạy và drawer luôn nằm trong DOM -> overlay đè lên
        nút hamburger, không click được.
    -->
    <Teleport to="body">
        <!-- Lớp nền mờ (chỉ mobile) -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="lg:hidden fixed inset-0 z-40 bg-black/50"
                @click="close"
            />
        </Transition>

        <!-- Drawer trượt (chỉ mobile) -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="-translate-x-full"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-to-class="-translate-x-full"
        >
            <aside
                v-if="isOpen"
                class="lg:hidden fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-gray-900 text-gray-300 flex flex-col shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-label="Menu quản trị"
            >
                <SidebarContent variant="mobile" :collapsed="false" @navigate="close" />
            </aside>
        </Transition>
    </Teleport>

    <!--
        Sidebar cố định (chỉ desktop).

        Dùng `hidden lg:flex` thay vì `v-if` để:
          - không nháy layout lúc khởi động (JS chưa kịp chạy matchMedia)
          - DOM ổn định, trạng thái thu gọn không bị mất
    -->
    <aside
        class="hidden lg:flex bg-gray-900 text-gray-300 shrink-0 flex-col transition-all"
        :class="collapsed ? 'w-16' : 'w-64'"
    >
        <SidebarContent
            variant="desktop"
            :collapsed="collapsed"
            @toggle-collapse="collapsed = !collapsed"
        />
    </aside>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import SidebarContent from './SidebarContent.vue';
import { useAdminNav } from '../../composables/useAdminNav';
import { useMediaQuery } from '../../composables/useMediaQuery';

const { isOpen, close } = useAdminNav();

/** >= 1024px (Tailwind `lg`) mới dùng sidebar cố định. */
const isDesktop = useMediaQuery('(min-width: 1024px)');

const collapsed = ref(false);

// Khi chuyển từ mobile sang desktop thì đóng drawer cho sạch.
watch(isDesktop, (value) => {
    if (value) {
        close();
    }
});

// Khoá cuộn trang khi drawer mobile đang mở.
watch(isOpen, (value) => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.style.overflow = value && !isDesktop.value ? 'hidden' : '';
});

/** Đóng drawer bằng phím Esc. */
function onKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) {
        close();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>
