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

interface ProductRow {
    id: number;
    product_name: string;
    product_code: string | null;
    insurance_code: string | null;
    manufacturer: string | null;
    status: string;
    approval_status: string;
    tenant_name: string | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    products: Paginated<ProductRow>;
    filters: { search: string; status: string | null; approval_status: string | null };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status || null);
const approval = ref(props.filters.approval_status || null);

const statusLabels: Record<string, string> = { active: '활성', inactive: '비활성', discontinued: '단종' };
const approvalLabels: Record<string, string> = {
    draft: '작성중', pending: '검수대기', reviewed: '검수됨', approved: '승인', rejected: '반려',
};
const statusSelect = [
    { label: '활성', value: 'active' }, { label: '비활성', value: 'inactive' }, { label: '단종', value: 'discontinued' },
];
const approvalSelect = [
    { label: '작성중', value: 'draft' }, { label: '검수대기', value: 'pending' }, { label: '검수됨', value: 'reviewed' },
    { label: '승인', value: 'approved' }, { label: '반려', value: 'rejected' },
];
const approvalSeverity = (a: string) => (a === 'approved' ? 'success' : a === 'rejected' ? 'danger' : 'warn');

const query = (extra: Record<string, unknown> = {}) => ({
    search: search.value || undefined,
    status: status.value || undefined,
    approval_status: approval.value || undefined,
    ...extra,
});

const refresh = () => {
    router.get(route('platform.products.index'), query(),
        { preserveState: true, preserveScroll: true, replace: true });
};
debouncedWatch([search, status, approval], refresh, { debounce: 300 });

const onPage = (e: { page: number }) => {
    router.get(route('platform.products.index'), query({ page: e.page + 1 }),
        { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head title="의약품 관리 (전역)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">의약품 관리 <span class="text-base font-normal text-surface-500">(전체 제약사)</span></h1>
                    <p class="text-surface-500 mt-1 text-sm">모든 제약사의 의약품 마스터 — 전체 {{ products.total }}건</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('platform.products.create')">
                        <Button label="의약품 등록" icon="pi pi-plus" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
                <InputText v-model="search" placeholder="제품명·코드·보험코드·제조사 검색" class="w-full sm:w-80" />
                <Select v-model="status" :options="statusSelect" option-label="label" option-value="value"
                        placeholder="상태" show-clear class="w-full sm:w-40" />
                <Select v-model="approval" :options="approvalSelect" option-label="label" option-value="value"
                        placeholder="승인" show-clear class="w-full sm:w-40" />
            </div>

            <DataTable :value="products.data" striped-rows class="text-sm">
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 의약품이 없습니다.</div>
                </template>
                <Column header="제약사" style="width: 150px">
                    <template #body="{ data }">
                        <Tag :value="data.tenant_name ?? '-'" severity="contrast" />
                    </template>
                </Column>
                <Column header="제품명" style="min-width: 180px">
                    <template #body="{ data }">
                        <Link :href="route('platform.products.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.product_name }}
                        </Link>
                        <div v-if="data.product_code" class="text-xs text-surface-400 mt-1">{{ data.product_code }}</div>
                    </template>
                </Column>
                <Column header="보험코드" body-class="text-center" style="width: 130px">
                    <template #body="{ data }">{{ data.insurance_code ?? '-' }}</template>
                </Column>
                <Column header="제조사" style="width: 150px">
                    <template #body="{ data }">{{ data.manufacturer ?? '-' }}</template>
                </Column>
                <Column header="상태" body-class="text-center" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="statusLabels[data.status] ?? data.status" :severity="data.status === 'active' ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column header="승인" body-class="text-center" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="approvalLabels[data.approval_status] ?? data.approval_status" :severity="approvalSeverity(data.approval_status)" />
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="products.per_page" :total-records="products.total"
                       :first="(products.current_page - 1) * products.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
