<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';

interface StatementLine {
    id: number;
    performance_no: string;
    performance_date: string | null;
    company_name: string | null;
    product_name: string | null;
    insurance_code: string | null;
    quantity: number;
    unit_price: string;
    subtotal: string;
    commission_rate: string | null;
    commission_amount: string | null;
}

const props = defineProps<{
    targetUser: { id: number; name: string; email: string };
    lines: StatementLine[];
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
        route('commission-summary.statement', props.targetUser.id),
        { month: monthInput.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const applyRange = () => {
    router.get(
        route('commission-summary.statement', props.targetUser.id),
        { from: fromInput.value, to: toInput.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const pdfHref = () => {
    const params = new URLSearchParams();
    if (props.filters.month) {
        params.set('month', props.filters.month);
    } else {
        params.set('from', props.filters.from);
        params.set('to', props.filters.to);
    }
    return `${route('commission-summary.statement.pdf', props.targetUser.id)}?${params.toString()}`;
};

const n = (v: number) => new Intl.NumberFormat('ko-KR').format(v);
const currency = (v: number) => `${n(Math.round(v))}원`;
</script>

<template>
    <Head :title="`수수료 명세 — ${targetUser.name}`" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">수수료 명세 — {{ targetUser.name }}</h1>
                    <p class="text-surface-500 mt-1 text-sm">
                        {{ targetUser.email }} · 기간 {{ filters.from }} ~ {{ filters.to }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <a :href="pdfHref()" target="_blank" rel="noopener">
                        <Button label="PDF 다운로드" icon="pi pi-file-pdf" severity="danger" />
                    </a>
                    <Link :href="route('commission-summary.index')">
                        <Button label="전체 합계로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                </div>
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
            </div>

            <!-- 합계 카드 -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">실적 건수</div>
                        <div class="text-2xl font-semibold mt-2">{{ n(totals.line_count) }}건</div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">수량 합계</div>
                        <div class="text-2xl font-semibold mt-2">{{ n(totals.total_quantity) }}</div>
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

            <!-- 라인 -->
            <DataTable :value="lines" stripedRows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">기간 내 승인된 실적이 없습니다.</div>
                </template>
                <Column header="실적번호" field="performance_no" style="width: 130px" />
                <Column header="일자" field="performance_date" style="width: 110px" />
                <Column header="거래처" field="company_name" />
                <Column header="제품" field="product_name" />
                <Column header="보험코드" field="insurance_code" style="width: 110px" />
                <Column header="수량" style="width: 80px; text-align: right">
                    <template #body="{ data }">
                        <span class="tabular-nums">{{ n(data.quantity) }}</span>
                    </template>
                </Column>
                <Column header="단가" style="width: 110px; text-align: right">
                    <template #body="{ data }">
                        <span class="tabular-nums">{{ n(Number(data.unit_price)) }}</span>
                    </template>
                </Column>
                <Column header="매출" style="width: 130px; text-align: right">
                    <template #body="{ data }">
                        <span class="tabular-nums">{{ n(Number(data.subtotal)) }}</span>
                    </template>
                </Column>
                <Column header="수수료율" style="width: 90px; text-align: right">
                    <template #body="{ data }">
                        <span v-if="data.commission_rate !== null" class="tabular-nums">
                            {{ Number(data.commission_rate).toFixed(2) }}%
                        </span>
                        <span v-else class="text-surface-400">-</span>
                    </template>
                </Column>
                <Column header="수수료" style="width: 130px; text-align: right">
                    <template #body="{ data }">
                        <span v-if="data.commission_amount !== null" class="tabular-nums font-semibold">
                            {{ n(Number(data.commission_amount)) }}
                        </span>
                        <span v-else class="text-surface-400">-</span>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AdminLayout>
</template>
