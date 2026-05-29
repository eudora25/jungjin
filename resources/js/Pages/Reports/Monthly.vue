<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';

interface CompanyRow {
    company_id: number;
    company_name: string;
    partner_type: string | null;
    line_count: number;
    total_quantity: number;
    total_subtotal: number;
    total_commission: number;
    avg_commission_rate: number;
}
interface SalesRow {
    user_id: number | null;
    user_name: string;
    line_count: number;
    total_quantity: number;
    total_subtotal: number;
    total_commission: number;
    avg_commission_rate: number;
}
interface ProductRow {
    product_id: number;
    product_name: string;
    insurance_code: string | null;
    manufacturer: string | null;
    line_count: number;
    total_quantity: number;
    total_subtotal: number;
    total_commission: number;
    avg_commission_rate: number;
}

const props = defineProps<{
    byCompany: CompanyRow[];
    bySales: SalesRow[];
    byProduct: ProductRow[];
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
const activeTab = ref<string>('company');

const applyMonth = () => {
    router.get(
        route('reports.monthly'),
        { month: monthInput.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const applyRange = () => {
    router.get(
        route('reports.monthly'),
        { from: fromInput.value, to: toInput.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const resetFilters = () => {
    monthInput.value = '';
    fromInput.value = '';
    toInput.value = '';
    router.get(route('reports.monthly'), {}, { preserveState: true, preserveScroll: true, replace: true });
};

const exportHref = () => {
    const params = new URLSearchParams();
    if (props.filters.month) {
        params.set('month', props.filters.month);
    } else {
        params.set('from', props.filters.from);
        params.set('to', props.filters.to);
    }
    return `${route('reports.monthly.export.excel')}?${params.toString()}`;
};

const partnerTypeLabel = (t: string | null) =>
    ({ company: '업체', pharmacy: '약국', hospital: '병원' })[t ?? ''] ?? (t ?? '');

const n = (v: number) => new Intl.NumberFormat('ko-KR').format(v);
const currency = (v: number) => `${n(Math.round(v))}원`;
const pct = (v: number) => `${v.toFixed(1)}%`;
</script>

<template>
    <Head title="월간 보고서" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">월간 보고서</h1>
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
                    <InputText v-model="monthInput" placeholder="2026-05" class="w-40" @keyup.enter="applyMonth" />
                </div>
                <Button label="월로 조회" icon="pi pi-search" @click="applyMonth" />

                <div class="w-px h-8 bg-surface-200 mx-2 hidden md:block" />

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-surface-500">시작일</label>
                    <InputText v-model="fromInput" placeholder="2026-05-01" class="w-40" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-surface-500">종료일</label>
                    <InputText v-model="toInput" placeholder="2026-05-31" class="w-40" />
                </div>
                <Button label="기간으로 조회" icon="pi pi-search" severity="info" @click="applyRange" />
                <Button label="초기화" icon="pi pi-times" severity="secondary" outlined @click="resetFilters" />
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

            <!-- 3종 탭 -->
            <Tabs v-model:value="activeTab">
                <TabList>
                    <Tab value="company">거래처별 <span class="ml-1 text-xs text-surface-500">({{ byCompany.length }})</span></Tab>
                    <Tab value="sales">영업사원별 <span class="ml-1 text-xs text-surface-500">({{ bySales.length }})</span></Tab>
                    <Tab value="product">제품별 <span class="ml-1 text-xs text-surface-500">({{ byProduct.length }})</span></Tab>
                </TabList>
                <TabPanels>
                    <!-- 거래처별 -->
                    <TabPanel value="company">
                        <DataTable :value="byCompany" stripedRows paginator :rows="20">
                            <template #empty>
                                <div class="text-center py-10 text-surface-500">기간 내 승인된 실적이 없습니다.</div>
                            </template>
                            <Column header="거래처명" field="company_name" style="min-width: 160px" />
                            <Column header="유형" style="width: 90px">
                                <template #body="{ data }">{{ partnerTypeLabel(data.partner_type) }}</template>
                            </Column>
                            <Column header="실적 건수" style="width: 100px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ n(data.line_count) }}</span></template>
                            </Column>
                            <Column header="수량 합계" style="width: 100px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ n(data.total_quantity) }}</span></template>
                            </Column>
                            <Column header="매출 합계" style="width: 150px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ currency(data.total_subtotal) }}</span></template>
                            </Column>
                            <Column header="수수료 합계" style="width: 150px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums font-semibold">{{ currency(data.total_commission) }}</span></template>
                            </Column>
                            <Column header="평균 수수료율" style="width: 110px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ pct(data.avg_commission_rate) }}</span></template>
                            </Column>
                        </DataTable>
                    </TabPanel>

                    <!-- 영업사원별 -->
                    <TabPanel value="sales">
                        <DataTable :value="bySales" stripedRows paginator :rows="20">
                            <template #empty>
                                <div class="text-center py-10 text-surface-500">기간 내 승인된 실적이 없습니다.</div>
                            </template>
                            <Column header="영업사원명" field="user_name" style="min-width: 160px" />
                            <Column header="실적 건수" style="width: 100px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ n(data.line_count) }}</span></template>
                            </Column>
                            <Column header="수량 합계" style="width: 100px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ n(data.total_quantity) }}</span></template>
                            </Column>
                            <Column header="매출 합계" style="width: 150px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ currency(data.total_subtotal) }}</span></template>
                            </Column>
                            <Column header="수수료 합계" style="width: 150px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums font-semibold">{{ currency(data.total_commission) }}</span></template>
                            </Column>
                            <Column header="평균 수수료율" style="width: 110px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ pct(data.avg_commission_rate) }}</span></template>
                            </Column>
                        </DataTable>
                    </TabPanel>

                    <!-- 제품별 -->
                    <TabPanel value="product">
                        <DataTable :value="byProduct" stripedRows paginator :rows="20">
                            <template #empty>
                                <div class="text-center py-10 text-surface-500">기간 내 승인된 실적이 없습니다.</div>
                            </template>
                            <Column header="제품명" field="product_name" style="min-width: 160px" />
                            <Column header="보험코드" field="insurance_code" style="width: 120px" />
                            <Column header="제조사" field="manufacturer" style="min-width: 120px" />
                            <Column header="실적 건수" style="width: 100px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ n(data.line_count) }}</span></template>
                            </Column>
                            <Column header="수량 합계" style="width: 100px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ n(data.total_quantity) }}</span></template>
                            </Column>
                            <Column header="매출 합계" style="width: 150px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums">{{ currency(data.total_subtotal) }}</span></template>
                            </Column>
                            <Column header="수수료 합계" style="width: 150px; text-align: right">
                                <template #body="{ data }"><span class="tabular-nums font-semibold">{{ currency(data.total_commission) }}</span></template>
                            </Column>
                        </DataTable>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </AdminLayout>
</template>
