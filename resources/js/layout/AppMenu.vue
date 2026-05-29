<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppMenuItem from './AppMenuItem.vue';

const page = usePage();
const isAdmin = computed(() => page.props?.auth?.user?.role === 'admin');
const isSales = computed(() => page.props?.auth?.user?.role === 'sales');
const authUserId = computed(() => page.props?.auth?.user?.id);

const model = computed(() => [
    {
        label: '대시보드',
        items: [
            {
                label: '대시보드',
                icon: 'pi pi-fw pi-home',
                to: '/dashboard',
            },
            {
                label: '영업 대시보드',
                icon: 'pi pi-fw pi-bolt',
                to: '/sales/dashboard',
                visible: isSales.value,
            },
            {
                label: '공지사항',
                icon: 'pi pi-fw pi-megaphone',
                to: '/notices',
            },
        ],
    },
    {
        label: '마스터 관리',
        path: '/master-data',
        items: [
            { label: '마스터 홈', icon: 'pi pi-fw pi-th-large', to: '/master-data', visible: isAdmin.value },
            { label: '의약품 관리', icon: 'pi pi-fw pi-tag', to: '/products' },
            { label: '약국 관리', icon: 'pi pi-fw pi-shop', to: '/pharmacies' },
            { label: '병의원 관리', icon: 'pi pi-fw pi-building', to: '/hospitals' },
        ],
    },
    {
        label: '거래처',
        path: '/companies',
        items: [
            { label: '업체 관리', icon: 'pi pi-fw pi-briefcase', to: '/companies' },
        ],
    },
    {
        label: '실적',
        path: '/performance',
        items: [
            {
                label: '실적 목록',
                icon: 'pi pi-fw pi-list',
                to: '/performance',
            },
            {
                label: '실적 등록',
                icon: 'pi pi-fw pi-plus-circle',
                to: '/performance/create',
            },
            {
                label: '실적 CSV 일괄 등록',
                icon: 'pi pi-fw pi-upload',
                to: '/performance/import',
            },
        ],
    },
    {
        label: '정산',
        items: [
            {
                label: '정산 관리',
                icon: 'pi pi-fw pi-calculator',
                to: '/settlements',
            },
            {
                label: '수수료 명세',
                icon: 'pi pi-fw pi-wallet',
                to: isAdmin.value
                    ? '/commission-summary'
                    : `/commission-summary/users/${authUserId.value}/statement`,
            },
            {
                label: '월간 보고서',
                icon: 'pi pi-fw pi-chart-line',
                to: '/reports/monthly',
                visible: isAdmin.value,
            },
        ],
    },
    {
        label: '관리',
        path: '/admin',
        visible: isAdmin.value,
        items: [
            {
                label: '사용자 관리',
                icon: 'pi pi-fw pi-user-edit',
                to: '/users',
                visible: isAdmin.value,
            },
            {
                    label: '목표 관리',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/sales-quotas',
                    visible: isAdmin.value,
                },
            { label: '영업사원 (조회)', icon: 'pi pi-fw pi-id-card', to: '/clients/sales', visible: isAdmin.value },
            { label: '설정', icon: 'pi pi-fw pi-cog', to: '/profile' },
        ],
    },
]);
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item.label">
            <AppMenuItem v-if="!item.separator && item.visible !== false" :item="item" :index="i" />
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>
