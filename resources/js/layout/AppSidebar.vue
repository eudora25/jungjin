<script setup>
import { useLayout } from '@/layout/composables/layout';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppMenu from './AppMenu.vue';

const { layoutState, isDesktop, hasOpenOverlay } = useLayout();
const sidebarRef = ref(null);
let outsideClickListener = null;
let removeNavListener = null;

const handleNavigation = () => {
    if (isDesktop()) layoutState.activePath = null;
    else layoutState.activePath = window.location.pathname;

    layoutState.overlayMenuActive = false;
    layoutState.mobileMenuActive = false;
    layoutState.menuHoverActive = false;
};

onMounted(() => {
    handleNavigation();
    removeNavListener = router.on('navigate', handleNavigation);
});

onBeforeUnmount(() => {
    unbindOutsideClickListener();
    removeNavListener?.();
});

const bindOutsideClickListener = () => {
    if (!outsideClickListener) {
        outsideClickListener = (event) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive = false;
            }
        };
        document.addEventListener('click', outsideClickListener);
    }
};

const unbindOutsideClickListener = () => {
    if (outsideClickListener) {
        document.removeEventListener('click', outsideClickListener);
        outsideClickListener = null;
    }
};

const isOutsideClicked = (event) => {
    const topbarButtonEl = document.querySelector('.layout-menu-button');
    return !(
        sidebarRef.value.isSameNode(event.target) ||
        sidebarRef.value.contains(event.target) ||
        topbarButtonEl?.isSameNode(event.target) ||
        topbarButtonEl?.contains(event.target)
    );
};
</script>

<template>
    <div ref="sidebarRef" class="layout-sidebar">
        <AppMenu />
    </div>
</template>
