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

interface Hospital {
    id: number;
    hospital_code: string | null;
    hospital_name: string;
    business_registration_number: string | null;
    hospital_type: string | null;
    specialty: string | null;
    contact_person_name: string | null;
    contact_phone: string | null;
    status: 'active' | 'inactive';
    created_at: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    hospitals: Paginated<Hospital>;
    filters: { search: string; status: string | null; type: string | null };
    types: string[];
    can: { create: boolean };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? null);
const type = ref(props.filters.type ?? null);

const typeLabels: Record<string, string> = {
    general_hospital: '종합병원',
    hospital: '병원',
    clinic: '의원',
    dental: '치과',
    oriental: '한의원',
    other: '기타',
};

const statusOptions = [
    { label: '전체', value: null },
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];

const typeOptions = [
    { label: '전체', value: null },
    ...props.types.map((t) => ({ label: typeLabels[t] ?? t, value: t })),
];

const refresh = () => {
    router.get(
        route('hospitals.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            type: type.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, refresh, { debounce: 400 });

const resetFilters = () => {
    search.value = '';
    type.value = null;
    status.value = null;
    refresh();
};

const onPage = (e: { page: number }) => {
    router.get(
        route('hospitals.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            type: type.value || undefined,
            page: e.page + 1,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('ko-KR', { dateStyle: 'short' }).format(new Date(iso));
</script>

<template>
    <Head title="병의원 관리" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">병의원 관리</h1>
                    <p class="text-surface-500 mt-1 text-sm">전체 {{ hospitals.total }}건</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="can.create" :href="route('hospitals.import.form')">
                        <Button label="CSV 일괄 등록" icon="pi pi-upload" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.create" :href="route('hospitals.create')">
                        <Button label="병의원 등록" icon="pi pi-plus" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:flex-wrap gap-3">
                <InputText v-model="search" placeholder="병의원명·코드·사업자번호·담당자 검색" class="w-full md:w-[28rem]" />
                <Select
                    v-model="type"
                    :options="typeOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="유형"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <Select
                    v-model="status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="상태"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <div class="flex items-center gap-2 w-full md:w-auto md:ml-auto">
                    <Button icon="pi pi-filter" label="검색" severity="info" @click="refresh" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilters" />
                </div>
            </div>

            <DataTable :value="hospitals.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 병의원이 없습니다.</div>
                </template>
                <Column header="병의원명">
                    <template #body="{ data }">
                        <Link :href="route('hospitals.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.hospital_name }}
                        </Link>
                        <div v-if="data.hospital_code" class="text-xs text-surface-400 mt-1">{{ data.hospital_code }}</div>
                    </template>
                </Column>
                <Column header="유형" style="width: 100px">
                    <template #body="{ data }">
                        <Tag v-if="data.hospital_type" :value="typeLabels[data.hospital_type]" severity="info" />
                        <span v-else class="text-xs text-surface-400">-</span>
                    </template>
                </Column>
                <Column header="전문분야" style="width: 120px">
                    <template #body="{ data }">{{ data.specialty ?? '-' }}</template>
                </Column>
                <Column header="사업자번호" style="width: 150px">
                    <template #body="{ data }">{{ data.business_registration_number ?? '-' }}</template>
                </Column>
                <Column header="담당자" style="width: 160px">
                    <template #body="{ data }">
                        <div class="text-sm">{{ data.contact_person_name ?? '-' }}</div>
                        <div v-if="data.contact_phone" class="text-xs text-surface-500">{{ data.contact_phone }}</div>
                    </template>
                </Column>
                <Column header="상태" style="width: 80px">
                    <template #body="{ data }">
                        <Tag :value="data.status === 'active' ? '활성' : '비활성'"
                             :severity="data.status === 'active' ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column header="등록일" style="width: 110px">
                    <template #body="{ data }">
                        <span class="text-sm text-surface-500">{{ formatDate(data.created_at) }}</span>
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="hospitals.per_page" :total-records="hospitals.total"
                       :first="(hospitals.current_page - 1) * hospitals.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
