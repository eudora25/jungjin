<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Chart from 'primevue/chart';
import { computed } from 'vue';

type Status = 'draft' | 'submitted' | 'reviewed' | 'approved' | 'rejected' | 'cancelled';
type SettlementStatus = 'draft' | 'confirmed' | 'paid' | 'cancelled';

interface RecentPerformance {
    id: number;
    performance_no: string;
    performance_date: string | null;
    company_name: string | null;
    product_name: string | null;
    quantity: number;
    unit_price: string;
    subtotal: string;
    status: Status;
}

interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: string;
}

const props = defineProps<{
    month: string; // YYYY-MM
    statusCounts: Record<Status, number>;
    thisMonthSubtotal: number;
    thisMonthCommission: number;
    settlementSummary: {
        companyCount: number;
        statusCounts: Record<SettlementStatus, number>;
        totalSubtotal: number;
        totalCommission: number;
    };
    recentSettlements: Array<{
        id: number;
        settlement_no: string;
        company_name: string | null;
        status: SettlementStatus;
        total_subtotal: string;
        total_commission: string;
    }>;
    recentPerformances: RecentPerformance[];
    myAssignedCompanies: Array<{
        id: number;
        company_name: string;
        default_commission_grade: string | null;
    }>;
    myAssignedCompanyTotal: number;
    myMonthlyChart: {
        labels: string[];
        data: number[];
    };
    recentRejected: Array<{
        id: number;
        performance_no: string;
        performance_date: string | null;
        company_name: string | null;
        product_name: string | null;
        subtotal: string;
        rejected_reason: string | null;
        updated_at: string | null;
    }>;
}>();

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const authUserId = (page.props.auth as { user: AuthUser })?.user?.id;

const n = (v: number) => new Intl.NumberFormat('ko-KR').format(v);
const currency = (v: number) => `${n(Math.round(v))}원`;

const statusLabel = (s: Status) =>
    ({
        draft: '임시저장',
        submitted: '제출',
        reviewed: '검수',
        approved: '승인',
        rejected: '반려',
        cancelled: '취소',
    })[s];

const statusSeverity = (s: Status) =>
    ({
        draft: 'secondary',
        submitted: 'info',
        reviewed: 'warn',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'secondary',
    })[s] as any;

const settlementStatusLabel = (s: SettlementStatus) =>
    ({
        draft: '작성중',
        confirmed: '확정',
        paid: '지급완료',
        cancelled: '취소',
    })[s];

const settlementStatusSeverity = (s: SettlementStatus) =>
    ({
        draft: 'secondary',
        confirmed: 'info',
        paid: 'success',
        cancelled: 'danger',
    })[s] as any;

// P2-8-3: 월별 실적 추이 차트
const chartData = computed(() => ({
    labels: props.myMonthlyChart.labels,
    datasets: [
        {
            label: '월별 승인 실적(소계)',
            data: props.myMonthlyChart.data,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.12)',
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 5,
            fill: true,
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: { parsed: { y: number | null } }) =>
                    ctx.parsed.y === null ? '-' : currency(ctx.parsed.y),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (v: number) => n(v),
            },
        },
        x: {
            ticks: { autoSkip: true, maxTicksLimit: 12 },
        },
    },
}));

const hasChartData = computed(() => props.myMonthlyChart.data.some((v) => v > 0));
</script>

<template>
    <Head title="영업 대시보드" />
    <AdminLayout>
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">영업 대시보드</h1>
                    <p class="text-surface-500 mt-1 text-sm">내 실적 현황을 빠르게 확인합니다. ({{ month }})</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('performance.create')">
                        <Button label="실적 등록" icon="pi pi-plus-circle" />
                    </Link>
                    <Link :href="route('performance.index')">
                        <Button label="실적 목록" icon="pi pi-list" severity="secondary" outlined />
                    </Link>
                    <Link :href="route('performance.index', { status: 'draft' })">
                        <Button
                            :label="`제출 대기 ${n(statusCounts.draft)}`"
                            icon="pi pi-pencil"
                            severity="warn"
                            outlined
                            :badge="statusCounts.draft > 0 ? String(statusCounts.draft) : undefined"
                        />
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">이번달 실적(소계)</div>
                        <div class="text-2xl font-semibold mt-2">{{ currency(thisMonthSubtotal) }}</div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500 flex items-center justify-between">
                            <span>이번달 내 수수료 합계</span>
                            <Link
                                v-if="authUserId"
                                :href="route('commission-summary.statement', authUserId) + `?month=${month}`"
                                class="text-xs text-primary-500 hover:underline"
                            >
                                상세 명세 →
                            </Link>
                        </div>
                        <div class="text-2xl font-semibold mt-2 text-primary-700">{{ currency(thisMonthCommission) }}</div>
                        <div class="text-xs text-surface-400 mt-1">승인된 실적 기준</div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">제출/검수 대기</div>
                        <div class="text-2xl font-semibold mt-2">{{ n(statusCounts.submitted + statusCounts.reviewed) }}건</div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-sm text-surface-500">승인 / 임시저장</div>
                        <div class="text-2xl font-semibold mt-2">
                            {{ n(statusCounts.approved) }}건
                            <span class="text-base text-surface-400">/ {{ n(statusCounts.draft) }}</span>
                        </div>
                    </template>
                </Card>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <Card class="xl:col-span-2">
                    <template #title>월별 실적 추이 (최근 12개월 · 승인 기준)</template>
                    <template #content>
                        <div v-if="hasChartData" class="w-full" style="height: 260px">
                            <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
                        </div>
                        <div
                            v-else
                            class="flex items-center justify-center rounded border border-dashed border-surface-300 dark:border-surface-700 text-sm text-surface-500"
                            style="height: 260px"
                        >
                            최근 12개월 내 승인된 실적이 없습니다.
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #title>
                        <div class="flex items-center justify-between">
                            <span>최근 반려 실적</span>
                            <Tag v-if="recentRejected.length" :value="`${n(recentRejected.length)}건`" severity="danger" />
                        </div>
                    </template>
                    <template #content>
                        <div v-if="recentRejected.length === 0" class="py-10 text-center text-surface-500 text-sm">
                            반려된 실적이 없습니다. 👍
                        </div>
                        <ul v-else class="flex flex-col gap-3">
                            <li
                                v-for="r in recentRejected"
                                :key="r.id"
                                class="border border-surface-200 dark:border-surface-700 rounded-lg p-3"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-sm">{{ r.company_name ?? '-' }}</span>
                                    <Link :href="route('performance.edit', r.id)" class="text-xs text-primary-600 hover:underline shrink-0">
                                        수정 →
                                    </Link>
                                </div>
                                <div class="text-xs text-surface-500 mt-0.5">
                                    {{ r.performance_no }} · {{ r.product_name ?? '-' }}
                                </div>
                                <div
                                    v-if="r.rejected_reason"
                                    class="mt-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 rounded px-2 py-1"
                                >
                                    <i class="pi pi-exclamation-circle text-[10px] mr-1" />{{ r.rejected_reason }}
                                </div>
                            </li>
                        </ul>
                    </template>
                </Card>
            </div>

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>내 담당 거래처</span>
                        <span class="text-sm font-normal text-surface-500">총 {{ n(myAssignedCompanyTotal) }}곳</span>
                    </div>
                </template>
                <template #content>
                    <div v-if="myAssignedCompanies.length === 0" class="py-6 text-center text-surface-500 text-sm">
                        아직 담당 거래처가 배정되지 않았습니다. 관리자에게 문의하세요.
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <Link
                            v-for="c in myAssignedCompanies"
                            :key="c.id"
                            :href="route('companies.show', c.id)"
                            class="inline-flex items-center gap-2 px-3 py-2 border border-surface-200 dark:border-surface-700 rounded-lg hover:border-primary-500 transition-colors"
                        >
                            <span class="font-medium">{{ c.company_name }}</span>
                            <Tag v-if="c.default_commission_grade" :value="c.default_commission_grade.toUpperCase()" severity="info" />
                        </Link>
                        <Link
                            v-if="myAssignedCompanyTotal > myAssignedCompanies.length"
                            :href="route('companies.index')"
                            class="inline-flex items-center px-3 py-2 text-sm text-primary-600 hover:underline"
                        >
                            전체 보기 ({{ n(myAssignedCompanyTotal - myAssignedCompanies.length) }}곳 더) →
                        </Link>
                    </div>
                </template>
            </Card>

            <Card>
                <template #title>이번달 정산 요약 (내 거래처)</template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-sm text-surface-500">대상 거래처</div>
                            <div class="text-xl font-semibold mt-1">{{ n(settlementSummary.companyCount) }}곳</div>
                        </div>
                        <div>
                            <div class="text-sm text-surface-500">정산 합계(소계)</div>
                            <div class="text-xl font-semibold mt-1">{{ currency(settlementSummary.totalSubtotal) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-surface-500">정산 합계(수수료)</div>
                            <div class="text-xl font-semibold mt-1">{{ currency(settlementSummary.totalCommission) }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2 items-end">
                            <Tag :value="`작성중 ${n(settlementSummary.statusCounts.draft)}건`" severity="secondary" />
                            <Tag :value="`확정 ${n(settlementSummary.statusCounts.confirmed)}건`" severity="info" />
                            <Tag :value="`지급완료 ${n(settlementSummary.statusCounts.paid)}건`" severity="success" />
                            <Tag :value="`취소 ${n(settlementSummary.statusCounts.cancelled)}건`" severity="danger" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <DataTable :value="recentSettlements" stripedRows>
                            <template #empty>
                                <div class="text-center py-8 text-surface-500">이번달 정산 데이터가 없습니다.</div>
                            </template>
                            <Column header="정산번호" field="settlement_no" style="width: 180px" />
                            <Column header="거래처" field="company_name" />
                            <Column header="소계" style="width: 140px">
                                <template #body="{ data }">
                                    <span class="tabular-nums">{{ n(Number(data.total_subtotal)) }}원</span>
                                </template>
                            </Column>
                            <Column header="수수료" style="width: 140px">
                                <template #body="{ data }">
                                    <span class="tabular-nums">{{ n(Number(data.total_commission)) }}원</span>
                                </template>
                            </Column>
                            <Column header="상태" body-class="text-center" style="width: 120px">
                                <template #body="{ data }">
                                    <Tag :value="settlementStatusLabel(data.status)" :severity="settlementStatusSeverity(data.status)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </template>
            </Card>

            <Card>
                <template #title>최근 내 실적 10건</template>
                <template #content>
                    <DataTable :value="recentPerformances" stripedRows>
                        <template #empty>
                            <div class="text-center py-10 text-surface-500">최근 실적이 없습니다.</div>
                        </template>
                        <Column header="번호" field="performance_no" style="width: 160px" />
                        <Column header="일자" field="performance_date" style="width: 140px" />
                        <Column header="거래처" field="company_name" />
                        <Column header="제품" field="product_name" />
                        <Column header="수량" style="width: 90px">
                            <template #body="{ data }">
                                <span class="tabular-nums">{{ n(data.quantity) }}</span>
                            </template>
                        </Column>
                        <Column header="소계" style="width: 140px">
                            <template #body="{ data }">
                                <span class="tabular-nums">{{ n(Number(data.subtotal)) }}원</span>
                            </template>
                        </Column>
                        <Column header="상태" body-class="text-center" style="width: 120px">
                            <template #body="{ data }">
                                <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>

