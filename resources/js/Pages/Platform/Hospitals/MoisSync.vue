<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';

interface ServiceRow {
    key: string;
    id: string;
    label: string;
    last_synced_at: string | null;
    last_run_at: string | null;
    last_run_status: string | null;
}

interface SyncRow {
    id: number;
    trigger: 'schedule' | 'manual';
    status: 'pending' | 'processing' | 'completed' | 'failed';
    params: Record<string, unknown> | null;
    report: Record<string, Record<string, number | string | boolean>> | null;
    error: string | null;
    created_at: string;
    finished_at: string | null;
    creator: { id: number; name: string } | null;
}

const props = defineProps<{
    syncs: SyncRow[];
    services: ServiceRow[];
    enabled: boolean;
}>();

const form = useForm<{ services: string[]; dry_run: boolean }>({
    services: props.services.map((s) => s.key),
    dry_run: false,
});

// --- 진행 모달 상태 ---
const dialogVisible = ref(false);
const starting = ref(false);
const syncPhase = ref<'running' | 'failed' | 'timeout'>('running');
const syncError = ref<string | null>(null);
const startError = ref<string | null>(null);
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let pollAttempts = 0;
const POLL_INTERVAL_MS = 1500;
const POLL_MAX_ATTEMPTS = 400; // 약 10분 안전 상한

const labelsFor = (keys: string[]): string =>
    props.services
        .filter((s) => keys.includes(s.key))
        .map((s) => s.label)
        .join(', ');

const targetLabels = ref('');

const stopPolling = () => {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
};

const finishWithList = () => {
    stopPolling();
    dialogVisible.value = false;
    router.reload({ only: ['syncs', 'services'] });
};

const pollStatus = async (id: number) => {
    pollAttempts += 1;
    try {
        const { data } = await window.axios.get(route('platform.hospitals.mois-sync.status', id));
        if (data.status === 'completed') {
            finishWithList();
            return;
        }
        if (data.status === 'failed') {
            syncPhase.value = 'failed';
            syncError.value = data.error ?? '동기화에 실패했습니다.';
            stopPolling();
            router.reload({ only: ['syncs', 'services'] });
            return;
        }
    } catch {
        // 일시적 오류는 무시하고 다음 폴링 재시도
    }
    if (pollAttempts >= POLL_MAX_ATTEMPTS) {
        syncPhase.value = 'timeout';
        stopPolling();
        return;
    }
    pollTimer = setTimeout(() => pollStatus(id), POLL_INTERVAL_MS);
};

const submit = async () => {
    if (form.services.length === 0 || starting.value) return;
    starting.value = true;
    startError.value = null;
    syncError.value = null;
    syncPhase.value = 'running';
    pollAttempts = 0;
    try {
        const { data } = await window.axios.post(route('platform.hospitals.mois-sync.store'), {
            services: form.services,
            dry_run: form.dry_run,
        });
        targetLabels.value = labelsFor(form.services);
        dialogVisible.value = true;
        pollStatus(data.id);
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        startError.value = err.response?.data?.message ?? '동기화 시작에 실패했습니다.';
    } finally {
        starting.value = false;
    }
};

const closeDialog = () => {
    stopPolling();
    dialogVisible.value = false;
};

onBeforeUnmount(stopPolling);

const refresh = () => router.reload({ only: ['syncs', 'services'] });

const statusSeverity = (s: string) =>
    ({ pending: 'secondary', processing: 'info', completed: 'success', failed: 'danger' })[s] ?? 'secondary';

const statusLabel = (s: string) =>
    ({ pending: '대기', processing: '처리중', completed: '완료', failed: '실패' })[s] ?? s;

const triggerLabel = (t: string) => (t === 'schedule' ? '스케줄' : '수동');

// 커서 yyyyMMddHHmmss → 'yyyy-MM-dd HH:mm' 표시
const formatPoint = (p: string | null): string => {
    if (!p || p.length < 12) return '-';
    return `${p.slice(0, 4)}-${p.slice(4, 6)}-${p.slice(6, 8)} ${p.slice(8, 10)}:${p.slice(10, 12)}`;
};

// ISO 일시 → 'yyyy-MM-dd HH:mm' (브라우저 로컬 시간)
const formatDateTime = (iso: string | null): string => {
    if (!iso) return '-';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '-';
    const p = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
};

const reportSummary = (row: SyncRow): string => {
    if (!row.report) return '-';
    const parts: string[] = [];
    for (const [svc, r] of Object.entries(row.report)) {
        if (r.failed === true || 'error' in r) {
            parts.push(`${svc}: 실패`);
            continue;
        }
        parts.push(`${svc}: +${r.inserted ?? 0}/~${r.updated ?? 0}/✕${r.closed ?? 0}/=${r.skipped ?? 0}`);
    }
    return parts.join('  ·  ');
};

const isDryRun = (row: SyncRow): boolean => row.params?.dry_run === true;
</script>

<template>
    <Head title="병의원 API 동기화(MOIS)" />
    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold">병의원 행안부(MOIS) API 증분 동기화</h1>
                <p class="text-surface-500 text-sm mt-1">
                    공공데이터포털(data.go.kr) 변경분(신규·변경·폐업)만 받아 병의원 마스터에 반영합니다.
                </p>
            </div>

            <Message :severity="enabled ? 'success' : 'warn'" :closable="false">
                <span class="text-sm">
                    자동 스케줄(매일 04:30):
                    <b>{{ enabled ? '활성' : '비활성' }}</b>
                    <template v-if="!enabled"> — 현재는 수동 트리거로만 동작합니다 (운영 검증 후 활성화).</template>
                </span>
            </Message>

            <Card>
                <template #title>업종별 커서</template>
                <template #content>
                    <DataTable :value="services" striped-rows>
                        <Column header="업종" field="label" style="width: 160px" />
                        <Column header="API ID" field="id" style="width: 120px" />
                        <Column header="최신 데이터 시점 (게시 기준)">
                            <template #body="{ data }">
                                <span :class="data.last_synced_at ? '' : 'text-surface-400'">
                                    {{ formatPoint(data.last_synced_at) }}
                                </span>
                            </template>
                        </Column>
                        <Column header="마지막 실행" style="width: 220px">
                            <template #body="{ data }">
                                <span v-if="!data.last_run_at && !data.last_run_status" class="text-surface-400">-</span>
                                <span v-else class="flex items-center gap-2">
                                    <span :class="data.last_run_at ? '' : 'text-surface-400'">
                                        {{ formatDateTime(data.last_run_at) }}
                                    </span>
                                    <Tag
                                        v-if="data.last_run_status"
                                        :value="statusLabel(data.last_run_status)"
                                        :severity="statusSeverity(data.last_run_status)"
                                    />
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                    <p class="text-surface-500 text-xs mt-2">
                        ※ <b>최신 데이터 시점</b>은 받아온 레코드 중 가장 최근 게시시각(DAT_UPDT_PNT)이며, 실행 시각이 아닙니다.
                        포털에 새 게시분이 없으면 동기화를 실행해도 이 값은 변하지 않습니다.
                    </p>
                </template>
            </Card>

            <Card>
                <template #title>지금 동기화</template>
                <template #content>
                    <form class="flex flex-col gap-4" @submit.prevent="submit">
                        <div>
                            <label class="block text-sm mb-2">대상 업종</label>
                            <div class="flex flex-wrap gap-4">
                                <div v-for="s in services" :key="s.key" class="flex items-center gap-2">
                                    <Checkbox v-model="form.services" :input-id="`svc-${s.key}`" :value="s.key" />
                                    <label :for="`svc-${s.key}`" class="text-sm">{{ s.label }}</label>
                                </div>
                            </div>
                            <Message v-if="form.errors.services" severity="error" size="small" variant="simple">
                                {{ form.errors.services }}
                            </Message>
                        </div>
                        <div class="flex items-center gap-2">
                            <ToggleSwitch v-model="form.dry_run" input-id="dry-run" />
                            <label for="dry-run" class="text-sm">모의 실행(dry-run) — 분류 카운트만, DB 미반영</label>
                        </div>
                        <div>
                            <Button type="submit" label="동기화 시작" icon="pi pi-sync" :loading="starting"
                                    :disabled="form.services.length === 0" />
                            <span class="text-surface-500 text-xs ml-3">진행 상황이 모달로 표시되며, 완료되면 자동으로 닫힙니다.</span>
                        </div>
                        <Message v-if="startError" severity="error" size="small" variant="simple">
                            {{ startError }}
                        </Message>
                    </form>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>최근 동기화 이력</span>
                        <Button label="새로고침" icon="pi pi-refresh" size="small" text @click="refresh" />
                    </div>
                </template>
                <template #content>
                    <DataTable :value="syncs" striped-rows>
                        <template #empty>
                            <div class="text-center py-8 text-surface-500">아직 동기화 이력이 없습니다.</div>
                        </template>
                        <Column header="#" field="id" style="width: 60px" />
                        <Column header="트리거" style="width: 100px">
                            <template #body="{ data }">
                                {{ triggerLabel(data.trigger) }}
                                <Tag v-if="isDryRun(data)" value="dry-run" severity="secondary" class="ml-1" />
                            </template>
                        </Column>
                        <Column header="상태" style="width: 90px">
                            <template #body="{ data }">
                                <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                            </template>
                        </Column>
                        <Column header="결과 (+신규/~변경/✕폐업/=스킵)">
                            <template #body="{ data }">
                                <span v-if="data.status === 'failed' && data.error" class="text-red-500 text-sm">{{ data.error }}</span>
                                <span v-else class="text-sm">{{ reportSummary(data) }}</span>
                            </template>
                        </Column>
                        <Column header="실행자" style="width: 100px">
                            <template #body="{ data }">{{ data.creator?.name ?? '스케줄' }}</template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog
            v-model:visible="dialogVisible"
            modal
            :closable="syncPhase !== 'running'"
            :close-on-escape="syncPhase !== 'running'"
            :draggable="false"
            :style="{ width: '28rem' }"
            header="MOIS 동기화"
        >
            <div v-if="syncPhase === 'running'" class="flex flex-col items-center gap-4 py-4 text-center">
                <ProgressSpinner style="width: 48px; height: 48px" stroke-width="4" />
                <div>
                    <p class="font-medium">동기화를 진행 중입니다…</p>
                    <p class="text-surface-500 text-sm mt-1">대상: {{ targetLabels || '전체' }}</p>
                    <p class="text-surface-400 text-xs mt-2">완료되면 이 창은 자동으로 닫힙니다.</p>
                </div>
            </div>

            <div v-else-if="syncPhase === 'failed'" class="flex flex-col gap-3 py-2">
                <Message severity="error" :closable="false">동기화에 실패했습니다.</Message>
                <p v-if="syncError" class="text-sm text-surface-600 break-all">{{ syncError }}</p>
            </div>

            <div v-else class="flex flex-col gap-3 py-2">
                <Message severity="warn" :closable="false">
                    완료 확인 시간이 초과되었습니다. 큐 워커 동작 여부를 확인하고, 아래 이력에서 최종 상태를 확인하세요.
                </Message>
            </div>

            <template v-if="syncPhase !== 'running'" #footer>
                <Button label="닫기" text @click="closeDialog" />
            </template>
        </Dialog>
    </AdminLayout>
</template>
