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

interface Company {
    id: number;
    company_name: string;
    business_registration_number: string | null;
    representative_name: string | null;
    company_group: string | null;
    contact_person_name: string | null;
    mobile_phone: string | null;
    default_commission_grade: string | null;
    status: 'active' | 'inactive';
    approval_status: 'pending' | 'approved' | 'rejected';
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    companies: Paginator<Company>;
    filters: { search: string; status: string | null; approval_status: string | null };
    can: { create: boolean };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? null);
const approvalStatus = ref(props.filters.approval_status ?? null);

const statusOptions = [
    { label: '전체', value: null },
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];

const approvalOptions = [
    { label: '전체', value: null },
    { label: '대기', value: 'pending' },
    { label: '승인', value: 'approved' },
    { label: '반려', value: 'rejected' },
];

const gradeSeverity = (g: string | null) => {
    switch (g) {
        case 'A':
            return 'success';
        case 'B':
            return 'info';
        case 'C':
            return 'warn';
        case 'D':
        case 'E':
            return 'danger';
        default:
            return 'secondary';
    }
};

const approvalLabel = (s: string) => ({ pending: '대기', approved: '승인', rejected: '반려' }[s] ?? s);
const approvalSeverity = (s: string) =>
    ({ pending: 'warn', approved: 'success', rejected: 'danger' }[s] ?? 'secondary');

const refresh = () => {
    router.get(
        route('companies.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            approval_status: approvalStatus.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, refresh, { debounce: 400 });

const resetFilters = () => {
    search.value = '';
    status.value = null;
    approvalStatus.value = null;
    refresh();
};

const onPage = (event: { page: number }) => {
    router.get(
        route('companies.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            approval_status: approvalStatus.value || undefined,
            page: event.page + 1,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('ko-KR', { dateStyle: 'short' }).format(new Date(iso));
</script>

<template>
    <Head title="업체 관리" />

    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">업체 관리</h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">거래처 업체 정보 관리. 전체 {{ companies.total }}건</p>
                </div>
                <Link v-if="can.create" :href="route('companies.create')">
                    <Button label="업체 등록" icon="pi pi-plus" />
                </Link>
            </div>

            <div class="flex flex-col md:flex-row md:flex-wrap gap-3">
                <InputText v-model="search" placeholder="업체명·사업자번호·담당자 검색" class="w-full md:w-[28rem]" />
                <Select
                    v-model="status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="상태"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <Select
                    v-model="approvalStatus"
                    :options="approvalOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="승인 상태"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <div class="flex items-center gap-2 w-full md:w-auto md:ml-auto">
                    <Button icon="pi pi-filter" label="검색" severity="info" @click="refresh" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilters" />
                </div>
            </div>

            <DataTable :value="companies.data" stripedRows responsiveLayout="scroll">
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 업체가 없습니다.</div>
                </template>

                <Column header="업체명">
                    <template #body="{ data }">
                        <Link
                            :href="route('companies.show', data.id)"
                            class="font-medium text-surface-900 dark:text-surface-0 hover:text-primary"
                        >
                            {{ data.company_name }}
                        </Link>
                        <div v-if="data.company_group" class="text-xs text-surface-500 mt-1">{{ data.company_group }}</div>
                    </template>
                </Column>

                <Column header="사업자번호" field="business_registration_number" style="width: 150px">
                    <template #body="{ data }">
                        <span class="text-sm">{{ data.business_registration_number ?? '-' }}</span>
                    </template>
                </Column>

                <Column header="대표" field="representative_name" style="width: 120px">
                    <template #body="{ data }">
                        <span class="text-sm">{{ data.representative_name ?? '-' }}</span>
                    </template>
                </Column>

                <Column header="담당자" style="width: 160px">
                    <template #body="{ data }">
                        <div class="text-sm">{{ data.contact_person_name ?? '-' }}</div>
                        <div v-if="data.mobile_phone" class="text-xs text-surface-500">{{ data.mobile_phone }}</div>
                    </template>
                </Column>

                <Column header="등급" style="width: 80px; text-align: center">
                    <template #body="{ data }">
                        <Tag
                            v-if="data.default_commission_grade"
                            :value="data.default_commission_grade"
                            :severity="gradeSeverity(data.default_commission_grade)"
                        />
                        <span v-else class="text-xs text-surface-400">-</span>
                    </template>
                </Column>

                <Column header="승인" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="approvalLabel(data.approval_status)" :severity="approvalSeverity(data.approval_status)" />
                    </template>
                </Column>

                <Column header="상태" style="width: 80px">
                    <template #body="{ data }">
                        <Tag
                            :value="data.status === 'active' ? '활성' : '비활성'"
                            :severity="data.status === 'active' ? 'success' : 'secondary'"
                        />
                    </template>
                </Column>

                <Column header="등록일" style="width: 110px">
                    <template #body="{ data }">
                        <span class="text-sm text-surface-500">{{ formatDate(data.created_at) }}</span>
                    </template>
                </Column>
            </DataTable>

            <Paginator
                :rows="companies.per_page"
                :totalRecords="companies.total"
                :first="(companies.current_page - 1) * companies.per_page"
                @page="onPage"
            />
        </div>
    </AdminLayout>
</template>
