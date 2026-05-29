<script setup lang="ts">
import { computed } from 'vue';
import Chart from 'primevue/chart';

type PriceType = 'insurance' | 'cost' | 'sale';

interface ProductPriceRow {
    id: number;
    price_type: PriceType;
    amount: string | number;
    effective_from: string; // YYYY-MM-DD
    effective_to: string | null; // YYYY-MM-DD | null
}

const props = defineProps<{
    prices: ProductPriceRow[];
}>();

const COLORS: Record<PriceType, { border: string; bg: string }> = {
    insurance: { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.12)' },
    cost: { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.12)' },
    sale: { border: '#10b981', bg: 'rgba(16, 185, 129, 0.15)' },
};

const TYPE_LABELS: Record<PriceType, string> = {
    insurance: '보험약가',
    cost: '매입가',
    sale: '매출가',
};

const todayIso = () => new Date().toISOString().slice(0, 10);

/** 날짜 unique + 정렬, 마지막에 today 보강 */
const buildLabels = (rows: ProductPriceRow[]): string[] => {
    const set = new Set<string>();
    rows.forEach((r) => {
        set.add(r.effective_from);
        if (r.effective_to) set.add(r.effective_to);
    });
    set.add(todayIso());
    return Array.from(set).sort();
};

/**
 * 한 type 의 step series 생성
 *  - 어떤 라벨 시점 d 에 적용 중인 가격(amount) 을 채움.
 *  - effective_from <= d AND (effective_to == null OR effective_to >= d)
 */
const buildSeries = (rows: ProductPriceRow[], type: PriceType, labels: string[]) => {
    const items = rows
        .filter((r) => r.price_type === type)
        .map((r) => ({ ...r, amount: typeof r.amount === 'string' ? Number(r.amount) : r.amount }))
        .sort((a, b) => a.effective_from.localeCompare(b.effective_from));

    if (items.length === 0) return null;

    return labels.map((d) => {
        const active = items.find((it) => it.effective_from <= d && (it.effective_to === null || it.effective_to >= d));
        return active ? Number(active.amount) : null;
    });
};

const chartData = computed(() => {
    const labels = buildLabels(props.prices);
    const datasets = (Object.keys(TYPE_LABELS) as PriceType[])
        .map((type) => ({ type, data: buildSeries(props.prices, type, labels) }))
        .filter((d) => d.data !== null)
        .map(({ type, data }) => ({
            label: TYPE_LABELS[type],
            data,
            borderColor: COLORS[type].border,
            backgroundColor: COLORS[type].bg,
            stepped: 'before' as const,
            spanGaps: false,
            tension: 0,
            pointRadius: 3,
            pointHoverRadius: 5,
            fill: false,
        }));

    return { labels, datasets };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
        legend: { position: 'top' as const, labels: { boxWidth: 12 } },
        tooltip: {
            callbacks: {
                label: (ctx: { dataset: { label?: string }; parsed: { y: number | null } }) => {
                    const y = ctx.parsed.y;
                    const v = y === null ? '-' : new Intl.NumberFormat('ko-KR').format(y) + '원';
                    return `${ctx.dataset.label ?? ''}: ${v}`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: false,
            ticks: {
                callback: (v: number) => new Intl.NumberFormat('ko-KR').format(v),
            },
        },
        x: {
            ticks: { autoSkip: true, maxTicksLimit: 8 },
        },
    },
}));

const hasData = computed(() => chartData.value.datasets.length > 0);
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">가격 변동 추이</h3>
            <span class="text-xs text-surface-500">step line · 보험약가 / 매입가 / 매출가</span>
        </div>
        <div v-if="hasData" class="w-full" style="height: 240px">
            <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
        </div>
        <div
            v-else
            class="flex items-center justify-center h-32 rounded border border-dashed border-surface-300 dark:border-surface-700 text-sm text-surface-500"
        >
            등록된 가격 이력이 없습니다.
        </div>
    </div>
</template>
