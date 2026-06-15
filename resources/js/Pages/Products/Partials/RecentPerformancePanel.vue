<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';

interface CompanyRef {
    id: number;
    company_name: string;
    default_commission_grade: string | null;
}

interface PerformanceRow {
    id: number;
    performance_no: string;
    performance_date: string | null;
    company: CompanyRef | null;
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
    commission_rate: string | number | null;
    commission_amount: string | number | null;
    status: string;
}

defineProps<{
    rows: PerformanceRow[];
}>();

const statusLabel = (s: string) =>
    ({
        draft: '초안',
        submitted: '제출',
        reviewed: '검수',
        approved: '승인',
        rejected: '반려',
        cancelled: '취소',
    }[s] ?? s);

const statusSeverity = (s: string) =>
    ({
        draft: 'secondary',
        submitted: 'warn',
        reviewed: 'info',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'contrast',
    }[s] ?? 'secondary');

const formatNumber = (n: string | number | null) => {
    if (n === null || n === undefined || n === '') return '-';
    const v = typeof n === 'string' ? parseFloat(n) : n;
    return new Intl.NumberFormat('ko-KR').format(v);
};

const formatPrice = (n: string | number | null) => (n === null ? '-' : formatNumber(n) + '원');

const formatPercent = (n: string | number | null) => {
    if (n === null || n === undefined || n === '') return '-';
    const v = typeof n === 'string' ? parseFloat(n) : n;
    return `${v.toFixed(2)}%`;
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">
                최근 실적 ({{ rows.length }}건)
            </h3>
            <Link href="/performance" class="text-sm text-primary hover:underline">
                전체 실적 →
            </Link>
        </div>

        <DataTable :value="rows" stripedRows responsiveLayout="scroll" size="small">
            <template #empty>
                <div class="text-center py-6 text-surface-500">
                    이 제품의 실적이 아직 없습니다.
                </div>
            </template>

            <Column header="실적번호" style="width: 150px">
                <template #body="{ data }">
                    <Link :href="`/performance/${data.id}`" class="font-mono text-sm text-primary hover:underline">
                        {{ data.performance_no }}
                    </Link>
                </template>
            </Column>

            <Column header="일자" field="performance_date" style="width: 110px">
                <template #body="{ data }">
                    <span class="text-sm">{{ data.performance_date ?? '-' }}</span>
                </template>
            </Column>

            <Column header="거래처">
                <template #body="{ data }">
                    <div class="text-sm">{{ data.company?.company_name ?? '-' }}</div>
                    <div v-if="data.company?.default_commission_grade" class="text-xs text-surface-500">
                        등급 {{ data.company.default_commission_grade.toUpperCase() }}
                    </div>
                </template>
            </Column>

            <Column header="수량" style="width: 80px; text-align: right">
                <template #body="{ data }">{{ formatNumber(data.quantity) }}</template>
            </Column>

            <Column header="단가" style="width: 110px; text-align: right">
                <template #body="{ data }">{{ formatPrice(data.unit_price) }}</template>
            </Column>

            <Column header="소계" style="width: 130px; text-align: right">
                <template #body="{ data }">
                    <span class="font-semibold">{{ formatPrice(data.subtotal) }}</span>
                </template>
            </Column>

            <Column header="수수료율" style="width: 90px; text-align: right">
                <template #body="{ data }">{{ formatPercent(data.commission_rate) }}</template>
            </Column>

            <Column header="수수료" style="width: 110px; text-align: right">
                <template #body="{ data }">{{ formatPrice(data.commission_amount) }}</template>
            </Column>

            <Column header="상태" body-class="text-center" style="width: 80px">
                <template #body="{ data }">
                    <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
