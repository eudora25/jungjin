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
type Status = 'active' | 'inactive' | null;

interface RowResult {
    line: number;
    action: Action;
    identifier: string;
    hospital_type: string | null;
    status: Status;
    errors: string[];
}

interface Analysis {
    token: string;
    filename: string;
    headers: string[];
    row_count: number;
    summary: { create: number; update: number; inactive: number; error: number };
    results: RowResult[];
    expires_at: string;
}

const props = withDefaults(defineProps<{
    requiredHeaders: string[];
    analysis?: Analysis | null;
    handleRoute?: string;
    indexRoute?: string;
}>(), {
    handleRoute: 'hospitals.import.handle',
    indexRoute: 'hospitals.index',
});

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
    analyzeForm.post(route(props.handleRoute), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const submitCommit = () => {
    if (!props.analysis) return;
    commitForm.token = props.analysis.token;
    commitForm.post(route(props.handleRoute), { preserveScroll: false });
};

const actionLabel = (a: Action) => ({ create: '신규', update: '수정', error: '오류' }[a]);
const actionSeverity = (a: Action) =>
    ({ create: 'success', update: 'info', error: 'danger' }[a]) as 'success' | 'info' | 'danger';

const statusLabel = (s: Status) => (s === 'active' ? '활성' : s === 'inactive' ? '비활성' : '-');
const statusSeverity = (s: Status) => (s === 'active' ? 'success' : s === 'inactive' ? 'secondary' : 'secondary');

const typeLabels: Record<string, string> = {
    general_hospital: '종합병원',
    hospital: '병원',
    clinic: '의원',
    dental: '치과',
    oriental: '한의원',
    other: '기타',
};

const hasErrors = computed(() => (props.analysis?.summary.error ?? 0) > 0);
const errorRowsOnly = ref(false);
const filteredResults = computed(() => {
    if (!props.analysis) return [];
    return errorRowsOnly.value ? props.analysis.results.filter((r) => r.action === 'error') : props.analysis.results;
});
</script>

<template>
    <Head title="병의원 CSV 일괄 등록" />
    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-6xl mx-auto">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">병의원 CSV 일괄 등록</h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">
                        공공데이터 CSV(대부분 CP949) 업로드를 지원합니다. <strong>관리번호</strong>를 `hospital_code`로 사용해 upsert 합니다.
                    </p>
                </div>
                <Link :href="route(props.indexRoute)">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>

            <Card>
                <template #content>
                    <div class="flex flex-col gap-3">
                        <h2 class="text-lg font-semibold">1. CSV 업로드 & 검증 (Dry-run)</h2>
                        <p class="text-sm text-surface-500">
                            CP949(EUC-KR) CSV 지원. 최대 100MB. 분석 결과를 확인한 뒤 확정해야 실제 적용됩니다. 토큰 유효기간 30분.
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
                        </div>
                        <small v-if="analyzeForm.errors.file" class="text-red-500 whitespace-pre-line">{{ analyzeForm.errors.file }}</small>
                    </div>

                    <Divider />

                    <details class="text-sm text-surface-600 dark:text-surface-300">
                        <summary class="cursor-pointer font-medium">필수 컬럼</summary>
                        <pre class="bg-surface-100 dark:bg-surface-800 rounded p-2 mt-2 overflow-x-auto">{{ requiredHeaders.join(',') }}</pre>
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
                                · 비활성 {{ analysis.summary.inactive }} · 오류 {{ analysis.summary.error }}
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
                            <Button label="확정 적용" icon="pi pi-check" :loading="commitForm.processing" :disabled="hasErrors" @click="submitCommit" />
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
                            <template #body="{ data }"><span class="text-sm font-mono">{{ data.line }}</span></template>
                        </Column>
                        <Column field="action" header="동작" style="width: 7rem">
                            <template #body="{ data }">
                                <Tag :value="actionLabel(data.action)" :severity="actionSeverity(data.action)" />
                            </template>
                        </Column>
                        <Column field="identifier" header="식별자" />
                        <Column header="유형" style="width: 8rem">
                            <template #body="{ data }">
                                <Tag v-if="data.hospital_type" :value="typeLabels[data.hospital_type] ?? data.hospital_type" severity="info" />
                                <span v-else class="text-xs text-surface-400">-</span>
                            </template>
                        </Column>
                        <Column header="상태" style="width: 7rem">
                            <template #body="{ data }">
                                <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
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

