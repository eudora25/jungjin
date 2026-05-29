<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
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
}>();

const targetTypes = [
    { label: '약국', value: 'pharmacy' },
    { label: '병의원', value: 'hospital' },
];
const requestTypes = [
    { label: '신규 등록', value: 'create' },
    { label: '정보 수정', value: 'update' },
];
const hospitalTypes = [
    { label: '종합병원', value: 'general_hospital' },
    { label: '병원', value: 'hospital' },
    { label: '의원', value: 'clinic' },
    { label: '치과', value: 'dental' },
    { label: '한의원', value: 'oriental' },
];

const targetTypeLabel = (v: string) => (v === 'hospital' ? '병의원' : '약국');
const requestTypeLabel = (v: string) => (v === 'update' ? '수정' : '신규');
const statusMeta: Record<string, { label: string; severity: string }> = {
    pending: { label: '검토 대기', severity: 'warn' },
    approved: { label: '승인', severity: 'success' },
    rejected: { label: '반려', severity: 'danger' },
};

const displayName = (r: MasterRequest) =>
    r.payload?.pharmacy_name ?? r.payload?.hospital_name ?? '-';

const dialogOpen = ref(false);

const form = useForm<{
    target_type: 'pharmacy' | 'hospital';
    request_type: 'create' | 'update';
    target_id: number | null;
    payload: Record<string, string | null>;
    reason: string | null;
}>({
    target_type: 'pharmacy',
    request_type: 'create',
    target_id: null,
    payload: {},
    reason: null,
});

const isHospital = computed(() => form.target_type === 'hospital');
const isUpdate = computed(() => form.request_type === 'update');

// 대상 유형이 바뀌면 payload 초기화
watch(
    () => form.target_type,
    () => {
        form.payload = {};
    },
);

const openDialog = () => {
    form.reset();
    form.clearErrors();
    form.payload = {};
    dialogOpen.value = true;
};

const submit = () => {
    form.post(route('master-change-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
            form.payload = {};
        },
    });
};

const onPage = (e: { page: number }) => {
    window.location.href = route('master-change-requests.index', { page: e.page + 1 });
};
</script>

<template>
    <Head title="마스터 변경요청" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">마스터 변경요청</h1>
                    <p class="text-surface-500 mt-1 text-sm">
                        공유 마스터(약국·병의원) 등록·수정은 운영자 승인 후 반영됩니다 — 전체 {{ requests.total }}건
                    </p>
                </div>
                <Button label="변경요청 작성" icon="pi pi-plus" @click="openDialog" />
            </div>

            <DataTable :value="requests.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">제출한 변경요청이 없습니다.</div>
                </template>
                <Column header="대상" style="width: 200px">
                    <template #body="{ data }">
                        <span class="font-medium">{{ displayName(data) }}</span>
                        <div class="text-xs text-surface-400 mt-1">
                            {{ targetTypeLabel(data.target_type) }} · {{ requestTypeLabel(data.request_type) }}
                            <span v-if="data.target_id"> (#{{ data.target_id }})</span>
                        </div>
                    </template>
                </Column>
                <Column header="상태" style="width: 110px">
                    <template #body="{ data }">
                        <Tag :value="statusMeta[data.status]?.label" :severity="statusMeta[data.status]?.severity" />
                    </template>
                </Column>
                <Column header="검토 메모">
                    <template #body="{ data }">
                        <span v-if="data.review_note" class="text-sm">{{ data.review_note }}</span>
                        <span v-else class="text-surface-400 text-sm">-</span>
                    </template>
                </Column>
                <Column header="검토자" style="width: 140px">
                    <template #body="{ data }">{{ data.reviewer?.name ?? '-' }}</template>
                </Column>
            </DataTable>

            <Paginator :rows="requests.per_page" :total-records="requests.total"
                       :first="(requests.current_page - 1) * requests.per_page" @page="onPage" />
        </div>

        <Dialog v-model:visible="dialogOpen" modal header="변경요청 작성" :style="{ width: '32rem' }">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-1">대상 유형</label>
                        <Select v-model="form.target_type" :options="targetTypes" option-label="label"
                                option-value="value" class="w-full" />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-1">요청 유형</label>
                        <Select v-model="form.request_type" :options="requestTypes" option-label="label"
                                option-value="value" class="w-full" />
                    </div>
                </div>

                <div v-if="isUpdate">
                    <label class="block text-sm font-medium mb-1">수정 대상 ID</label>
                    <InputNumber v-model="form.target_id" :use-grouping="false" class="w-full"
                                 placeholder="수정할 마스터의 ID" />
                    <small v-if="form.errors.target_id" class="text-red-500">{{ form.errors.target_id }}</small>
                    <small v-else class="text-surface-400">목록 화면에서 확인한 약국/병의원 ID 를 입력하세요.</small>
                </div>

                <!-- 약국 필드 -->
                <template v-if="!isHospital">
                    <div>
                        <label class="block text-sm font-medium mb-1">약국명 <span class="text-red-500">*</span></label>
                        <InputText v-model="form.payload.pharmacy_name" class="w-full" />
                        <small v-if="form.errors['payload.pharmacy_name']" class="text-red-500">
                            {{ form.errors['payload.pharmacy_name'] }}
                        </small>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1">사업자등록번호</label>
                            <InputText v-model="form.payload.business_registration_number" class="w-full" />
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1">대표자명</label>
                            <InputText v-model="form.payload.representative_name" class="w-full" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">주소</label>
                        <InputText v-model="form.payload.address" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">연락처</label>
                        <InputText v-model="form.payload.contact_phone" class="w-full" />
                    </div>
                </template>

                <!-- 병의원 필드 -->
                <template v-else>
                    <div>
                        <label class="block text-sm font-medium mb-1">병의원명 <span class="text-red-500">*</span></label>
                        <InputText v-model="form.payload.hospital_name" class="w-full" />
                        <small v-if="form.errors['payload.hospital_name']" class="text-red-500">
                            {{ form.errors['payload.hospital_name'] }}
                        </small>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1">유형</label>
                            <Select v-model="form.payload.hospital_type" :options="hospitalTypes" option-label="label"
                                    option-value="value" class="w-full" show-clear />
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1">진료과목</label>
                            <InputText v-model="form.payload.specialty" class="w-full" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">사업자등록번호</label>
                        <InputText v-model="form.payload.business_registration_number" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">주소</label>
                        <InputText v-model="form.payload.address" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">연락처</label>
                        <InputText v-model="form.payload.phone" class="w-full" />
                    </div>
                </template>

                <div>
                    <label class="block text-sm font-medium mb-1">요청 사유 (선택)</label>
                    <Textarea v-model="form.reason" rows="2" class="w-full" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" text @click="dialogOpen = false" />
                    <Button label="요청 제출" icon="pi pi-send" type="submit" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
