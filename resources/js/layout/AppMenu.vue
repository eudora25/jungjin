<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppMenuItem from './AppMenuItem.vue';

const page = usePage();
const isPharma = computed(() => page.props?.auth?.user?.role === 'pharma');
const isCso = computed(() => page.props?.auth?.user?.role === 'cso');
const isPlatform = computed(() => page.props?.auth?.user?.role === 'platform');
const authUserId = computed(() => page.props?.auth?.user?.id);

// GAP-10 임퍼서네이션: platform 이 특정 제약사로 진입 중인지
const impersonating = computed(() => page.props?.impersonating ?? null);
// 제약사 관리자처럼 동작 (pharma, 또는 진입 중인 platform)
const actingPharma = computed(() => isPharma.value || (isPlatform.value && !!impersonating.value));
// 테넌트 운영 화면 접근 (pharma/cso, 또는 진입 중인 platform)
const inTenantView = computed(() => !isPlatform.value || !!impersonating.value);

const model = computed(() => [
    {
        label: '플랫폼',
        visible: isPlatform.value && !impersonating.value,
        items: [
            { label: '제약사 관리', icon: 'pi pi-fw pi-building-columns', to: '/platform/tenants' },
            { label: '의약품 관리', icon: 'pi pi-fw pi-tag', to: '/platform/products' },
            { label: '병의원 관리', icon: 'pi pi-fw pi-building', to: '/platform/hospitals' },
            { label: '약국 관리', icon: 'pi pi-fw pi-shop', to: '/platform/pharmacies' },
            { label: '사용자 관리', icon: 'pi pi-fw pi-users', to: '/platform/users' },
        ],
    },
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
                visible: isCso.value,
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
        visible: actingPharma.value,
        items: [
            { label: '마스터 홈', icon: 'pi pi-fw pi-th-large', to: '/master-data', visible: actingPharma.value },
            { label: '의약품 관리', icon: 'pi pi-fw pi-tag', to: '/products' },
            { label: '약국 관리', icon: 'pi pi-fw pi-shop', to: '/pharmacies' },
            { label: '병의원 관리', icon: 'pi pi-fw pi-building', to: '/hospitals' },
        ],
    },
    {
        label: '거래처',
        path: '/companies',
        visible: inTenantView.value,
        items: [
            { label: '업체 관리', icon: 'pi pi-fw pi-briefcase', to: '/companies' },
        ],
    },
    {
        label: '실적',
        path: '/performance',
        visible: inTenantView.value,
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
        visible: inTenantView.value,
        items: [
            {
                label: '정산 관리',
                icon: 'pi pi-fw pi-calculator',
                to: '/settlements',
            },
            {
                label: '수수료 명세',
                icon: 'pi pi-fw pi-wallet',
                to: actingPharma.value
                    ? '/commission-summary'
                    : `/commission-summary/users/${authUserId.value}/statement`,
            },
            {
                label: '월간 보고서',
                icon: 'pi pi-fw pi-chart-line',
                to: '/reports/monthly',
                visible: actingPharma.value,
            },
        ],
    },
    {
        label: '관리',
        path: '/admin',
        visible: actingPharma.value,
        items: [
            {
                label: '사용자 관리',
                icon: 'pi pi-fw pi-user-edit',
                to: '/users',
                visible: actingPharma.value,
            },
            {
                    label: '목표 관리',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/sales-quotas',
                    visible: actingPharma.value,
                },
            { label: '영업사원 (조회)', icon: 'pi pi-fw pi-id-card', to: '/clients/sales', visible: actingPharma.value },
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
