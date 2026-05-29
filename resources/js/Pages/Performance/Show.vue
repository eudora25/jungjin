<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Timeline from 'primevue/timeline';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';
import { ref } from 'vue';

type Status = 'draft' | 'submitted' | 'reviewed' | 'approved' | 'rejected' | 'cancelled';

interface Performance {
    id: number;
    performance_no: string;
    performance_date: string;
    quantity: number;
    unit_price: string;
    commission_rate: string | null;
    subtotal: string | null;
    commission_amount: string | null;
    price_source: 'override' | 'product_sale' | 'products_price' | 'manual';
    commission_source: 'override' | 'matrix' | 'manual' | 'none';
    status: Status;
    note: string | null;
    company: { id: number; company_name: string; business_registration_number: string | null; default_commission_grade: string | null };
    product: { id: number; product_name: string; insurance_code: string; product_code: string; approval_status: string; status: string };
    price_override: { id: number } | null;
    price_record: { id: number; price_type: string; amount: string; effective_from: string } | null;
    commission_rate_record: { id: number; base_month: string } | null;
    creator: { id: number; name: string } | null;
    updater: { id: number; name: string } | null;
    submitter: { id: number; name: string } | null;
    reviewer: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    approved_at: string | null;
    rejected_reason: string | null;
    created_at: string;
    updated_at: string;
    files: PerformanceFile[];
}

interface PerformanceFile {
    id: number;
    original_name: string;
    size: number;
    extension: string | null;
}

interface Activity {
    id: number;
    log_name: string;
    description: string;
    event: string | null;
    causer: { id: number; name: string } | null;
    properties: { reason?: string; attributes?: Record<string, unknown>; old?: Record<string, unknown> };
    created_at: string;
}

const props = defineProps<{
    performance: Performance;
    activities: Activity[];
    can: {
        update: boolean;
        delete: boolean;
        submit: boolean;
        review: boolean;
        approve: boolean;
        reject: boolean;
        cancel: boolean;
    };
}>();

const confirm = useConfirm();

const rejectOpen = ref(false);
const rejectForm = useForm({ reason: '' });

const fileInput = ref<HTMLInputElement | null>(null);
const fileForm = useForm({ file: null as File | null });

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    fileForm.file = input.files?.[0] ?? null;
};

const uploadFile = () => {
    fileForm.post(route('performance.files.store', props.performance.id), {
        preserveScroll: true,
        onSuccess: () => {
            fileForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const deleteFile = (fileId: number) => {
    router.delete(route('performance.files.destroy', [props.performance.id, fileId]), {
        preserveScroll: true,
    });
};

const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const postAction = (name: string) => {
    router.post(route(name, props.performance.id), {}, { preserveScroll: true });
};

const openReject = () => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectOpen.value = true;
};

const submitReject = () => {
    rejectForm.post(route('performance.reject', props.performance.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejectOpen.value = false;
        },
    });
};

const onCancel = () => {
    confirm.require({
        message: '이 실적을 취소(무효) 처리하시겠습니까? 승인된 건도 정산 전제가 바뀔 수 있습니다.',
        header: '취소 확인',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { severity: 'danger', label: '취소 처리' },
        rejectProps: { severity: 'secondary', outlined: true, label: '닫기' },
        accept: () => {
            postAction('performance.cancel');
        },
    });
};

const statusSeverity: Record<Status, 'info' | 'warn' | 'success' | 'secondary' | 'danger' | 'contrast'> = {
    draft: 'secondary',
    submitted: 'info',
    reviewed: 'warn',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'contrast',
};

const statusLabel: Record<Status, string> = {
    draft: '초안',
    submitted: '제출됨',
    reviewed: '검수됨',
    approved: '승인됨',
    rejected: '반려',
    cancelled: '취소',
};

const priceSourceLabel: Record<Performance['price_source'], string> = {
    override: '거래처 예외 단가',
    product_sale: '제품 매출가',
    products_price: '제품 기본 약가(fallback)',
    manual: '수동 입력',
};

const commissionSourceLabel: Record<Performance['commission_source'], string> = {
    override: '거래처 예외 수수료율',
    matrix: '등급 매트릭스',
    manual: '수동 입력',
    none: '없음',
};

const fmt = (v: string | number | null | undefined): string => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'number' ? v : parseFloat(v);
    if (Number.isNaN(n)) return '-';
    return n.toLocaleString('ko-KR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};

const onDelete = () => {
    confirm.require({
        message: `실적 ${props.performance.performance_no} 을(를) 삭제하시겠습니까?`,
        header: '삭제 확인',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { severity: 'danger', label: '삭제' },
        rejectProps: { severity: 'secondary', outlined: true, label: '취소' },
        accept: () => {
            router.delete(route('performance.destroy', props.performance.id));
        },
    });
};
</script>

<template>
    <Head :title="`실적 ${performance.performance_no}`" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <Link :href="route('performance.index')" class="text-primary-600 text-sm">
                <i class="pi pi-chevron-left" /> 실적 목록
            </Link>

            <div class="card">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h1 class="text-2xl font-bold">{{ performance.performance_no }}</h1>
                            <Tag :value="statusLabel[performance.status]" :severity="statusSeverity[performance.status]" />
                        </div>
                        <p class="text-surface-500 text-sm">
                            {{ performance.performance_date }} · 등록자: {{ performance.creator?.name ?? '-' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 justify-end">
                        <Button v-if="can.submit" icon="pi pi-send" label="제출" severity="success"
                                @click="postAction('performance.submit')" />
                        <Button v-if="can.review" icon="pi pi-check-square" label="검수 완료" severity="warn"
                                @click="postAction('performance.review')" />
                        <Button v-if="can.approve" icon="pi pi-verified" label="승인" severity="success"
                                @click="postAction('performance.approve')" />
                        <Button v-if="can.reject" icon="pi pi-times-circle" label="반려" severity="danger" outlined
                                @click="openReject" />
                        <Button v-if="can.cancel" icon="pi pi-ban" label="취소(무효)" severity="secondary" outlined
                                @click="onCancel" />
                        <Link v-if="can.update" :href="route('performance.edit', performance.id)">
                            <Button icon="pi pi-pencil" label="수정" severity="info" outlined />
                        </Link>
                        <Button v-if="can.delete" icon="pi pi-trash" label="삭제" severity="danger" outlined
                                @click="onDelete" />
                    </div>
                </div>

                <div v-if="performance.rejected_reason" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                    <div class="text-xs text-red-700 font-medium mb-1">반려 사유</div>
                    <div class="text-sm text-red-900 whitespace-pre-wrap">{{ performance.rejected_reason }}</div>
                </div>

                <!-- Core snapshot -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <div class="text-xs text-surface-500">거래처</div>
                        <div class="font-semibold">{{ performance.company.company_name }}</div>
                        <div class="text-xs text-surface-400">
                            {{ performance.company.business_registration_number ?? '' }}
                            <span v-if="performance.company.default_commission_grade">
                                · 등급 {{ performance.company.default_commission_grade.toUpperCase() }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">제품</div>
                        <div class="font-semibold">{{ performance.product.product_name }}</div>
                        <div class="text-xs text-surface-400">
                            {{ performance.product.insurance_code }} · {{ performance.product.product_code }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">수량</div>
                        <div class="font-semibold">{{ fmt(performance.quantity) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">실적일</div>
                        <div class="font-semibold">{{ performance.performance_date }}</div>
                    </div>
                </div>

                <div class="border border-surface-200 rounded-lg p-4 bg-surface-50 mb-6">
                    <h2 class="font-semibold mb-3">스냅샷</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-surface-500">단가</div>
                            <div class="text-lg font-bold">{{ fmt(performance.unit_price) }}원</div>
                            <Tag :value="priceSourceLabel[performance.price_source]" severity="info" class="mt-1" />
                        </div>
                        <div>
                            <div class="text-xs text-surface-500">수수료율</div>
                            <div class="text-lg font-bold">
                                <span v-if="performance.commission_rate !== null">{{ performance.commission_rate }}%</span>
                                <span v-else class="text-surface-400">없음</span>
                            </div>
                            <Tag :value="commissionSourceLabel[performance.commission_source]"
                                 :severity="performance.commission_source === 'none' ? 'secondary' : 'info'"
                                 class="mt-1" />
                        </div>
                        <div>
                            <div class="text-xs text-surface-500">매출 (GENERATED)</div>
                            <div class="text-lg font-bold">{{ fmt(performance.subtotal) }}원</div>
                        </div>
                        <div>
                            <div class="text-xs text-surface-500">수수료 (GENERATED)</div>
                            <div class="text-lg font-bold">
                                <span v-if="performance.commission_amount !== null">{{ fmt(performance.commission_amount) }}원</span>
                                <span v-else class="text-surface-400">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="performance.note" class="mb-6">
                    <div class="text-xs text-surface-500 mb-1">비고</div>
                    <div class="whitespace-pre-wrap">{{ performance.note }}</div>
                </div>

                <!-- 증빙 파일 -->
                <div class="mb-6">
                    <div class="text-xs text-surface-500 mb-2">증빙 파일 ({{ performance.files.length }}/5)</div>
                    <ul v-if="performance.files.length" class="flex flex-col gap-2 mb-3">
                        <li v-for="file in performance.files" :key="file.id"
                            class="flex items-center justify-between border border-surface rounded-lg px-3 py-2 bg-surface-50 dark:bg-surface-900">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="pi pi-file text-surface-400" />
                                <span class="truncate text-sm">{{ file.original_name }}</span>
                                <span class="text-xs text-surface-400 shrink-0">{{ formatSize(file.size) }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="route('performance.files.download', [performance.id, file.id])"
                                   class="text-primary text-sm hover:underline">다운로드</a>
                                <Button v-if="can.update" icon="pi pi-times" text size="small" severity="danger"
                                        @click="deleteFile(file.id)" />
                            </div>
                        </li>
                    </ul>
                    <form v-if="can.update && performance.files.length < 5"
                          @submit.prevent="uploadFile" class="flex items-center gap-2">
                        <input type="file" ref="fileInput"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.hwp"
                               class="text-sm" @change="onFileChange" />
                        <Button type="submit" label="첨부" icon="pi pi-upload" size="small"
                                severity="secondary" outlined :loading="fileForm.processing" :disabled="!fileForm.file" />
                    </form>
                    <small v-if="fileForm.errors.file" class="text-red-500 block mt-1">{{ fileForm.errors.file }}</small>
                </div>

                <div class="mb-2 text-sm text-surface-500">
                    <span v-if="performance.updater">수정: {{ performance.updater.name }} · {{ performance.updated_at }}</span>
                </div>
            </div>

            <Dialog v-model:visible="rejectOpen" modal header="실적 반려" class="w-full max-w-md"
                    :dismissable-mask="true">
                <div class="flex flex-col gap-3">
                    <label class="text-sm">반려 사유 *</label>
                    <Textarea v-model="rejectForm.reason" rows="4" class="w-full" :invalid="!!rejectForm.errors.reason" />
                    <small v-if="rejectForm.errors.reason" class="text-red-500">{{ rejectForm.errors.reason }}</small>
                    <div class="flex justify-end gap-2 mt-2">
                        <Button label="닫기" severity="secondary" outlined @click="rejectOpen = false" />
                        <Button label="반려 처리" icon="pi pi-times" severity="danger" :loading="rejectForm.processing"
                                @click="submitReject" />
                    </div>
                </div>
            </Dialog>

            <div class="card">
                <h2 class="text-lg font-semibold mb-3">변경 이력</h2>
                <div v-if="activities.length === 0" class="text-sm text-surface-500">변경 이력이 없습니다.</div>
                <Timeline v-else :value="activities">
                    <template #content="{ item }">
                        <div class="pb-4">
                            <div class="flex items-center gap-2">
                                <Tag :value="item.log_name" severity="info" />
                                <span class="text-sm font-medium">{{ item.description }}</span>
                                <span class="text-xs text-surface-400">{{ item.created_at }}</span>
                            </div>
                            <div v-if="item.properties?.reason" class="mt-1 text-sm">
                                사유: <span class="italic">{{ item.properties.reason }}</span>
                            </div>
                            <div class="text-xs text-surface-400 mt-1">
                                {{ item.causer?.name ?? 'system' }}
                            </div>
                        </div>
                    </template>
                </Timeline>
            </div>
        </div>
    </AdminLayout>
</template>
