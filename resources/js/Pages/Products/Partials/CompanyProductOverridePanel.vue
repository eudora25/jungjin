<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import CompanySearchAutoComplete from './CompanySearchAutoComplete.vue';

interface CompanyOverride {
    id: number;
    company_id: number;
    product_id: number;
    override_unit_price: string | number | null;
    override_commission_rate: string | number | null;
    effective_from: string;
    effective_to: string | null;
    reason: string | null;
    company: {
        id: number;
        company_name: string;
        business_registration_number: string | null;
        default_commission_grade: string | null;
    } | null;
    creator: { id: number; name: string } | null;
    updater: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    productId: number;
    overrides: CompanyOverride[];
    canManage: boolean;
}>();

const formatMoney = (v: number | string | null | undefined) => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'string' ? Number(v) : v;
    if (Number.isNaN(n)) return '-';
    return new Intl.NumberFormat('ko-KR').format(n) + '원';
};

const formatRate = (v: number | string | null | undefined) => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'string' ? Number(v) : v;
    if (Number.isNaN(n)) return '-';
    return n.toFixed(2) + '%';
};

const formatDate = (iso: string | null) => {
    if (!iso) return '-';
    return new Intl.DateTimeFormat('ko-KR', { dateStyle: 'medium' }).format(new Date(iso));
};

const isCurrent = (row: CompanyOverride) => {
    const today = new Date().toISOString().slice(0, 10);
    return row.effective_from <= today && (row.effective_to === null || row.effective_to >= today);
};

const activeOverrides = computed(() => props.overrides.filter(isCurrent));

// --- 등록 모달 ---
const createOpen = ref(false);
const createForm = useForm<{
    company_id: number | null;
    override_unit_price: number | null;
    override_commission_rate: number | null;
    effective_from: Date | null;
    effective_to: Date | null;
    reason: string;
}>({
    company_id: null,
    override_unit_price: null,
    override_commission_rate: null,
    effective_from: new Date(),
    effective_to: null,
    reason: '',
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.effective_from = new Date();
    createOpen.value = true;
};

const toIsoDate = (d: Date | null) => (d ? d.toISOString().slice(0, 10) : null);

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            effective_from: toIsoDate(data.effective_from as Date | null),
            effective_to: toIsoDate(data.effective_to as Date | null),
        }))
        .post(route('products.overrides.store', props.productId), {
            preserveScroll: true,
            onSuccess: () => {
                createOpen.value = false;
            },
        });
};

// --- 수정 모달 ---
const editOpen = ref(false);
const editTarget = ref<CompanyOverride | null>(null);
const editForm = useForm<{
    override_unit_price: number | null;
    override_commission_rate: number | null;
    effective_to: Date | null;
    reason: string;
}>({
    override_unit_price: null,
    override_commission_rate: null,
    effective_to: null,
    reason: '',
});

const openEdit = (row: CompanyOverride) => {
    editTarget.value = row;
    editForm.override_unit_price = row.override_unit_price !== null ? Number(row.override_unit_price) : null;
    editForm.override_commission_rate =
        row.override_commission_rate !== null ? Number(row.override_commission_rate) : null;
    editForm.effective_to = row.effective_to ? new Date(row.effective_to) : null;
    editForm.reason = row.reason ?? '';
    editForm.clearErrors();
    editOpen.value = true;
};

const submitEdit = () => {
    if (!editTarget.value) return;
    editForm
        .transform((data) => ({ ...data, effective_to: toIsoDate(data.effective_to as Date | null) }))
        .put(route('products.overrides.update', { product: props.productId, override: editTarget.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
                editTarget.value = null;
            },
        });
};

// --- 삭제 ---
const confirm = useConfirm();
const onDelete = (row: CompanyOverride) => {
    confirm.require({
        header: '거래처 예외 삭제',
        message: `${row.company?.company_name ?? '해당 거래처'} (${formatDate(row.effective_from)} 시작) 예외를 삭제할까요? 인접 이력의 적용 종료일은 자동 복원되지 않습니다.`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(
                route('products.overrides.destroy', { product: props.productId, override: row.id }),
                { preserveScroll: true },
            ),
    });
};
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">거래처 예외 단가 / 수수료</h2>
                <p class="text-xs text-surface-500 mt-0.5">
                    거래처별로 매출 단가 또는 수수료율의 예외를 적용 시작일 기준으로 관리합니다.
                    같은 거래처 + 시작일 조합은 중복 등록할 수 없습니다.
                </p>
            </div>
            <Button v-if="canManage" label="거래처 예외 등록" icon="pi pi-plus" @click="openCreate" />
        </div>

        <Message v-if="activeOverrides.length > 0" severity="info" :closable="false" class="mb-3">
            현재 <b>{{ activeOverrides.length }}</b>개 거래처에 예외가 적용 중입니다.
        </Message>

        <DataTable :value="overrides" data-key="id" :rows="10" paginator responsive-layout="scroll">
            <template #empty>
                <div class="text-center text-surface-500 py-6">등록된 거래처 예외가 없습니다.</div>
            </template>

            <Column header="거래처">
                <template #body="{ data }">
                    <div class="flex flex-col">
                        <span class="font-medium">{{ data.company?.company_name ?? '-' }}</span>
                        <span class="text-xs text-surface-500">
                            <span v-if="data.company?.business_registration_number">
                                {{ data.company.business_registration_number }}
                            </span>
                            <span v-if="data.company?.default_commission_grade" class="ml-1">
                                · 기본 등급 {{ data.company.default_commission_grade.toUpperCase() }}
                            </span>
                        </span>
                    </div>
                </template>
            </Column>
            <Column header="예외 단가" style="width: 9rem">
                <template #body="{ data }">
                    <span class="font-medium">{{ formatMoney(data.override_unit_price) }}</span>
                </template>
            </Column>
            <Column header="예외 수수료율" style="width: 8rem">
                <template #body="{ data }">
                    <span class="font-medium">{{ formatRate(data.override_commission_rate) }}</span>
                </template>
            </Column>
            <Column header="시작일" style="width: 8rem">
                <template #body="{ data }">{{ formatDate(data.effective_from) }}</template>
            </Column>
            <Column header="종료일" style="width: 8rem">
                <template #body="{ data }">
                    <span :class="{ 'text-surface-400': !data.effective_to }">
                        {{ data.effective_to ? formatDate(data.effective_to) : '— (현재)' }}
                    </span>
                </template>
            </Column>
            <Column header="상태" body-class="text-center" style="width: 6rem">
                <template #body="{ data }">
                    <Tag v-if="isCurrent(data)" value="적용중" severity="success" />
                    <Tag v-else value="비활성" severity="secondary" />
                </template>
            </Column>
            <Column header="사유">
                <template #body="{ data }">
                    <span class="text-sm text-surface-700 dark:text-surface-200">{{ data.reason ?? '-' }}</span>
                </template>
            </Column>
            <Column header="" style="width: 7rem">
                <template #body="{ data }">
                    <div class="flex justify-end gap-1">
                        <Button
                            v-if="canManage"
                            icon="pi pi-pencil"
                            severity="secondary"
                            text
                            rounded
                            title="수정"
                            @click="openEdit(data)"
                        />
                        <Button
                            v-if="canManage"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            rounded
                            title="삭제"
                            @click="onDelete(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- 등록 모달 -->
        <Dialog
            v-model:visible="createOpen"
            modal
            header="거래처 예외 등록"
            :style="{ width: '36rem' }"
            :draggable="false"
        >
            <div class="flex flex-col gap-3">
                <Message severity="info" :closable="false">
                    같은 거래처에서 직전 이력의 적용 종료일은 신규 시작일 -1일 로 자동 마감됩니다.
                    단가 / 수수료율 중 최소 1개는 입력해야 합니다.
                </Message>

                <div>
                    <label class="block text-sm font-medium mb-1">거래처 <span class="text-red-500">*</span></label>
                    <CompanySearchAutoComplete
                        v-model="createForm.company_id"
                        :invalid="!!createForm.errors.company_id"
                    />
                    <small v-if="createForm.errors.company_id" class="text-red-500">{{ createForm.errors.company_id }}</small>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">예외 단가</label>
                        <InputNumber
                            v-model="createForm.override_unit_price"
                            mode="decimal"
                            :min="0"
                            :max-fraction-digits="2"
                            suffix=" 원"
                            class="w-full"
                            :class="{ 'p-invalid': createForm.errors.override_unit_price }"
                            input-class="w-full"
                            fluid
                        />
                        <small v-if="createForm.errors.override_unit_price" class="text-red-500">
                            {{ createForm.errors.override_unit_price }}
                        </small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">예외 수수료율</label>
                        <InputNumber
                            v-model="createForm.override_commission_rate"
                            mode="decimal"
                            :min="0"
                            :max="100"
                            :max-fraction-digits="2"
                            suffix=" %"
                            class="w-full"
                            :class="{ 'p-invalid': createForm.errors.override_commission_rate }"
                            input-class="w-full"
                            fluid
                        />
                        <small v-if="createForm.errors.override_commission_rate" class="text-red-500">
                            {{ createForm.errors.override_commission_rate }}
                        </small>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">적용 시작일 <span class="text-red-500">*</span></label>
                        <DatePicker
                            v-model="createForm.effective_from"
                            date-format="yy-mm-dd"
                            show-icon
                            class="w-full"
                            :class="{ 'p-invalid': createForm.errors.effective_from }"
                            fluid
                        />
                        <small v-if="createForm.errors.effective_from" class="text-red-500">{{ createForm.errors.effective_from }}</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">적용 종료일 (선택)</label>
                        <DatePicker
                            v-model="createForm.effective_to"
                            date-format="yy-mm-dd"
                            show-icon
                            show-button-bar
                            class="w-full"
                            :class="{ 'p-invalid': createForm.errors.effective_to }"
                            fluid
                        />
                        <small v-if="createForm.errors.effective_to" class="text-red-500">{{ createForm.errors.effective_to }}</small>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">사유 (선택)</label>
                    <Textarea
                        v-model="createForm.reason"
                        rows="2"
                        auto-resize
                        class="w-full"
                        :class="{ 'p-invalid': createForm.errors.reason }"
                        placeholder="예: 거래처 합의가 확정 (2026-04-20), 시범 단가"
                    />
                    <small v-if="createForm.errors.reason" class="text-red-500">{{ createForm.errors.reason }}</small>
                </div>
            </div>
            <template #footer>
                <Button label="취소" severity="secondary" text @click="createOpen = false" />
                <Button
                    label="등록"
                    icon="pi pi-check"
                    :loading="createForm.processing"
                    :disabled="!createForm.company_id || !createForm.effective_from"
                    @click="submitCreate"
                />
            </template>
        </Dialog>

        <!-- 수정 모달 -->
        <Dialog
            v-model:visible="editOpen"
            modal
            header="거래처 예외 수정"
            :style="{ width: '36rem' }"
            :draggable="false"
        >
            <div v-if="editTarget" class="flex flex-col gap-3">
                <Message severity="warn" :closable="false">
                    거래처와 적용 시작일은 변경할 수 없습니다. 변경이 필요하면 이력을 삭제 후 재등록하세요.
                </Message>

                <div class="rounded bg-surface-50 dark:bg-surface-800 p-3 text-sm">
                    <div class="font-medium">{{ editTarget.company?.company_name ?? '-' }}</div>
                    <div class="text-surface-600 dark:text-surface-300">
                        시작일: {{ formatDate(editTarget.effective_from) }}
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">예외 단가</label>
                        <InputNumber
                            v-model="editForm.override_unit_price"
                            mode="decimal"
                            :min="0"
                            :max-fraction-digits="2"
                            suffix=" 원"
                            class="w-full"
                            :class="{ 'p-invalid': editForm.errors.override_unit_price }"
                            input-class="w-full"
                            fluid
                        />
                        <small v-if="editForm.errors.override_unit_price" class="text-red-500">
                            {{ editForm.errors.override_unit_price }}
                        </small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">예외 수수료율</label>
                        <InputNumber
                            v-model="editForm.override_commission_rate"
                            mode="decimal"
                            :min="0"
                            :max="100"
                            :max-fraction-digits="2"
                            suffix=" %"
                            class="w-full"
                            :class="{ 'p-invalid': editForm.errors.override_commission_rate }"
                            input-class="w-full"
                            fluid
                        />
                        <small v-if="editForm.errors.override_commission_rate" class="text-red-500">
                            {{ editForm.errors.override_commission_rate }}
                        </small>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">적용 종료일 (비우면 현재 적용)</label>
                    <DatePicker
                        v-model="editForm.effective_to"
                        date-format="yy-mm-dd"
                        show-icon
                        show-button-bar
                        class="w-full"
                        :class="{ 'p-invalid': editForm.errors.effective_to }"
                        fluid
                    />
                    <small v-if="editForm.errors.effective_to" class="text-red-500">{{ editForm.errors.effective_to }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">사유 (선택)</label>
                    <Textarea
                        v-model="editForm.reason"
                        rows="2"
                        auto-resize
                        class="w-full"
                        :class="{ 'p-invalid': editForm.errors.reason }"
                    />
                    <small v-if="editForm.errors.reason" class="text-red-500">{{ editForm.errors.reason }}</small>
                </div>
            </div>
            <template #footer>
                <Button label="취소" severity="secondary" text @click="editOpen = false" />
                <Button
                    label="저장"
                    icon="pi pi-check"
                    :loading="editForm.processing"
                    @click="submitEdit"
                />
            </template>
        </Dialog>
    </div>
</template>
