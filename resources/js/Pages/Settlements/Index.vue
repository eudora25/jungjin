<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanySearchAutoComplete from '@/Pages/Products/Partials/CompanySearchAutoComplete.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';

interface CompanyRef {
    id: number;
    company_name: string;
}

interface SettlementRow {
    id: number;
    settlement_no: string;
    period_month: string;
    status: string;
    line_count: number;
    total_quantity: number;
    total_subtotal: string | number;
    total_commission: string | number;
    calculated_at: string | null;
    company: CompanyRef;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    settlements: Paginated<SettlementRow>;
    filters: { period_month: string | null; company_id: number | null; payment_batch_no: string | null };
    can: { create: boolean };
}>();

const periodMonth = ref(props.filters.period_month ?? '');
const companyId = ref<number | null>(props.filters.company_id);
const paymentBatchNo = ref(props.filters.payment_batch_no ?? '');

const applyFilter = () => {
    router.get(route('settlements.index'), {
        period_month: periodMonth.value || undefined,
        company_id: companyId.value ?? undefined,
        payment_batch_no: paymentBatchNo.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilter = () => {
    periodMonth.value = '';
    companyId.value = null;
    paymentBatchNo.value = '';
    router.get(route('settlements.index'));
};

const onPage = (event: { page: number }) => {
    router.get(route('settlements.index'), {
        page: event.page + 1,
        period_month: periodMonth.value || undefined,
        company_id: companyId.value ?? undefined,
        payment_batch_no: paymentBatchNo.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const form = useForm({
    company_id: null as number | null,
    period_month: '',
});

const submitGenerate = () => {
    form.post(route('settlements.store'), { preserveScroll: true });
};

const fmt = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'number' ? v : parseFloat(String(v));
    if (Number.isNaN(n)) return '-';
    return n.toLocaleString('ko-KR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};

const statusLabel: Record<string, string> = {
    draft: '초안',
    confirmed: '확정',
    paid: '지급완료',
    cancelled: '취소',
};

const statusSeverity: Record<string, 'info' | 'warn' | 'success' | 'secondary' | 'danger' | 'contrast'> = {
    draft: 'secondary',
    confirmed: 'info',
    paid: 'success',
    cancelled: 'contrast',
};
</script>

<template>
    <Head title="정산 관리" />
    <AdminLayout>
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold">정산 관리</h1>
                    <p class="text-surface-500 mt-1 text-sm">
                        승인된 실적만 월·거래처별로 집계합니다. 초안(draft) 정산만 재계산할 수 있습니다.
                    </p>
                </div>
            </div>

            <div v-if="can.create" class="border border-surface-200 rounded-lg p-4 mb-6 bg-surface-50">
                <h2 class="font-semibold mb-3">정산 생성 / 재계산</h2>
                <Message v-if="Object.keys(form.errors).length > 0" severity="error" class="mb-3" :closable="false">
                    {{ form.errors.company_id || form.errors.period_month || '입력을 확인해 주세요.' }}
                </Message>
                <form class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end" @submit.prevent="submitGenerate">
                    <div>
                        <label class="block text-xs text-surface-500 mb-1">거래처 *</label>
                        <CompanySearchAutoComplete v-model="form.company_id" :invalid="!!form.errors.company_id" />
                    </div>
                    <div>
                        <label class="block text-xs text-surface-500 mb-1">정산 월 (YYYY-MM) *</label>
                        <InputText v-model="form.period_month" placeholder="2026-04" class="w-full"
                                   :invalid="!!form.errors.period_month" />
                    </div>
                    <Button type="submit" label="계산 실행" icon="pi pi-calculator" :loading="form.processing" />
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                <div>
                    <label class="block text-xs text-surface-500 mb-1">정산 월</label>
                    <InputText v-model="periodMonth" placeholder="2026-04" class="w-full" @keyup.enter="applyFilter" />
                </div>
                <div>
                    <label class="block text-xs text-surface-500 mb-1">거래처</label>
                    <CompanySearchAutoComplete v-model="companyId" />
                </div>
                <div>
                    <label class="block text-xs text-surface-500 mb-1">지급 묶음(Batch)</label>
                    <InputText v-model="paymentBatchNo" placeholder="예: 2026-04-BATCH-001" class="w-full" @keyup.enter="applyFilter" />
                </div>
                <div class="flex gap-2 items-end">
                    <Button icon="pi pi-filter" label="검색" severity="info" @click="applyFilter" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilter" />
                </div>
            </div>

            <DataTable :value="settlements.data" :striped-rows="true" data-key="id">
                <Column field="settlement_no" header="정산번호" class="min-w-[140px]">
                    <template #body="{ data }">
                        <Link :href="route('settlements.show', data.id)" class="text-primary-600 hover:underline font-medium">
                            {{ data.settlement_no }}
                        </Link>
                    </template>
                </Column>
                <Column field="period_month" header="정산월" />
                <Column field="company.company_name" header="거래처">
                    <template #body="{ data }">{{ data.company?.company_name ?? '-' }}</template>
                </Column>
                <Column field="line_count" header="라인" class="text-right" />
                <Column field="total_subtotal" header="매출 합계" class="text-right">
                    <template #body="{ data }">{{ fmt(data.total_subtotal) }}원</template>
                </Column>
                <Column field="total_commission" header="수수료 합계" class="text-right">
                    <template #body="{ data }">{{ fmt(data.total_commission) }}원</template>
                </Column>
                <Column field="status" header="상태" body-class="text-center">
                    <template #body="{ data }">
                        <Tag :value="statusLabel[data.status] ?? data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                    </template>
                </Column>
                <template #empty>
                    <div class="py-8 text-center text-surface-500">정산 데이터가 없습니다.</div>
                </template>
            </DataTable>

            <Paginator v-if="settlements.last_page > 1"
                       :rows="settlements.per_page"
                       :total-records="settlements.total"
                       :first="(settlements.current_page - 1) * settlements.per_page"
                       @page="onPage" />
        </div>
    </AdminLayout>
</template>
