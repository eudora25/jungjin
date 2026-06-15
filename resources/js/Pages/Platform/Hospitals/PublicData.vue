<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

interface ImportRow {
    id: number;
    kind: string;
    original_filename: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    report: Record<string, number | string> | null;
    error: string | null;
    created_at: string;
    finished_at: string | null;
    creator: { id: number; name: string } | null;
}

const props = defineProps<{
    imports: ImportRow[];
    kinds: string[];
    stats: { hospitals: number; matched: number };
}>();

// 적재 유형 라벨 — '전국 병의원 및 약국 현황 2026.3' 폴더의 파일 번호·이름 기준
const KIND_LABELS: Record<string, string> = {
    institution: '1. 병원정보서비스 → ykiho 매칭 (가장 먼저)',
    facilities: '3. 시설정보 → 시설·병상',
    hours: '4. 세부정보 → 진료시간·편의',
    specialties: '5. 진료과목정보 → 진료과목',
    transports: '6. 교통정보 → 교통',
    equipments: '7. 의료장비정보 → 의료장비',
    meal_surcharges: '8. 식대가산정보 → 식대가산',
    nursing_grades: '9. 간호등급정보 → 간호등급',
    special_treatments: '10. 특수진료정보서비스 → 특수진료',
    specialized_fields: '11. 전문병원지정분야',
    other_staff: '12. 기타인력정보 → 기타인력',
};

// 각 적재 유형에 올려야 하는 파일명 패턴 — (*) 는 분기 표기(예: 2026.3.)로, 분기마다 바뀜
const EXPECTED_FILE: Record<string, string> = {
    institution: '1.병원정보서비스(*).xlsx',
    facilities: '3.의료기관별상세정보서비스_01_시설정보(*).xlsx',
    hours: '4.의료기관별상세정보서비스_02_세부정보(*).xlsx',
    specialties: '5.의료기관별상세정보서비스_03_진료과목정보(*).xlsx',
    transports: '6.의료기관별상세정보서비스_04_교통정보(*).xlsx',
    equipments: '7.의료기관별상세정보서비스_05_의료장비정보(*).xlsx',
    meal_surcharges: '8.의료기관별상세정보서비스_06_식대가산정보(*).xlsx',
    nursing_grades: '9.의료기관별상세정보서비스_07_간호등급정보(*).xlsx',
    special_treatments: '10.의료기관별상세정보서비스_08_특수진료정보서비스(*).xlsx',
    specialized_fields: '11.의료기관별상세정보서비스_09_전문병원지정분야(*).xlsx',
    other_staff: '12.의료기관별상세정보서비스_10_기타인력정보(*).xlsx',
};

const kindLabel = (k: string) => KIND_LABELS[k] ?? k;

const kindOptions = computed(() => props.kinds.map((k) => ({ value: k, label: kindLabel(k) })));

const expectedFile = computed(() => EXPECTED_FILE[form.kind] ?? '');

// 선택한 파일명이 기대 패턴과 다르면 경고 — (*) 분기 표기는 와일드카드로 매칭 (오업로드 방지)
const fileNameMismatch = computed(() => {
    if (!form.file || !expectedFile.value) return false;
    const pattern = expectedFile.value
        .replace(/[.*+?^${}()|[\]\\]/g, '\\$&') // 정규식 특수문자 이스케이프
        .replace(/\\\*/g, '.*'); // (*) 자리만 와일드카드로
    return !new RegExp(`^${pattern}$`).test(form.file.name);
});

const form = useForm<{ kind: string; file: File | null }>({
    kind: 'institution',
    file: null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
};

const submit = () => {
    form.post(route('platform.hospitals.public-data.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const statusSeverity = (s: string) =>
    ({ pending: 'secondary', processing: 'info', completed: 'success', failed: 'danger' })[s] ?? 'secondary';

const statusLabel = (s: string) =>
    ({ pending: '대기', processing: '처리중', completed: '완료', failed: '실패' })[s] ?? s;

const reportSummary = (row: ImportRow): string => {
    const r = row.report;
    if (!r) return '-';
    if (row.kind === 'institution') {
        const tie = Number(r.tie_broken ?? 0) > 0 ? ` (대표행 선택 ${r.tie_broken})` : '';
        const conflict = Number(r.conflict ?? 0) > 0 ? ` / ykiho 충돌 ${r.conflict}` : '';
        return `총 ${r.total} / 매칭 ${r.matched}${tie} / 미매칭 ${r.unmatched}${conflict} (매칭률 ${r.match_rate}%)`;
    }
    return `총 ${r.total} / 연결 ${r.resolved} / 스킵 ${r.skipped_unmatched} / 적재 ${r.written}`;
};

const refresh = () => router.reload({ only: ['imports', 'stats'] });
</script>

<template>
    <Head title="병의원 보강(공공데이터)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">병의원 보강 — 심평원(HIRA) 공공데이터</h1>
                    <p class="text-surface-500 text-sm mt-1">
                        인허가 CSV로 만든 병의원 마스터에 진료과목·장비·병상·진료시간 등을 보강합니다.
                    </p>
                </div>
                <div class="text-right text-sm">
                    <div>전체 병의원 <b>{{ stats.hospitals.toLocaleString() }}</b></div>
                    <div class="text-surface-500">ykiho 매칭 {{ stats.matched.toLocaleString() }}</div>
                </div>
            </div>

            <Message severity="info" :closable="false">
                <div class="text-sm leading-relaxed">
                    <b>진행 순서</b>
                    <ol class="list-decimal ml-5 mt-1">
                        <li>
                            먼저
                            <Link :href="route('platform.hospitals.import.form')" class="text-primary underline">인허가 CSV 등록</Link>
                            (건강_병원/의원 등) — 병의원 기본 마스터 생성
                        </li>
                        <li><b>1.병원정보서비스</b> 업로드(적재 유형 = ‘1. 병원정보서비스’) → 기관명+우편번호로 매칭해 <code>ykiho</code> 부착 (가장 먼저)</li>
                        <li>상세 유형(3~12번 파일: 시설·세부·진료과목·교통·장비·식대가산·간호등급·특수진료·전문병원분야·기타인력)을 각각 업로드 → 정규화 적재</li>
                    </ol>
                    <p class="mt-1 text-surface-500">대용량 파일은 백그라운드(큐)에서 처리되며, 아래 목록에서 상태를 확인합니다.</p>
                </div>
            </Message>

            <Card>
                <template #title>보강 파일 업로드</template>
                <template #content>
                    <form class="flex flex-col gap-4" @submit.prevent="submit">
                        <!-- 1줄: 적재 유형 + 올릴 파일 -->
                        <div class="flex flex-col gap-3 md:flex-row md:items-end">
                            <div class="flex-1">
                                <label class="block text-sm mb-1">적재 유형 <span class="text-red-500">*</span></label>
                                <Select v-model="form.kind" :options="kindOptions" option-label="label" option-value="value"
                                        class="w-full" />
                                <Message v-if="form.errors.kind" severity="error" size="small" variant="simple">{{ form.errors.kind }}</Message>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm mb-1">올릴 파일</label>
                                <div class="w-full rounded border border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 px-3 py-2 text-sm font-mono break-all">
                                    {{ expectedFile || '-' }}
                                </div>
                            </div>
                        </div>

                        <!-- 2줄: 파일 선택 + 업로드 버튼 -->
                        <div class="flex flex-col gap-3 md:flex-row md:items-end">
                            <div class="flex-1">
                                <label class="block text-sm mb-1">Excel 파일 (.xlsx) <span class="text-red-500">*</span></label>
                                <input ref="fileInput" type="file" accept=".xlsx" class="w-full text-sm border border-surface-300 rounded p-2"
                                       @change="onFileChange" />
                                <Message v-if="fileNameMismatch" severity="warn" size="small" variant="simple">
                                    선택한 파일명이 권장 파일과 다릅니다. 적재 유형에 맞는 파일인지 확인하세요.
                                </Message>
                                <Message v-if="form.errors.file" severity="error" size="small" variant="simple">{{ form.errors.file }}</Message>
                            </div>
                            <Button type="submit" label="업로드 & 적재" icon="pi pi-upload" :loading="form.processing"
                                    :disabled="!form.file" />
                        </div>
                    </form>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>최근 적재 이력</span>
                        <Button label="새로고침" icon="pi pi-refresh" size="small" text @click="refresh" />
                    </div>
                </template>
                <template #content>
                    <DataTable :value="imports" striped-rows>
                        <template #empty>
                            <div class="text-center py-8 text-surface-500">아직 업로드한 보강 파일이 없습니다.</div>
                        </template>
                        <Column header="유형" style="width: 200px">
                            <template #body="{ data }">{{ kindLabel(data.kind) }}</template>
                        </Column>
                        <Column header="파일" field="original_filename" />
                        <Column header="상태" body-class="text-center" style="width: 90px">
                            <template #body="{ data }">
                                <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                            </template>
                        </Column>
                        <Column header="결과">
                            <template #body="{ data }">
                                <span v-if="data.status === 'failed'" class="text-red-500 text-sm">{{ data.error }}</span>
                                <span v-else class="text-sm">{{ reportSummary(data) }}</span>
                            </template>
                        </Column>
                        <Column header="업로더" style="width: 100px">
                            <template #body="{ data }">{{ data.creator?.name ?? '-' }}</template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
