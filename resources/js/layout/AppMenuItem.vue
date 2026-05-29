<script setup>
import { useLayout } from '@/layout/composables/layout';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { layoutState, isDesktop } = useLayout();

const props = defineProps({
    item: {
        type: Object,
        default: () => ({}),
    },
    root: {
        type: Boolean,
        default: true,
    },
    parentPath: {
        type: String,
        default: null,
    },
});

const page = usePage();
const currentUrl = computed(() => page.url);

const fullPath = computed(() =>
    props.item.path
        ? props.parentPath
            ? props.parentPath + props.item.path
            : props.item.path
        : null,
);

const isActive = computed(() => {
    return props.item.path
        ? layoutState.activePath?.startsWith(fullPath.value)
        : layoutState.activePath === props.item.to;
});

const isCurrentRoute = computed(
    () => props.item.to && currentUrl.value === props.item.to,
);

const itemClick = (event, item) => {
    if (item.disabled) {
        event.preventDefault();
        return;
    }

    if (item.command) {
        item.command({ originalEvent: event, item });
    }

    if (item.items) {
        if (isActive.value) {
            layoutState.activePath = layoutState.activePath.replace(item.path, '');
        } else {
            layoutState.activePath = fullPath.value;
            layoutState.menuHoverActive = true;
        }
    } else {
        layoutState.overlayMenuActive = false;
        layoutState.mobileMenuActive = false;
        layoutState.menuHoverActive = false;
    }
};

const onMouseEnter = () => {
    if (
        isDesktop() &&
        props.root &&
        props.item.items &&
        layoutState.menuHoverActive
    ) {
        layoutState.activePath = fullPath.value;
    }
};
</script>

<template>
    <li :class="{ 'layout-root-menuitem': root, 'active-menuitem': isActive }">
        <div v-if="root && item.visible !== false" class="layout-menuitem-root-text">
            {{ item.label }}
        </div>
        <a
            v-if="(!item.to || item.items) && item.visible !== false"
            :href="item.url"
            @click="itemClick($event, item)"
            :class="item.class"
            :target="item.target"
            tabindex="0"
            @mouseenter="onMouseEnter"
        >
            <i :class="item.icon" class="layout-menuitem-icon" />
            <span class="layout-menuitem-text">{{ item.label }}</span>
            <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items" />
        </a>
        <Link
            v-if="item.to && !item.items && item.visible !== false"
            :href="item.to"
            :method="item.method ?? 'get'"
            :as="item.as ?? 'a'"
            @click="itemClick($event, item)"
            :class="[item.class, { 'active-route': isCurrentRoute }]"
            tabindex="0"
            @mouseenter="onMouseEnter"
        >
            <i :class="item.icon" class="layout-menuitem-icon" />
            <span class="layout-menuitem-text">{{ item.label }}</span>
        </Link>
        <Transition v-if="item.items && item.visible !== false" name="layout-submenu">
            <ul v-show="root ? true : isActive" class="layout-submenu">
                <AppMenuItem
                    v-for="child in item.items"
                    :key="child.label + '_' + (child.to || child.path)"
                    :item="child"
                    :root="false"
                    :parentPath="fullPath"
                />
            </ul>
        </Transition>
    </li>
</template>
