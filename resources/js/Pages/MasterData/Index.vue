<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Button from 'primevue/button';

const props = defineProps<{
    counts: {
        products: number;
        pharmacies: number;
        hospitals: number;
    };
}>();

const n = (v: number) => new Intl.NumberFormat('ko-KR').format(v);

interface MasterCard {
    key: keyof typeof props.counts;
    label: string;
    desc: string;
    icon: string;
    index: string;
    create: string;
    color: string;
}

const cards: MasterCard[] = [
    {
        key: 'products',
        label: '의약품 관리',
        desc: '보험코드·성분·제형·제조사 등 의약품(제품) 마스터',
        icon: 'pi pi-tag',
        index: '/products',
        create: '/products/create',
        color: 'text-blue-600',
    },
    {
        key: 'pharmacies',
        label: '약국 관리',
        desc: '약국 마스터 — 거래처와 독립된 기준정보',
        icon: 'pi pi-shop',
        index: '/pharmacies',
        create: '/pharmacies/create',
        color: 'text-emerald-600',
    },
    {
        key: 'hospitals',
        label: '병의원 관리',
        desc: '병원·의원·치과·한의원 마스터 — 거래처와 독립',
        icon: 'pi pi-building',
        index: '/hospitals',
        create: '/hospitals/create',
        color: 'text-violet-600',
    },
];
</script>

<template>
    <Head title="마스터 관리" />
    <AdminLayout>
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-bold">마스터 관리</h1>
                <p class="text-surface-500 mt-1 text-sm">
                    병의원·약국·의약품을 거래처와 독립된 기준정보 마스터로 관리합니다.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card v-for="c in cards" :key="c.key">
                    <template #content>
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <i :class="[c.icon, c.color, 'text-xl']" />
                                    <span class="font-semibold text-lg">{{ c.label }}</span>
                                </div>
                                <p class="text-xs text-surface-500 mt-1">{{ c.desc }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-end justify-between">
                            <div>
                                <div class="text-sm text-surface-500">등록 건수</div>
                                <div class="text-3xl font-bold mt-1 tabular-nums">{{ n(props.counts[c.key]) }}</div>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <Link :href="c.index" class="flex-1">
                                <Button label="목록" icon="pi pi-list" severity="secondary" outlined class="w-full" />
                            </Link>
                            <Link :href="c.create" class="flex-1">
                                <Button label="등록" icon="pi pi-plus-circle" class="w-full" />
                            </Link>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
