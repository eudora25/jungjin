<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';

interface ProductRef {
    id: number;
    product_name: string;
    insurance_code: string;
}

interface PerformanceRef {
    id: number;
    performance_no: string;
    product: ProductRef | null;
}

interface LineRow {
    id: number;
    performance_id: number;
    snapshot_unit_price: string | number;
    snapshot_commission_rate: string | number | null;
    quantity: number;
    subtotal: string | number;
    commission_amount: string | number | null;
    performance: PerformanceRef | null;
}

interface Settlement {
    id: number;
    settlement_no: string;
    period_month: string;
    status: string;
    line_count: number;
    total_quantity: number;
    total_subtotal: string | number;
    total_commission: string | number;
    calculated_at: string | null;
    paid_on: string | null;
    payment_method: string | null;
    payment_batch_no: string | null;
    payment_note: string | null;
    company: { id: number; company_name: string; business_registration_number: string | null };
    lines: LineRow[];
}

interface PaymentFile {
    id: number;
    original_name: string;
    size: number;
    mime_type: string;
    extension: string | null;
    uploaded_by_name: string | null;
    uploaded_at: string | null;
}

const props = defineProps<{
    settlement: Settlement;
    hasUnappliedPerformances: boolean;
    paymentFiles: PaymentFile[];
    can: {
        create: boolean;
        export: boolean;
        recalculate: boolean;
        confirm: boolean;
        pay: boolean;
        cancel: boolean;
        uploadPaymentFile: boolean;
    };
}>();

const confirm = useConfirm();

const fmt = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'number' ? v : parseFloat(String(v));
    if (Number.isNaN(n)) return '-';
    return n.toLocaleString('ko-KR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};

const statusLabel: Record<string, string> = {
    draft: '초안',
    confirmed: '확정',
    paid: '지급완료',
    cancelled: '취소',
};

const postAction = (name: string, id: number) => {
    router.post(route(name, id), {}, { preserveScroll: true });
};

const confirmAction = (message: string, action: () => void, danger = false) => {
    confirm.require({
        message,
        header: '확인',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { severity: danger ? 'danger' : 'info', label: '확인' },
        rejectProps: { severity: 'secondary', outlined: true, label: '취소' },
        accept: action,
    });
};

// ── GAP-5: 지급 처리 모달 ──────────────────────────────────────────────────────

const payDialogVisible = ref(false);

const todayStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const payForm = useForm({
    paid_on: todayStr(),
    payment_method: '' as '' | 'bank_transfer' | 'cash' | 'other',
    payment_batch_no: '',
    payment_note: '',
});

const paymentMethodOptions = [
    { label: '계좌이체', value: 'bank_transfer' },
    { label: '현금', value: 'cash' },
    { label: '기타', value: 'other' },
];

const paymentMethodLabel = (m: string | null) => {
    if (!m) return '-';
    return paymentMethodOptions.find((o) => o.value === m)?.label ?? m;
};

const openPayDialog = () => {
    payForm.reset();
    payForm.paid_on = todayStr();
    payDialogVisible.value = true;
};

const submitPay = () => {
    payForm.post(route('settlements.pay', props.settlement.id), {
        preserveScroll: true,
        onSuccess: () => {
            payDialogVisible.value = false;
            payForm.reset();
        },
    });
};

// ── GAP-5: 지급 증빙 파일 업로드 ───────────────────────────────────────────────

const fileForm = useForm({
    file: null as File | null,
});

const onFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    fileForm.file = target.files?.[0] ?? null;
};

const submitFileUpload = () => {
    if (!fileForm.file) return;
    fileForm.post(route('settlements.payment-files.store', props.settlement.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            fileForm.reset();
            const input = document.getElementById('payment-file-input') as HTMLInputElement | null;
            if (input) input.value = '';
        },
    });
};

const confirmFileDelete = (file: PaymentFile) => {
    confirm.require({
        message: `${file.original_name} 파일을 삭제하시겠습니까?`,
        header: '증빙 파일 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { severity: 'danger', label: '삭제' },
        rejectProps: { severity: 'secondary', outlined: true, label: '취소' },
        accept: () => router.delete(route('settlements.payment-files.destroy', [props.settlement.id, file.id]), { preserveScroll: true }),
    });
};

const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('ko-KR', { dateStyle: 'medium' }).format(new Date(iso)) : '-';
</script>

<template>
    <Head :title="`정산 ${settlement.settlement_no}`" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <Link :href="route('settlements.index')" class="text-primary-600 text-sm">
                <i class="pi pi-chevron-left" /> 정산 목록
            </Link>

            <div v-if="hasUnappliedPerformances"
                 class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle mt-0.5 shrink-0" />
                <span>마지막 계산 이후 새로 승인된 실적이 있습니다. 정산 금액이 실제와 다를 수 있으니 <strong>재계산</strong>을 확인하세요.</span>
            </div>

            <div class="card">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold">{{ settlement.settlement_no }}</h1>
                        <p class="text-surface-500 text-sm mt-1">
                            {{ settlement.period_month }} · {{ settlement.company.company_name }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Tag :value="statusLabel[settlement.status] ?? settlement.status" severity="info" />
                        <div class="flex gap-2">
                            <a v-if="can.export" :href="route('settlements.export.excel', settlement.id)" class="inline-block">
                                <Button icon="pi pi-file-excel" label="Excel" severity="secondary" outlined />
                            </a>
                            <a v-if="can.export" :href="route('settlements.export.pdf', settlement.id)" class="inline-block">
                                <Button icon="pi pi-file-pdf" label="PDF" severity="secondary" outlined />
                            </a>
                            <Button v-if="can.recalculate" icon="pi pi-refresh" label="재계산" severity="secondary" outlined
                                    @click="confirmAction('이 정산을 재계산할까요? (draft만 가능)', () => postAction('settlements.recalculate', settlement.id))" />
                            <Button v-if="can.confirm" icon="pi pi-check" label="확정" severity="info"
                                    @click="confirmAction('정산을 확정할까요? 확정 후에는 재계산이 제한됩니다.', () => postAction('settlements.confirm', settlement.id))" />
                            <Button v-if="can.pay" icon="pi pi-wallet" label="지급완료" severity="success"
                                    @click="openPayDialog" />
                            <Button v-if="can.cancel" icon="pi pi-ban" label="취소" severity="danger" outlined
                                    @click="confirmAction('정산을 취소할까요?', () => postAction('settlements.cancel', settlement.id), true)" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <div class="text-xs text-surface-500">라인 수</div>
                        <div class="text-lg font-semibold">{{ settlement.line_count }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">수량 합계</div>
                        <div class="text-lg font-semibold">{{ fmt(settlement.total_quantity) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">매출 합계</div>
                        <div class="text-lg font-semibold">{{ fmt(settlement.total_subtotal) }}원</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">수수료 합계</div>
                        <div class="text-lg font-semibold">{{ fmt(settlement.total_commission) }}원</div>
                    </div>
                </div>

                <h2 class="text-lg font-semibold mb-2">정산 라인 (실적 스냅샷 복사)</h2>
                <DataTable :value="settlement.lines" :striped-rows="true" data-key="id">
                    <Column header="실적번호" class="min-w-[120px]">
                        <template #body="{ data }">
                            <Link v-if="data.performance" :href="route('performance.show', data.performance.id)"
                                  class="text-primary-600 hover:underline text-sm">
                                {{ data.performance.performance_no }}
                            </Link>
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column header="제품" class="min-w-[160px]">
                        <template #body="{ data }">
                            <span v-if="data.performance?.product">{{ data.performance.product.product_name }}</span>
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column field="quantity" header="수량" class="text-right" />
                    <Column header="단가(스냅샷)" class="text-right">
                        <template #body="{ data }">{{ fmt(data.snapshot_unit_price) }}원</template>
                    </Column>
                    <Column header="매출" class="text-right">
                        <template #body="{ data }">{{ fmt(data.subtotal) }}원</template>
                    </Column>
                    <Column header="수수료" class="text-right">
                        <template #body="{ data }">
                            <span v-if="data.commission_amount !== null">{{ fmt(data.commission_amount) }}원</span>
                            <span v-else class="text-surface-400">-</span>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="py-6 text-center text-surface-500 text-sm">집계된 라인이 없습니다. 해당 월에 승인된 실적이 있는지 확인하세요.</div>
                    </template>
                </DataTable>
            </div>

            <!-- GAP-5: 지급 정보 카드 (paid 또는 지급 정보 일부라도 있을 때 표시) -->
            <div v-if="settlement.status === 'paid' || settlement.paid_on || settlement.payment_batch_no" class="card">
                <h2 class="text-lg font-semibold mb-3">지급 정보</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-surface-500">지급일</div>
                        <div class="font-medium mt-1">{{ settlement.paid_on ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">지급 수단</div>
                        <div class="font-medium mt-1">{{ paymentMethodLabel(settlement.payment_method) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">지급 묶음(Batch)</div>
                        <div class="font-mono text-sm mt-1">{{ settlement.payment_batch_no ?? '-' }}</div>
                    </div>
                    <div class="md:col-span-1">
                        <div class="text-xs text-surface-500">메모</div>
                        <div class="text-sm mt-1 whitespace-pre-wrap">{{ settlement.payment_note || '-' }}</div>
                    </div>
                </div>
            </div>

            <!-- GAP-5: 지급 증빙 파일 -->
            <div v-if="can.uploadPaymentFile || paymentFiles.length > 0" class="card">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">지급 증빙 파일</h2>
                    <span class="text-xs text-surface-500">{{ paymentFiles.length }}건 · PDF/이미지/문서 최대 10MB</span>
                </div>

                <div v-if="can.uploadPaymentFile" class="flex items-end gap-2 mb-4">
                    <div class="flex-1">
                        <input
                            id="payment-file-input"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.hwp"
                            @change="onFileSelect"
                            class="block w-full text-sm text-surface-700 dark:text-surface-200
                                   file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0
                                   file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700
                                   hover:file:bg-primary-100"
                        />
                        <small v-if="fileForm.errors.file" class="text-red-500">{{ fileForm.errors.file }}</small>
                    </div>
                    <Button
                        icon="pi pi-upload"
                        label="업로드"
                        :loading="fileForm.processing"
                        :disabled="!fileForm.file"
                        @click="submitFileUpload"
                    />
                </div>

                <DataTable :value="paymentFiles" :striped-rows="true" data-key="id">
                    <Column header="파일명" class="min-w-[180px]">
                        <template #body="{ data }">
                            <a
                                :href="route('settlements.payment-files.download', [settlement.id, data.id])"
                                class="text-primary-600 hover:underline"
                            >
                                {{ data.original_name }}
                            </a>
                        </template>
                    </Column>
                    <Column header="크기" class="min-w-[80px]">
                        <template #body="{ data }">{{ formatSize(data.size) }}</template>
                    </Column>
                    <Column header="업로더" class="min-w-[100px]">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-500">{{ data.uploaded_by_name ?? '-' }}</span>
                        </template>
                    </Column>
                    <Column header="업로드 시각" class="min-w-[120px]">
                        <template #body="{ data }">{{ formatDate(data.uploaded_at) }}</template>
                    </Column>
                    <Column header="" class="w-20">
                        <template #body="{ data }">
                            <Button
                                v-if="can.uploadPaymentFile"
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                text
                                @click="confirmFileDelete(data)"
                            />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="py-6 text-center text-surface-500 text-sm">첨부된 증빙 파일이 없습니다.</div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- GAP-5: 지급 처리 모달 -->
        <Dialog
            v-model:visible="payDialogVisible"
            header="정산 지급 처리"
            modal
            :style="{ width: '480px' }"
        >
            <form @submit.prevent="submitPay" class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">지급일 *</label>
                    <InputText
                        v-model="payForm.paid_on"
                        type="date"
                        :invalid="!!payForm.errors.paid_on"
                    />
                    <small v-if="payForm.errors.paid_on" class="text-red-500">{{ payForm.errors.paid_on }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">지급 수단</label>
                    <Select
                        v-model="payForm.payment_method"
                        :options="paymentMethodOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="선택"
                        :invalid="!!payForm.errors.payment_method"
                        show-clear
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">지급 묶음 (Batch No.)</label>
                    <InputText
                        v-model="payForm.payment_batch_no"
                        placeholder="예: 2026-04-BATCH-001"
                        :invalid="!!payForm.errors.payment_batch_no"
                    />
                    <small class="text-xs text-surface-500">월말 N건을 함께 지급할 때 동일 값을 부여하면 목록에서 한꺼번에 필터링할 수 있습니다.</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">메모</label>
                    <Textarea
                        v-model="payForm.payment_note"
                        rows="3"
                        :invalid="!!payForm.errors.payment_note"
                    />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="payDialogVisible = false" />
                    <Button type="submit" label="지급 완료 처리" icon="pi pi-check" :loading="payForm.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
