import { ref } from 'vue';

/**
 * =============================================================================
 *  PIN247 — trạng thái chia sẻ cho sidebar quản trị
 *
 *  AdminSidebar và AdminHeader là hai component anh em (cùng nằm trong
 *  AdminLayout) nhưng nằm ở hai vị trí khác nhau trong DOM. Muốn nút
 *  "hamburger" ở header mở/đóng được sidebar ở mobile thì cần một state
 *  CHIA SẺ giữa hai component.
 *
 *  Dùng module-level `ref` (singleton) thay vì Pinia store vì:
 *    - State này chỉ sống trong vòng đời trang quản trị, không cần persist.
 *    - Không muốn lọt vào devtools/store persist của Pinia.
 *
 *  LƯU Ý: import hàm `useAdminNav()` ở MỌI nơi cần truy cập — không import
 *  trực tiếp biến `isOpen`, để giữ đúng pattern của composable.
 * =============================================================================
 */

const isOpen = ref(false);

/** Mở sidebar (mobile). */
export function open() {
    isOpen.value = true;
}

/** Đóng sidebar (mobile). */
export function close() {
    isOpen.value = false;
}

/** Đảo trạng thái sidebar (mobile). */
export function toggle() {
    isOpen.value = !isOpen.value;
}

export function useAdminNav() {
    return { isOpen, open, close, toggle };
}
