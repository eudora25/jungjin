<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Paginator from 'primevue/paginator';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

interface MasterRequest {
    id: number;
    target_type: 'pharmacy' | 'hospital';
    request_type: 'create' | 'update';
    target_id: number | null;
    payload: Record<string, string>;
    status: 'pending' | 'approved' | 'rejected';
    review_note: string | null;
    reviewed_at: string | null;
    created_at: string;
    tenant: { id: number; name: string } | null;
    requester: { id: number; name: string } | null;
    reviewer: { id: number; name: string } | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    requests: Paginated<MasterRequest>;
    filters: { status: string };
}>();

const statusOptions = [
    { label: '검토 대기', value: 'pending' },
    { label: '승인', value: 'approved' },
    { label: '반려', value: 'rejected' },
];
const statusMeta: Record<string, { label: string; severity: string }> = {
    pending: { label: '검토 대기', severity: 'warn' },
    approved: { label: '승인', severity: 'success' },
    rejected: { label: '반려', severity: 'danger' },
};

const status = ref(props.filters.status ?? 'pending');

const targetTypeLabel = (v: string) => (v === 'hospital' ? '병의원' : '약국');
const requestTypeLabel = (v: string) => (v === 'update' ? '수정' : '신규');
const displayName = (r: MasterRequest) =>
    r.payload?.pharmacy_name ?? r.payload?.hospital_name ?? '-';

// payload 를 key: value 목록으로 (이름 필드는 제외하고 부가 정보만)
const payloadEntries = (r: MasterRequest) =>
    Object.entries(r.payload ?? {}).filter(
        ([k, v]) => v != null && v !== '' && k !== 'pharmacy_name' && k !== 'hospital_name',
    );

const filterByStatus = (next: string) => {
    status.value = next;
    router.get(
        route('platform.master-requests.index'),
        { status: next },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const onPage = (e: { page: number }) => {
    router.get(
        route('platform.master-requests.index'),
        { status: status.value, page: e.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const approve = (r: MasterRequest) => {
    router.post(route('platform.master-requests.approve', r.id), {}, { preserveScroll: true });
};

// 반려 다이얼로그
const rejectTarget = ref<MasterRequest | null>(null);
const rejectForm = useForm({ review_note: '' });

const openReject = (r: MasterRequest) => {
    rejectTarget.value = r;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const submitReject = () => {
    if (!rejectTarget.value) return;
    rejectForm.post(route('platform.master-requests.reject', rejectTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejectTarget.value = null;
        },
    });
};
</script>

<template>
    <Head title="변경요청 검토" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-2xl font-bold">변경요청 검토</h1>
                <p class="text-surface-500 mt-1 text-sm">
                    제약사가 제출한 공유 마스터(약국·병의원) 변경요청 — {{ requests.total }}건
                </p>
            </div>

            <SelectButton :model-value="status" :options="statusOptions" option-label="label" option-value="value"
                          :allow-empty="false" @update:model-value="filterByStatus" />

            <DataTable :value="requests.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">해당 상태의 변경요청이 없습니다.</div>
                </template>
                <Column header="제약사" style="width: 150px">
                    <template #body="{ data }">{{ data.tenant?.name ?? '-' }}</template>
                </Column>
                <Column header="대상">
                    <template #body="{ data }">
                        <span class="font-medium">{{ displayName(data) }}</span>
                        <div class="text-xs text-surface-400 mt-1">
                            {{ targetTypeLabel(data.target_type) }} · {{ requestTypeLabel(data.request_type) }}
                            <span v-if="data.target_id"> (#{{ data.target_id }})</span>
                        </div>
                        <div v-if="payloadEntries(data).length" class="text-xs text-surface-500 mt-1">
                            <span v-for="[k, v] in payloadEntries(data)" :key="k" class="mr-2">{{ k }}: {{ v }}</span>
                        </div>
                    </template>
                </Column>
                <Column header="요청자" style="width: 120px">
                    <template #body="{ data }">{{ data.requester?.name ?? '-' }}</template>
                </Column>
                <Column header="상태" body-class="text-center" style="width: 100px">
                    <template #body="{ data }">
                        <Tag :value="statusMeta[data.status]?.label" :severity="statusMeta[data.status]?.severity" />
                        <div v-if="data.review_note" class="text-xs text-surface-400 mt-1">{{ data.review_note }}</div>
                    </template>
                </Column>
                <Column header="" style="width: 160px">
                    <template #body="{ data }">
                        <div v-if="data.status === 'pending'" class="flex gap-2">
                            <Button label="승인" icon="pi pi-check" size="small" severity="success"
                                    @click="approve(data)" />
                            <Button label="반려" icon="pi pi-times" size="small" severity="danger" outlined
                                    @click="openReject(data)" />
                        </div>
                        <span v-else class="text-surface-400 text-sm">
                            {{ data.reviewer?.name ?? '' }}
                        </span>
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="requests.per_page" :total-records="requests.total"
                       :first="(requests.current_page - 1) * requests.per_page" @page="onPage" />
        </div>

        <Dialog :visible="rejectTarget !== null" modal header="변경요청 반려" :style="{ width: '28rem' }"
                @update:visible="rejectTarget = null">
            <div class="flex flex-col gap-3">
                <p class="text-sm text-surface-600">
                    <span class="font-medium">{{ rejectTarget ? displayName(rejectTarget) : '' }}</span> 요청을 반려합니다.
                </p>
                <div>
                    <label class="block text-sm font-medium mb-1">반려 사유 (선택)</label>
                    <Textarea v-model="rejectForm.review_note" rows="3" class="w-full" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" text @click="rejectTarget = null" />
                    <Button label="반려 처리" icon="pi pi-times" severity="danger" :loading="rejectForm.processing"
                            @click="submitReject" />
                </div>
            </div>
        </Dialog>
    </AdminLayout>
</template>
