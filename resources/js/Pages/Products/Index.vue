<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

type ApprovalStatus = 'draft' | 'pending' | 'reviewed' | 'approved' | 'rejected';

interface Product {
    id: number;
    insurance_code: string;
    product_code: string;
    product_name: string;
    manufacturer: string | null;
    category: string | null;
    price: string | number | null;
    status: 'active' | 'inactive' | 'discontinued';
    approval_status: ApprovalStatus;
    drug_type: 'general' | 'etc' | 'narcotic' | 'psychotropic';
    nims_managed: boolean;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    products: Paginator<Product>;
    filters: {
        search: string;
        category: string | null;
        status: string | null;
        approval_status: string | null;
    };
    categories: string[];
    can: { create: boolean };
}>();

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? null);
const status = ref(props.filters.status ?? null);
const approvalStatus = ref(props.filters.approval_status ?? null);

const statusOptions = [
    { label: '전체', value: null },
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
    { label: '단종', value: 'discontinued' },
];

const approvalOptions = [
    { label: '전체 승인 단계', value: null },
    { label: '초안', value: 'draft' },
    { label: '검수 대기', value: 'pending' },
    { label: '검수 완료', value: 'reviewed' },
    { label: '승인 완료', value: 'approved' },
    { label: '반려', value: 'rejected' },
];

const categoryOptions = [
    { label: '전체 카테고리', value: null },
    ...props.categories.map((c) => ({ label: c, value: c })),
];

const statusLabel = (s: string) =>
    ({ active: '활성', inactive: '비활성', discontinued: '단종' }[s] ?? s);
const statusSeverity = (s: string) =>
    ({ active: 'success', inactive: 'secondary', discontinued: 'danger' }[s] ?? 'secondary');

const approvalLabel = (s: string) =>
    ({ draft: '초안', pending: '검수 대기', reviewed: '검수 완료', approved: '승인 완료', rejected: '반려' }[s] ?? s);
const approvalSeverity = (s: string) =>
    ({ draft: 'secondary', pending: 'warn', reviewed: 'info', approved: 'success', rejected: 'danger' }[s] ?? 'secondary');

const drugTypeLabel = (s: string) =>
    ({ general: '일반', etc: 'ETC', narcotic: '마약', psychotropic: '향정' }[s] ?? s);
const drugTypeSeverity = (s: string) =>
    ({ general: 'secondary', etc: 'info', narcotic: 'danger', psychotropic: 'warn' }[s] ?? 'secondary');

const formatPrice = (p: string | number | null) => {
    if (p === null) return '-';
    const n = typeof p === 'string' ? parseFloat(p) : p;
    return new Intl.NumberFormat('ko-KR').format(n) + '원';
};

const queryParams = () => ({
    search: search.value || undefined,
    category: category.value || undefined,
    status: status.value || undefined,
    approval_status: approvalStatus.value || undefined,
});

const refresh = () => {
    router.get(route('products.index'), queryParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

debouncedWatch(search, refresh, { debounce: 400 });

const onPage = (event: { page: number }) => {
    router.get(
        route('products.index'),
        { ...queryParams(), page: event.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const resetFilters = () => {
    search.value = '';
    category.value = null;
    status.value = null;
    approvalStatus.value = null;
    refresh();
};
</script>

<template>
    <Head title="제품 관리" />

    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">제품 관리</h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">의약품 마스터. 전체 {{ products.total }}건</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="can.create" :href="route('products.import.form')">
                        <Button label="CSV 일괄 등록" icon="pi pi-upload" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.create" :href="route('products.create')">
                        <Button label="제품 등록" icon="pi pi-plus" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:flex-wrap md:items-center gap-3">
                <InputText v-model="search" placeholder="제품명·보험코드·표준코드·바코드·성분명·제조사 검색" class="w-full md:w-[28rem]" />
                <Select
                    v-model="category"
                    :options="categoryOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="카테고리"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <Select
                    v-model="approvalStatus"
                    :options="approvalOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="승인 단계"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <Select
                    v-model="status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="판매 상태"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <div class="flex items-center gap-2 w-full md:w-auto md:ml-auto">
                    <Button icon="pi pi-filter" label="검색" severity="info" @click="refresh" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilters" />
                </div>
            </div>

            <DataTable :value="products.data" stripedRows responsiveLayout="scroll">
                <template #empty>
                    <div class="text-center py-10 text-surface-500">조건에 맞는 제품이 없습니다.</div>
                </template>

                <Column header="제품명" style="min-width: 18rem">
                    <template #body="{ data }">
                        <Link
                            :href="route('products.show', data.id)"
                            class="font-medium text-surface-900 dark:text-surface-0 hover:text-primary"
                        >
                            {{ data.product_name }}
                        </Link>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span v-if="data.manufacturer" class="text-xs text-surface-500">{{ data.manufacturer }}</span>
                            <Tag
                                v-if="data.drug_type && data.drug_type !== 'general'"
                                :value="drugTypeLabel(data.drug_type)"
                                :severity="drugTypeSeverity(data.drug_type)"
                                class="!text-[10px] !py-0.5"
                            />
                            <Tag
                                v-if="data.nims_managed"
                                value="NIMS"
                                severity="danger"
                                class="!text-[10px] !py-0.5"
                            />
                        </div>
                    </template>
                </Column>

                <Column header="보험코드" style="width: 120px">
                    <template #body="{ data }">
                        <span class="text-sm font-mono">{{ data.insurance_code }}</span>
                    </template>
                </Column>

                <Column header="제품코드" style="width: 120px">
                    <template #body="{ data }">
                        <span class="text-sm font-mono">{{ data.product_code }}</span>
                    </template>
                </Column>

                <Column header="카테고리" style="width: 110px">
                    <template #body="{ data }">
                        <span v-if="data.category" class="text-sm">{{ data.category }}</span>
                        <span v-else class="text-xs text-surface-400">-</span>
                    </template>
                </Column>

                <Column header="약가" style="width: 110px; text-align: right">
                    <template #body="{ data }">
                        <span class="text-sm">{{ formatPrice(data.price) }}</span>
                    </template>
                </Column>

                <Column header="승인" style="width: 110px">
                    <template #body="{ data }">
                        <Tag :value="approvalLabel(data.approval_status)" :severity="approvalSeverity(data.approval_status)" />
                    </template>
                </Column>

                <Column header="판매" style="width: 80px">
                    <template #body="{ data }">
                        <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                    </template>
                </Column>
            </DataTable>

            <Paginator
                :rows="products.per_page"
                :totalRecords="products.total"
                :first="(products.current_page - 1) * products.per_page"
                @page="onPage"
            />
        </div>
    </AdminLayout>
</template>
