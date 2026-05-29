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

interface PharmacyRow {
    id: number;
    pharmacy_name: string;
    pharmacy_code: string | null;
    business_registration_number: string | null;
    representative_name: string | null;
    status: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    pharmacies: Paginated<PharmacyRow>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

const refresh = () => {
    router.get(route('platform.pharmacies.index'), { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true });
};
debouncedWatch(search, refresh, { debounce: 400 });

const onPage = (e: { page: number }) => {
    router.get(route('platform.pharmacies.index'), { search: search.value || undefined, page: e.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head title="약국 관리 (공유)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-2xl font-bold">약국 관리 <span class="text-base font-normal text-surface-500">(공유 마스터)</span></h1>
                <p class="text-surface-500 mt-1 text-sm">전 제약사 공용 약국 마스터 — 전체 {{ pharmacies.total }}건 · 등록/수정은 다음 단계</p>
            </div>

            <InputText v-model="search" placeholder="약국명·코드·사업자번호 검색" class="w-full md:w-[28rem]" />

            <DataTable :value="pharmacies.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 약국이 없습니다.</div>
                </template>
                <Column header="약국명">
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.pharmacy_name }}</span>
                        <div v-if="data.pharmacy_code" class="text-xs text-surface-400 mt-1">{{ data.pharmacy_code }}</div>
                    </template>
                </Column>
                <Column header="사업자번호" style="width: 150px">
                    <template #body="{ data }">{{ data.business_registration_number ?? '-' }}</template>
                </Column>
                <Column header="대표" style="width: 130px">
                    <template #body="{ data }">{{ data.representative_name ?? '-' }}</template>
                </Column>
                <Column header="상태" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="data.status === 'active' ? '활성' : '비활성'" :severity="data.status === 'active' ? 'success' : 'secondary'" />
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="pharmacies.per_page" :total-records="pharmacies.total"
                       :first="(pharmacies.current_page - 1) * pharmacies.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
