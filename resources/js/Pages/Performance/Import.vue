<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Divider from 'primevue/divider';
import Message from 'primevue/message';
import Tag from 'primevue/tag';

type Action = 'create' | 'error';

interface Preview {
    performance_date: string;
    quantity: number;
    unit_price: number;
    commission_rate: number | null;
    subtotal: number;
    commission_amount: number | null;
    price_source: string;
    commission_source: string;
}

interface RowResult {
    line: number;
    action: Action;
    identifier: string;
    company?: { id: number; company_name: string };
    product?: { id: number; product_name: string; insurance_code: string };
    errors: string[];
    preview: Preview | null;
}

interface Analysis {
    token: string;
    filename: string;
    headers: string[];
    row_count: number;
    summary: { create: number; error: number };
    results: RowResult[];
    expires_at: string;
}

const props = defineProps<{
    allowedHeaders: string[];
    sampleHeader: string;
    analysis?: Analysis | null;
}>();

const analyzeForm = useForm<{ file: File | null; mode: 'analyze' }>({
    file: null,
    mode: 'analyze',
});

const commitForm = useForm<{ token: string; mode: 'commit'; file: File | null }>({
    token: props.analysis?.token ?? '',
    mode: 'commit',
    file: null,
});

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    analyzeForm.file = target.files && target.files[0] ? target.files[0] : null;
};

const submitAnalyze = () => {
    analyzeForm.post(route('performance.import.handle'), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const submitCommit = () => {
    if (!props.analysis) return;
    commitForm.token = props.analysis.token;
    commitForm.post(route('performance.import.handle'), { preserveScroll: false });
};

const actionLabel = (a: Action) => ({ create: '생성', error: '오류' }[a]);
const actionSeverity = (a: Action) =>
    ({ create: 'success', error: 'danger' }[a]) as 'success' | 'danger';

const sourceLabel = (s: string) =>
    ({
        override: '예외',
        product_sale: '제품 매출가',
        products_price: '제품 기본가',
        manual: '수동',
        matrix: '매트릭스',
        none: '없음',
    }[s] ?? s);

const formatMoney = (v: number | null) =>
    v === null ? '-' : new Intl.NumberFormat('ko-KR').format(v) + '원';

const formatRate = (v: number | null) => (v === null ? '-' : `${v}%`);

const hasErrors = computed(() => (props.analysis?.summary.error ?? 0) > 0);

const errorRowsOnly = ref(false);
const filteredResults = computed(() => {
    if (!props.analysis) return [];
    return errorRowsOnly.value
        ? props.analysis.results.filter((r) => r.action === 'error')
        : props.analysis.results;
});
</script>

<template>
    <Head title="실적 CSV 일괄 등록" />
    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-6xl mx-auto">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">실적 CSV 일괄 등록</h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        거래처·제품·실적일·수량을 CSV 로 업로드하면, 단가·수수료는 각 행별 스냅샷 해석 규칙에 따라 자동 산출되어 <strong>draft</strong> 상태로 등록됩니다.
                    </p>
                </div>
                <Link :href="route('performance.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>

            <Card>
                <template #content>
                    <div class="flex flex-col gap-3">
                        <h2 class="text-lg font-semibold">1. CSV 업로드 & 검증 (Dry-run)</h2>
                        <p class="text-sm text-surface-500">
                            UTF-8 CSV (BOM 허용). 최대 5MB. 분석 결과를 확인한 뒤 확정해야 실제 등록됩니다. 토큰 유효기간 30분.
                        </p>
                        <div class="flex items-center gap-2 flex-wrap">
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                class="block text-sm text-surface-700 dark:text-surface-200 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:bg-primary file:text-white file:font-medium hover:file:bg-primary-emphasis"
                                @change="onFileChange"
                            />
                            <Button
                                label="검증 (Dry-run)"
                                icon="pi pi-play"
                                :loading="analyzeForm.processing"
                                :disabled="!analyzeForm.file"
                                @click="submitAnalyze"
                            />
                            <a :href="route('performance.import.sample')">
                                <Button label="샘플 CSV" icon="pi pi-download" severity="secondary" text />
                            </a>
                        </div>
                        <small v-if="analyzeForm.errors.file" class="text-red-500 whitespace-pre-line">{{ analyzeForm.errors.file }}</small>
                    </div>

                    <Divider />

                    <details class="text-sm text-surface-600 dark:text-surface-300">
                        <summary class="cursor-pointer font-medium">허용 컬럼 ({{ allowedHeaders.length }}개)</summary>
                        <pre class="bg-surface-100 dark:bg-surface-800 rounded p-2 mt-2 overflow-x-auto">{{ sampleHeader }}</pre>
                        <ul class="list-disc list-inside mt-2 text-xs text-surface-500">
                            <li>필수: performance_date, quantity</li>
                            <li>거래처 키: company_biz_no (사업자등록번호) / company_name — 최소 1개. biz_no 우선 매칭</li>
                            <li>제품 키: insurance_code / product_code — 최소 1개. insurance_code 우선 매칭</li>
                            <li>quantity 는 0 불가. 반품은 음수로 표기</li>
                            <li>단가·수수료는 실적일 기준으로 자동 해석 (override → product_prices → products.price)</li>
                        </ul>
                    </details>
                </template>
            </Card>

            <Card v-if="analysis">
                <template #content>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-lg font-semibold">2. 분석 결과 — {{ analysis.filename }}</h2>
                            <p class="text-xs text-surface-500 mt-1">
                                전체 {{ analysis.row_count }}행 · 생성 {{ analysis.summary.create }} · 오류 {{ analysis.summary.error }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="analysis.summary.error > 0"
                                :label="errorRowsOnly ? '전체 보기' : '오류만 보기'"
                                icon="pi pi-filter"
                                severity="secondary"
                                text
                                @click="errorRowsOnly = !errorRowsOnly"
                            />
                            <Button
                                label="확정 적용"
                                icon="pi pi-check"
                                :loading="commitForm.processing"
                                :disabled="hasErrors"
                                @click="submitCommit"
                            />
                        </div>
                    </div>

                    <Message v-if="hasErrors" severity="warn" :closable="false" class="mb-3">
                        검증 오류가 {{ analysis.summary.error }}건 있습니다. 한 행이라도 오류가 있으면 적용되지 않습니다 (all-or-nothing).
                    </Message>
                    <Message v-else severity="success" :closable="false" class="mb-3">
                        모든 행이 통과했습니다. 확정 적용을 누르면 트랜잭션 내에서 일괄 등록됩니다.
                    </Message>

                    <DataTable :value="filteredResults" data-key="line" :rows="20" paginator responsive-layout="scroll">
                        <template #empty>
                            <div class="text-center py-6 text-surface-500">표시할 행이 없습니다.</div>
                        </template>

                        <Column field="line" header="행" style="width: 5rem">
                            <template #body="{ data }">
                                <span class="text-sm font-mono">{{ data.line }}</span>
                            </template>
                        </Column>
                        <Column field="action" header="동작" style="width: 6rem">
                            <template #body="{ data }">
                                <Tag :value="actionLabel(data.action)" :severity="actionSeverity(data.action)" />
                            </template>
                        </Column>
                        <Column field="identifier" header="식별자">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-xs text-surface-500">{{ data.identifier }}</span>
                                    <span v-if="data.company" class="font-medium">{{ data.company.company_name }}</span>
                                    <span v-if="data.product" class="text-xs">{{ data.product.product_name }} ({{ data.product.insurance_code }})</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="스냅샷 미리보기">
                            <template #body="{ data }">
                                <div v-if="data.preview" class="flex flex-col gap-1 text-xs">
                                    <div>수량 {{ data.preview.quantity }} · 단가 {{ formatMoney(data.preview.unit_price) }}
                                        <Tag :value="sourceLabel(data.preview.price_source)" severity="info" class="ml-1" />
                                    </div>
                                    <div>
                                        매출 <strong>{{ formatMoney(data.preview.subtotal) }}</strong>
                                        · 수수료율 {{ formatRate(data.preview.commission_rate) }}
                                        · 수수료 <strong>{{ formatMoney(data.preview.commission_amount) }}</strong>
                                        <Tag :value="sourceLabel(data.preview.commission_source)" severity="secondary" class="ml-1" />
                                    </div>
                                </div>
                                <span v-else class="text-xs text-surface-400">-</span>
                            </template>
                        </Column>
                        <Column field="errors" header="오류">
                            <template #body="{ data }">
                                <ul v-if="data.errors.length > 0" class="list-disc list-inside text-xs text-red-600 dark:text-red-400">
                                    <li v-for="(e, i) in data.errors" :key="i">{{ e }}</li>
                                </ul>
                                <span v-else class="text-xs text-surface-400">-</span>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
