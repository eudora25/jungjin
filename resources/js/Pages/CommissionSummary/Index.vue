<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';

interface SummaryRow {
    user_id: number;
    user_name: string;
    line_count: number;
    total_quantity: number;
    total_subtotal: number;
    total_commission: number;
}

const props = defineProps<{
    rows: SummaryRow[];
    totals: {
        line_count: number;
        total_quantity: number;
        total_subtotal: number;
        total_commission: number;
    };
    filters: {
        from: string;
        to: string;
        month: string | null;
    };
}>();

const monthInput = ref<string>(props.filters.month ?? '');
const fromInput = ref<string>(props.filters.from);
const toInput = ref<string>(props.filters.to);

const applyMonth = () => {
    router.get(
        route('commission-summary.index'),
        { month: monthInput.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const applyRange = () => {
    router.get(
        route('commission-summary.index'),
        { from: fromInput.value, to: toInput.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const resetFilters = () => {
    monthInput.value = '';
    fromInput.value = '';
    toInput.value = '';
    router.get(route('commission-summary.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
};

const exportHref = () => {
    const params = new URLSearchParams();
    if (props.filters.month) {
        params.set('month', props.filters.month);
    } else {
        params.set('from', props.filters.from);
        params.set('to', props.filters.to);
    }
    return `${route('commission-summary.export.excel')}?${params.toString()}`;
};

const statementHref = (userId: number) => {
    const params = new URLSearchParams();
    if (props.filters.month) {
        params.set('month', props.filters.month);
    } else {
        params.set('from', props.filters.from);
        params.set('to', props.filters.to);
    }
    return `${route('commission-summary.statement', userId)}?${params.toString()}`;
};

const n = (v: number) => new Intl.NumberFormat('ko-KR').format(v);
const currency = (v: number) => `${n(Math.round(v))}원`;
</script>

<template>
    <Head title="수수료 명세" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">영업사원별 수수료 명세</h1>
                    <p class="text-surface-500 mt-1 text-sm">
                        승인된 실적 기준 — 기간 {{ filters.from }} ~ {{ filters.to }}
                    </p>
                </div>
                <a :href="exportHref()" target="_blank" rel="noopener">
                    <Button label="Excel 다운로드" icon="pi pi-file-excel" severity="success" />
                </a>
            </div>

            <!-- 필터 -->
            <div class="flex flex-col md:flex-row md:flex-wrap gap-3 items-end">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-surface-500">월(YYYY-MM)</label>
                    <InputText v-model="monthInput" placeholder="2026-04" class="w-40" @keyup.enter="applyMonth" />
                </div>
                <Button label="월로 조회" icon="pi pi-search" @click="applyMonth" />

                <div class="w-px h-8 bg-surface-200 mx-2 hidden md:block" />

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-surface-500">시작일</label>
                    <InputText v-model="fromInput" placeholder="2026-04-01" class="w-40" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-surface-500">종료일</label>
                    <InputText v-model="toInput" placeholder="2026-04-30" class="w-40" />
                </div>
                <Button label="기간으로 조회" icon="pi pi-search" severity="info" @click="applyRange" />
                <Button label="초기화" icon="pi pi-times" severity="secondary" outlined @click="resetFilters" />
            </div>

            <!-- 합계 카드 -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">대상자</div>
                        <div class="text-2xl font-semibold mt-2">{{ n(rows.length) }}명</div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">실적 건수</div>
                        <div class="text-2xl font-semibold mt-2">{{ n(totals.line_count) }}건</div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">매출 합계</div>
                        <div class="text-2xl font-semibold mt-2">{{ currency(totals.total_subtotal) }}</div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">수수료 합계</div>
                        <div class="text-2xl font-semibold mt-2">{{ currency(totals.total_commission) }}</div>
                    </template>
                </Card>
            </div>

            <!-- 목록 -->
            <DataTable :value="rows" stripedRows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">기간 내 승인된 실적이 없습니다.</div>
                </template>
                <Column header="영업사원" field="user_name" style="min-width: 140px" />
                <Column header="실적 건수" style="width: 110px; text-align: right">
                    <template #body="{ data }">
                        <span class="tabular-nums">{{ n(data.line_count) }}</span>
                    </template>
                </Column>
                <Column header="수량 합계" style="width: 110px; text-align: right">
                    <template #body="{ data }">
                        <span class="tabular-nums">{{ n(data.total_quantity) }}</span>
                    </template>
                </Column>
                <Column header="매출 합계" style="width: 160px; text-align: right">
                    <template #body="{ data }">
                        <span class="tabular-nums">{{ currency(data.total_subtotal) }}</span>
                    </template>
                </Column>
                <Column header="수수료 합계" style="width: 160px; text-align: right">
                    <template #body="{ data }">
                        <span class="tabular-nums font-semibold">{{ currency(data.total_commission) }}</span>
                    </template>
                </Column>
                <Column header="" style="width: 110px">
                    <template #body="{ data }">
                        <Link :href="statementHref(data.user_id)">
                            <Button label="명세" icon="pi pi-file" size="small" text />
                        </Link>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AdminLayout>
</template>
