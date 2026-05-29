<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';

interface Stats {
    total_performance: number;
    pending_settlements: number;
    notice_count: number;
    user_count: number;
}

interface MonthlyPoint {
    month: string;
    amount: number;
}

interface TopProduct {
    name: string;
    amount: number;
}

interface RecentNotice {
    id: number;
    title: string;
    author: string;
    created_at: string;
}

const props = defineProps<{
    stats: Stats;
    monthlyPerformance: MonthlyPoint[];
    topProducts: TopProduct[];
    recentNotices: RecentNotice[];
}>();

const currency = (n: number) => new Intl.NumberFormat('ko-KR').format(n) + '원';

const cards = computed(() => [
    {
        label: '누적 실적',
        value: currency(props.stats.total_performance),
        icon: 'pi pi-chart-bar',
        color: 'bg-blue-100 text-blue-600',
    },
    {
        label: '대기 정산',
        value: `${new Intl.NumberFormat('ko-KR').format(props.stats.pending_settlements)}건`,
        icon: 'pi pi-calculator',
        color: 'bg-amber-100 text-amber-600',
    },
    {
        label: '공지 수',
        value: `${props.stats.notice_count}건`,
        icon: 'pi pi-megaphone',
        color: 'bg-emerald-100 text-emerald-600',
    },
    {
        label: '사용자 수',
        value: `${props.stats.user_count}명`,
        icon: 'pi pi-users',
        color: 'bg-violet-100 text-violet-600',
    },
]);

const lineChartData = ref({
    labels: props.monthlyPerformance.map((p) => p.month),
    datasets: [
        {
            label: '월별 실적',
            data: props.monthlyPerformance.map((p) => p.amount),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.15)',
            tension: 0.35,
            fill: true,
        },
    ],
});

const lineChartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (v: number) => new Intl.NumberFormat('ko-KR').format(v),
            },
        },
    },
});

const donutChartData = computed(() => ({
    labels: props.topProducts.length
        ? props.topProducts.map((p) => p.name)
        : ['데이터 없음'],
    datasets: [
        {
            data: props.topProducts.length
                ? props.topProducts.map((p) => p.amount)
                : [1],
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6'],
        },
    ],
}));

const donutChartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
});
</script>

<template>
    <Head title="대시보드" />

    <AdminLayout>
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">대시보드</h1>
                <p class="text-surface-500 dark:text-surface-400 mt-1">정진팜 실적관리 시스템 현황입니다.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <Card v-for="card in cards" :key="card.label">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm text-surface-500 dark:text-surface-400">{{ card.label }}</span>
                                <div class="text-2xl font-semibold text-surface-900 dark:text-surface-0 mt-2">
                                    {{ card.value }}
                                </div>
                            </div>
                            <div :class="['rounded-xl p-3 flex items-center justify-center', card.color]" style="width: 48px; height: 48px">
                                <i :class="card.icon" style="font-size: 1.25rem" />
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <Card class="xl:col-span-2">
                    <template #title>월별 실적 추이</template>
                    <template #content>
                        <div style="height: 320px">
                            <Chart type="line" :data="lineChartData" :options="lineChartOptions" />
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #title>상위 제품</template>
                    <template #content>
                        <div style="height: 320px">
                            <Chart type="doughnut" :data="donutChartData" :options="donutChartOptions" />
                        </div>
                    </template>
                </Card>
            </div>

            <Card>
                <template #title>최근 공지</template>
                <template #content>
                    <DataTable :value="recentNotices" :paginator="false" stripedRows responsiveLayout="scroll">
                        <template #empty>
                            <div class="text-center text-surface-500 py-8">아직 등록된 공지가 없습니다.</div>
                        </template>
                        <Column field="title" header="제목" />
                        <Column field="author" header="작성자" style="width: 180px" />
                        <Column field="created_at" header="작성일" style="width: 180px">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.created_at" severity="secondary" />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
