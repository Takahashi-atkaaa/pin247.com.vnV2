import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * =============================================================================
 *  PIN247 — theo dõi media query
 *
 *  Cần thiết cho AdminSidebar: nút "thu gọn" chỉ có ý nghĩa ở desktop
 *  (>= lg). Ở mobile, sidebar là drawer trượt — luôn hiện nhãn, không bao
 *  giờ thu gọn.
 *
 *  Dùng `matchMedia` + listener thay vì `window.innerWidth` để không bị
 *  re-render liên tục khi kéo cửa sổ.
 * =============================================================================
 */

export function useMediaQuery(query) {
    const matches = ref(false);
    let media = null;

    function update(event) {
        matches.value = event.matches;
    }

    onMounted(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return;
        }

        media = window.matchMedia(query);
        matches.value = media.matches;
        media.addEventListener('change', update);
    });

    onBeforeUnmount(() => {
        media?.removeEventListener('change', update);
    });

    return matches;
}
