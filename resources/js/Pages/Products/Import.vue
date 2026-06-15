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

type Action = 'create' | 'update' | 'error';

interface PriceChange {
    type: 'insurance' | 'cost' | 'sale';
    amount: number;
    effective_from: string;
}

interface RowResult {
    line: number;
    action: Action;
    identifier: string;
    product_id?: number | null;
    errors: string[];
    price_changes: PriceChange[];
}

interface Analysis {
    token: string;
    filename: string;
    headers: string[];
    row_count: number;
    summary: { create: number; update: number; price_added: number; error: number };
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
    analyzeForm.post(route('products.import.handle'), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const submitCommit = () => {
    if (!props.analysis) return;
    commitForm.token = props.analysis.token;
    commitForm.post(route('products.import.handle'), { preserveScroll: false });
};

const actionLabel = (a: Action) => ({ create: '신규', update: '수정', error: '오류' }[a]);
const actionSeverity = (a: Action) =>
    ({ create: 'success', update: 'info', error: 'danger' }[a]) as 'success' | 'info' | 'danger';

const priceTypeLabel = (t: PriceChange['type']) =>
    ({ insurance: '보험약가', cost: '매입가', sale: '매출가' }[t]);
const priceTypeSeverity = (t: PriceChange['type']) =>
    ({ insurance: 'info', cost: 'warn', sale: 'success' }[t]) as 'info' | 'warn' | 'success';

const formatMoney = (v: number) => new Intl.NumberFormat('ko-KR').format(v) + '원';

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
    <Head title="제품 CSV 일괄 등록" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">제품 CSV 일괄 등록</h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        키 컬럼(insurance_code/standard_code/barcode_gtin) 기준으로 신규/수정 일괄 처리. 가격 컬럼이 있으면
                        가격 이력에 자동으로 추가됩니다.
                    </p>
                </div>
                <Link :href="route('products.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>

            <Card>
                <template #content>
                    <div class="flex flex-col gap-3">
                        <h2 class="text-lg font-semibold">1. CSV 업로드 & 검증 (Dry-run)</h2>
                        <p class="text-sm text-surface-500">
                            UTF-8 CSV (BOM 허용). 최대 5MB. 분석 결과를 보고 확정해야 실제 적용됩니다. 분석 토큰은 30분 유지됩니다.
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
                            <a :href="route('products.import.sample')">
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
                            <li>키 컬럼: insurance_code / standard_code / barcode_gtin (최소 1개)</li>
                            <li>필수: product_name</li>
                            <li>가격 컬럼: insurance_price / cost_price / sale_price (각각 양수)</li>
                            <li>price_effective_from 미지정 시 오늘로 적용</li>
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
                                전체 {{ analysis.row_count }}행 · 신규 {{ analysis.summary.create }} · 수정 {{ analysis.summary.update }}
                                · 가격 추가 {{ analysis.summary.price_added }} · 오류 {{ analysis.summary.error }}
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
                        모든 행이 통과했습니다. 확정 적용을 누르면 트랜잭션 내에서 일괄 처리됩니다.
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
                        <Column field="action" header="동작" style="width: 7rem">
                            <template #body="{ data }">
                                <Tag :value="actionLabel(data.action)" :severity="actionSeverity(data.action)" />
                            </template>
                        </Column>
                        <Column field="identifier" header="식별자">
                            <template #body="{ data }">
                                <span class="font-medium">{{ data.identifier }}</span>
                                <span v-if="data.product_id" class="ml-2 text-xs text-surface-500">#{{ data.product_id }}</span>
                            </template>
                        </Column>
                        <Column field="price_changes" header="가격 추가" style="width: 18rem">
                            <template #body="{ data }">
                                <div v-if="data.price_changes.length === 0" class="text-xs text-surface-400">-</div>
                                <div v-else class="flex flex-wrap gap-1">
                                    <Tag
                                        v-for="(p, i) in data.price_changes"
                                        :key="i"
                                        :value="`${priceTypeLabel(p.type)} ${formatMoney(p.amount)}`"
                                        :severity="priceTypeSeverity(p.type)"
                                    />
                                </div>
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
