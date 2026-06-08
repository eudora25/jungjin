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

const KIND_LABELS: Record<string, string> = {
    institution: '병원정보(B-1) — ykiho 매칭',
    facilities: '시설·병상',
    hours: '진료시간·편의',
    specialties: '진료과목',
    transports: '교통',
    equipments: '의료장비',
    meal_surcharges: '식대가산',
    nursing_grades: '간호등급',
    special_treatments: '특수진료',
    specialized_fields: '전문병원지정분야',
    other_staff: '기타인력',
};

const kindLabel = (k: string) => KIND_LABELS[k] ?? k;

const kindOptions = computed(() => props.kinds.map((k) => ({ value: k, label: kindLabel(k) })));

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
        const conflict = Number(r.conflict ?? 0) > 0 ? ` / ykiho 충돌 ${r.conflict}` : '';
        return `총 ${r.total} / 매칭 ${r.matched} / 미매칭 ${r.unmatched}${conflict} (매칭률 ${r.match_rate}%)`;
    }
    return `총 ${r.total} / 연결 ${r.resolved} / 스킵 ${r.skipped_unmatched} / 적재 ${r.written}`;
};

const refresh = () => router.reload({ only: ['imports', 'stats'] });
</script>

<template>
    <Head title="병의원 보강(공공데이터)" />
    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl mx-auto">
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
                        <li><b>병원정보(B-1)</b> 업로드 → 기관명+우편번호로 매칭해 <code>ykiho</code> 부착 (가장 먼저)</li>
                        <li>상세 유형(진료과목·장비·시설·진료시간 등)을 각각 업로드 → 정규화 적재</li>
                    </ol>
                    <p class="mt-1 text-surface-500">대용량 파일은 백그라운드(큐)에서 처리되며, 아래 목록에서 상태를 확인합니다.</p>
                </div>
            </Message>

            <Card>
                <template #title>보강 파일 업로드</template>
                <template #content>
                    <form class="flex flex-col gap-3 md:flex-row md:items-end" @submit.prevent="submit">
                        <div class="flex-1">
                            <label class="block text-sm mb-1">적재 유형 <span class="text-red-500">*</span></label>
                            <Select v-model="form.kind" :options="kindOptions" option-label="label" option-value="value"
                                    class="w-full" />
                            <Message v-if="form.errors.kind" severity="error" size="small" variant="simple">{{ form.errors.kind }}</Message>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm mb-1">Excel 파일 (.xlsx) <span class="text-red-500">*</span></label>
                            <input ref="fileInput" type="file" accept=".xlsx" class="w-full text-sm border border-surface-300 rounded p-2"
                                   @change="onFileChange" />
                            <Message v-if="form.errors.file" severity="error" size="small" variant="simple">{{ form.errors.file }}</Message>
                        </div>
                        <Button type="submit" label="업로드 & 적재" icon="pi pi-upload" :loading="form.processing"
                                :disabled="!form.file" />
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
                        <Column header="상태" style="width: 90px">
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
