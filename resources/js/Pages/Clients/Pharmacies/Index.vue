<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBusinessNumber } from '@/utils/format';
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

interface Pharmacy {
    id: number;
    pharmacy_code: string | null;
    pharmacy_name: string;
    business_registration_number: string | null;
    representative_name: string | null;
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
    pharmacies: Paginated<Pharmacy>;
    filters: { search: string; status: string | null };
    can: { create: boolean };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? null);

const statusOptions = [
    { label: '전체', value: null },
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];

const refresh = () => {
    router.get(
        route('pharmacies.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, refresh, { debounce: 400 });

const resetFilters = () => {
    search.value = '';
    status.value = null;
    refresh();
};

const onPage = (e: { page: number }) => {
    router.get(
        route('pharmacies.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            page: e.page + 1,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('ko-KR', { dateStyle: 'short' }).format(new Date(iso));
</script>

<template>
    <Head title="약국 관리" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">약국 관리</h1>
                    <p class="text-surface-500 mt-1 text-sm">전체 {{ pharmacies.total }}건</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="can.create" :href="route('pharmacies.import.form')">
                        <Button label="CSV 일괄 등록" icon="pi pi-upload" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.create" :href="route('pharmacies.create')">
                        <Button label="약국 등록" icon="pi pi-plus" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:flex-wrap gap-3">
                <InputText v-model="search" placeholder="약국명·코드·사업자번호·담당자 검색" class="w-full md:w-[28rem]" />
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

            <DataTable :value="pharmacies.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 약국이 없습니다.</div>
                </template>
                <Column header="약국명">
                    <template #body="{ data }">
                        <Link :href="route('pharmacies.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.pharmacy_name }}
                        </Link>
                        <div v-if="data.pharmacy_code" class="text-xs text-surface-400 mt-1">{{ data.pharmacy_code }}</div>
                    </template>
                </Column>
                <Column header="사업자번호" body-class="text-center" style="width: 150px">
                    <template #body="{ data }">{{ formatBusinessNumber(data.business_registration_number) }}</template>
                </Column>
                <Column header="대표" style="width: 120px">
                    <template #body="{ data }">{{ data.representative_name ?? '-' }}</template>
                </Column>
                <Column header="담당자" style="width: 160px">
                    <template #body="{ data }">
                        <div class="text-sm">{{ data.contact_person_name ?? '-' }}</div>
                        <div v-if="data.contact_phone" class="text-xs text-surface-500">{{ data.contact_phone }}</div>
                    </template>
                </Column>
                <Column header="상태" body-class="text-center" style="width: 80px">
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

            <Paginator :rows="pharmacies.per_page" :total-records="pharmacies.total"
                       :first="(pharmacies.current_page - 1) * pharmacies.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
