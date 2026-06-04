<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';

interface ServiceRow {
    key: string;
    id: string;
    label: string;
    last_synced_at: string | null;
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

const submit = () => {
    form.post(route('platform.hospitals.mois-sync.store'), {
        preserveScroll: true,
    });
};

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
                        <Column header="업종" field="label" style="width: 200px" />
                        <Column header="API ID" field="id" style="width: 140px" />
                        <Column header="마지막 동기화 시점">
                            <template #body="{ data }">
                                <span :class="data.last_synced_at ? '' : 'text-surface-400'">
                                    {{ formatPoint(data.last_synced_at) }}
                                </span>
                            </template>
                        </Column>
                    </DataTable>
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
                            <Button type="submit" label="동기화 시작" icon="pi pi-sync" :loading="form.processing"
                                    :disabled="form.services.length === 0" />
                            <span class="text-surface-500 text-xs ml-3">백그라운드(큐)에서 처리됩니다. 잠시 후 새로고침하세요.</span>
                        </div>
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
    </AdminLayout>
</template>
