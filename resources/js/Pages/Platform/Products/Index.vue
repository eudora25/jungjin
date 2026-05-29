<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
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
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

const refresh = () => {
    router.get(route('platform.products.index'), { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true });
};
debouncedWatch(search, refresh, { debounce: 400 });

const onPage = (e: { page: number }) => {
    router.get(route('platform.products.index'), { search: search.value || undefined, page: e.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head title="의약품 관리 (전역)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-2xl font-bold">의약품 관리 <span class="text-base font-normal text-surface-500">(전체 제약사)</span></h1>
                <p class="text-surface-500 mt-1 text-sm">모든 제약사의 의약품 — 전체 {{ products.total }}건 · 등록/수정은 다음 단계</p>
            </div>

            <InputText v-model="search" placeholder="제품명·코드·보험코드·제조사 검색" class="w-full md:w-[28rem]" />

            <DataTable :value="products.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 의약품이 없습니다.</div>
                </template>
                <Column header="제약사" style="width: 160px">
                    <template #body="{ data }">
                        <Tag :value="data.tenant_name ?? '-'" severity="contrast" />
                    </template>
                </Column>
                <Column header="제품명">
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.product_name }}</span>
                        <div v-if="data.product_code" class="text-xs text-surface-400 mt-1">{{ data.product_code }}</div>
                    </template>
                </Column>
                <Column header="보험코드" style="width: 130px">
                    <template #body="{ data }">{{ data.insurance_code ?? '-' }}</template>
                </Column>
                <Column header="제조사" style="width: 150px">
                    <template #body="{ data }">{{ data.manufacturer ?? '-' }}</template>
                </Column>
                <Column header="상태" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="data.status" :severity="data.status === 'active' ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column header="승인" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="data.approval_status" :severity="data.approval_status === 'approved' ? 'success' : 'warn'" />
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="products.per_page" :total-records="products.total"
                       :first="(products.current_page - 1) * products.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
