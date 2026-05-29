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
import Tag from 'primevue/tag';

interface HospitalRow {
    id: number;
    hospital_name: string;
    hospital_code: string | null;
    hospital_type: string | null;
    business_registration_number: string | null;
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
    hospitals: Paginated<HospitalRow>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

const typeLabels: Record<string, string> = {
    general_hospital: '종합병원', hospital: '병원', clinic: '의원', dental: '치과', oriental: '한의원', other: '기타',
};

const refresh = () => {
    router.get(route('platform.hospitals.index'), { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true });
};
debouncedWatch(search, refresh, { debounce: 400 });

const onPage = (e: { page: number }) => {
    router.get(route('platform.hospitals.index'), { search: search.value || undefined, page: e.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head title="병의원 관리 (공유)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">병의원 관리 <span class="text-base font-normal text-surface-500">(공유 마스터)</span></h1>
                    <p class="text-surface-500 mt-1 text-sm">전 제약사 공용 병의원 마스터 — 전체 {{ hospitals.total }}건</p>
                </div>
                <Link :href="route('platform.hospitals.create')">
                    <Button label="병의원 등록" icon="pi pi-plus" />
                </Link>
            </div>

            <InputText v-model="search" placeholder="병의원명·코드·사업자번호 검색" class="w-full md:w-[28rem]" />

            <DataTable :value="hospitals.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 병의원이 없습니다.</div>
                </template>
                <Column header="병의원명">
                    <template #body="{ data }">
                        <Link :href="route('platform.hospitals.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.hospital_name }}
                        </Link>
                        <div v-if="data.hospital_code" class="text-xs text-surface-400 mt-1">{{ data.hospital_code }}</div>
                    </template>
                </Column>
                <Column header="유형" style="width: 120px">
                    <template #body="{ data }">
                        <Tag v-if="data.hospital_type" :value="typeLabels[data.hospital_type] ?? data.hospital_type" severity="info" />
                        <span v-else>-</span>
                    </template>
                </Column>
                <Column header="사업자번호" style="width: 150px">
                    <template #body="{ data }">{{ data.business_registration_number ?? '-' }}</template>
                </Column>
                <Column header="상태" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="data.status === 'active' ? '활성' : '비활성'" :severity="data.status === 'active' ? 'success' : 'secondary'" />
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="hospitals.per_page" :total-records="hospitals.total"
                       :first="(hospitals.current_page - 1) * hospitals.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
