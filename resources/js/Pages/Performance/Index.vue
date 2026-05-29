<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanySearchAutoComplete from '@/Pages/Products/Partials/CompanySearchAutoComplete.vue';
import ProductSearchAutoComplete from '@/Pages/Products/Partials/ProductSearchAutoComplete.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

type Status = 'draft' | 'submitted' | 'reviewed' | 'approved' | 'rejected' | 'cancelled';

interface PerformanceRow {
    id: number;
    performance_no: string;
    performance_date: string;
    company: { id: number; company_name: string };
    product: { id: number; product_name: string; insurance_code: string };
    quantity: number;
    unit_price: string | number;
    subtotal: string | number | null;
    commission_rate: string | number | null;
    commission_amount: string | number | null;
    status: Status;
    creator: { id: number; name: string } | null;
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
    performances: Paginated<PerformanceRow>;
    filters: {
        status: string | null;
        from: string | null;
        to: string | null;
        company_id: number | null;
        product_id: number | null;
    };
    statuses: Status[];
    can: { create: boolean; viewCreator: boolean };
}>();

const status = ref<string | null>(props.filters.status);
const from = ref<Date | null>(props.filters.from ? new Date(props.filters.from) : null);
const to = ref<Date | null>(props.filters.to ? new Date(props.filters.to) : null);
const companyId = ref<number | null>(props.filters.company_id);
const productId = ref<number | null>(props.filters.product_id);

const statusOptions: { label: string; value: string | null }[] = [
    { label: '전체', value: null },
    { label: '초안', value: 'draft' },
    { label: '제출됨', value: 'submitted' },
    { label: '검수됨', value: 'reviewed' },
    { label: '승인됨', value: 'approved' },
    { label: '반려', value: 'rejected' },
    { label: '취소', value: 'cancelled' },
];

const statusSeverity: Record<Status, 'info' | 'warn' | 'success' | 'secondary' | 'danger' | 'contrast'> = {
    draft: 'secondary',
    submitted: 'info',
    reviewed: 'warn',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'contrast',
};

const statusLabel: Record<Status, string> = {
    draft: '초안',
    submitted: '제출됨',
    reviewed: '검수됨',
    approved: '승인됨',
    rejected: '반려',
    cancelled: '취소',
};

const toDateString = (d: Date | null): string | null => {
    if (!d) return null;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const filterQuery = () => ({
    status: status.value ?? undefined,
    from: toDateString(from.value) ?? undefined,
    to: toDateString(to.value) ?? undefined,
    company_id: companyId.value ?? undefined,
    product_id: productId.value ?? undefined,
});

const applyFilter = () => {
    router.get(route('performance.index'), filterQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilter = () => {
    status.value = null;
    from.value = null;
    to.value = null;
    companyId.value = null;
    productId.value = null;
    router.get(route('performance.index'));
};

const onPage = (event: { page: number }) => {
    router.get(
        route('performance.index'),
        { page: event.page + 1, ...filterQuery() },
        { preserveState: true, preserveScroll: true },
    );
};

const fmt = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'number' ? v : parseFloat(v);
    if (Number.isNaN(n)) return '-';
    return n.toLocaleString('ko-KR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};
</script>

<template>
    <Head title="실적 관리" />
    <AdminLayout>
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold">실적 목록</h1>
                    <p class="text-surface-500 mt-1 text-sm">
                        전체 {{ performances.total }}건 — 실적은 draft → submitted → reviewed → approved 순서로 상태 전이합니다.
                    </p>
                </div>
                <div v-if="can.create" class="flex gap-2">
                    <Link :href="route('performance.import.form')">
                        <Button icon="pi pi-upload" label="CSV 일괄 등록" severity="secondary" outlined />
                    </Link>
                    <Link :href="route('performance.create')">
                        <Button icon="pi pi-plus-circle" label="실적 등록" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:flex-wrap gap-3 mb-3">
                <Select
                    v-model="status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="상태"
                    class="w-full md:w-[220px] shrink-0"
                    show-clear
                />
                <DatePicker
                    v-model="from"
                    placeholder="시작일"
                    date-format="yy-mm-dd"
                    show-icon
                    class="w-full md:w-[220px] shrink-0"
                />
                <DatePicker
                    v-model="to"
                    placeholder="종료일"
                    date-format="yy-mm-dd"
                    show-icon
                    class="w-full md:w-[220px] shrink-0"
                />
            </div>
            <div class="flex flex-col md:flex-row md:flex-wrap gap-3 mb-4">
                <div class="w-full md:w-[448px] shrink-0">
                    <label class="block text-base font-semibold text-surface-700 mb-1">거래처</label>
                    <CompanySearchAutoComplete
                        v-model="companyId"
                        container-class="md:!w-[448px]"
                        input-class="p-inputtext p-component w-full md:w-[28rem]"
                    />
                </div>
                <div class="w-full md:w-[448px] shrink-0">
                    <label class="block text-base font-semibold text-surface-700 mb-1">제품</label>
                    <ProductSearchAutoComplete
                        v-model="productId"
                        container-class="md:!w-[448px]"
                        input-class="p-inputtext p-component w-full md:w-[28rem]"
                    />
                </div>
                <div class="flex items-end gap-2 w-full md:w-auto">
                    <Button icon="pi pi-filter" label="검색" severity="info" @click="applyFilter" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilter" />
                </div>
            </div>

            <DataTable :value="performances.data" :striped-rows="true" data-key="id">
                <Column field="performance_no" header="실적번호" class="min-w-[140px]">
                    <template #body="{ data }">
                        <Link :href="route('performance.show', data.id)" class="text-primary-600 hover:underline font-medium">
                            {{ data.performance_no }}
                        </Link>
                    </template>
                </Column>
                <Column field="performance_date" header="실적일" class="min-w-[110px]" />
                <Column field="company.company_name" header="거래처" class="min-w-[160px]">
                    <template #body="{ data }">
                        <span>{{ data.company?.company_name ?? '-' }}</span>
                    </template>
                </Column>
                <Column field="product.product_name" header="제품" class="min-w-[180px]">
                    <template #body="{ data }">
                        <div class="flex flex-col">
                            <span>{{ data.product?.product_name ?? '-' }}</span>
                            <span class="text-xs text-surface-400">{{ data.product?.insurance_code }}</span>
                        </div>
                    </template>
                </Column>
                <Column field="quantity" header="수량" class="text-right">
                    <template #body="{ data }">{{ fmt(data.quantity) }}</template>
                </Column>
                <Column field="unit_price" header="단가" class="text-right">
                    <template #body="{ data }">{{ fmt(data.unit_price) }}원</template>
                </Column>
                <Column field="subtotal" header="매출" class="text-right">
                    <template #body="{ data }">{{ fmt(data.subtotal) }}원</template>
                </Column>
                <Column field="commission_amount" header="수수료" class="text-right">
                    <template #body="{ data }">
                        <span v-if="data.commission_amount !== null">{{ fmt(data.commission_amount) }}원</span>
                        <span v-else class="text-surface-400">-</span>
                    </template>
                </Column>
                <Column field="status" header="상태" class="min-w-[90px]">
                    <template #body="{ data }">
                        <Tag :value="statusLabel[data.status as Status]" :severity="statusSeverity[data.status as Status]" />
                    </template>
                </Column>
                <Column v-if="can.viewCreator" field="creator.name" header="등록자" class="min-w-[90px]">
                    <template #body="{ data }">{{ data.creator?.name ?? '-' }}</template>
                </Column>
                <template #empty>
                    <div class="py-8 text-center text-surface-500">등록된 실적이 없습니다.</div>
                </template>
            </DataTable>

            <Paginator v-if="performances.last_page > 1"
                       :rows="performances.per_page"
                       :total-records="performances.total"
                       :first="(performances.current_page - 1) * performances.per_page"
                       @page="onPage" />
        </div>
    </AdminLayout>
</template>
