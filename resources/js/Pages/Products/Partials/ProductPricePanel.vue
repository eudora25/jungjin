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
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import ProductPriceChart from './ProductPriceChart.vue';

type PriceType = 'insurance' | 'cost' | 'sale';

interface ProductPrice {
    id: number;
    product_id: number;
    price_type: PriceType;
    amount: string | number;
    effective_from: string;
    effective_to: string | null;
    source: string | null;
    note: string | null;
    creator: { id: number; name: string } | null;
    updater: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    productId: number;
    prices: ProductPrice[];
    currentPrices: { insurance: number | string | null; cost: number | string | null; sale: number | string | null };
    canManage: boolean;
}>();

const priceTypeOptions: { label: string; value: PriceType; severity: 'info' | 'warn' | 'success' }[] = [
    { label: '보험약가', value: 'insurance', severity: 'info' },
    { label: '매입가', value: 'cost', severity: 'warn' },
    { label: '매출가', value: 'sale', severity: 'success' },
];

const typeLabel = (t: PriceType) => priceTypeOptions.find((o) => o.value === t)?.label ?? t;
const typeSeverity = (t: PriceType) => priceTypeOptions.find((o) => o.value === t)?.severity ?? 'info';

const formatMoney = (v: number | string | null | undefined) => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'string' ? Number(v) : v;
    if (Number.isNaN(n)) return '-';
    return new Intl.NumberFormat('ko-KR').format(n) + '원';
};

const formatDate = (iso: string | null) => {
    if (!iso) return '-';
    return new Intl.DateTimeFormat('ko-KR', { dateStyle: 'medium' }).format(new Date(iso));
};

const activeTab = ref<'all' | PriceType>('all');

const filterPrices = (key: 'all' | PriceType) =>
    key === 'all' ? props.prices : props.prices.filter((p) => p.price_type === key);

const countByType = (type: PriceType) => props.prices.filter((p) => p.price_type === type).length;

const isCurrent = (row: ProductPrice) => {
    const today = new Date().toISOString().slice(0, 10);
    return row.effective_from <= today && (row.effective_to === null || row.effective_to >= today);
};

// --- 등록 모달 ---
const createOpen = ref(false);
const createForm = useForm<{
    price_type: PriceType;
    amount: number | null;
    effective_from: Date | null;
    effective_to: Date | null;
    source: string;
    note: string;
}>({
    price_type: 'sale',
    amount: null,
    effective_from: new Date(),
    effective_to: null,
    source: '',
    note: '',
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.effective_from = new Date();
    if (activeTab.value !== 'all') {
        createForm.price_type = activeTab.value;
    }
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
        .post(route('products.prices.store', props.productId), {
            preserveScroll: true,
            onSuccess: () => {
                createOpen.value = false;
            },
        });
};

// --- 수정 모달 ---
const editOpen = ref(false);
const editTarget = ref<ProductPrice | null>(null);
const editForm = useForm<{ amount: number | null; effective_to: Date | null; source: string; note: string }>({
    amount: null,
    effective_to: null,
    source: '',
    note: '',
});

const openEdit = (row: ProductPrice) => {
    editTarget.value = row;
    editForm.amount = Number(row.amount);
    editForm.effective_to = row.effective_to ? new Date(row.effective_to) : null;
    editForm.source = row.source ?? '';
    editForm.note = row.note ?? '';
    editForm.clearErrors();
    editOpen.value = true;
};

const submitEdit = () => {
    if (!editTarget.value) return;
    editForm
        .transform((data) => ({ ...data, effective_to: toIsoDate(data.effective_to as Date | null) }))
        .put(route('products.prices.update', { product: props.productId, price: editTarget.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
                editTarget.value = null;
            },
        });
};

// --- 삭제 ---
const confirm = useConfirm();
const onDelete = (row: ProductPrice) => {
    confirm.require({
        header: '가격 이력 삭제',
        message: `${typeLabel(row.price_type)} ${formatDate(row.effective_from)} 자 ${formatMoney(row.amount)} 이력을 삭제할까요? 인접 이력의 적용 종료일은 자동 복원되지 않습니다.`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(
                route('products.prices.destroy', { product: props.productId, price: row.id }),
                { preserveScroll: true },
            ),
    });
};

const currentSummary = computed(() =>
    priceTypeOptions.map((opt) => ({
        ...opt,
        amount: props.currentPrices?.[opt.value] ?? null,
    })),
);
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">가격 이력</h2>
                <p class="text-xs text-surface-500 mt-0.5">
                    보험약가 / 매입가 / 매출가를 적용 시작일 기준으로 관리합니다. 새로 등록하면 직전 이력의 적용 종료일이 자동 마감됩니다.
                </p>
            </div>
            <Button v-if="canManage" label="가격 이력 등록" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- 가격 변동 차트 -->
        <div class="mb-4 rounded border border-surface-200 dark:border-surface-700 p-3 bg-surface-0 dark:bg-surface-900">
            <ProductPriceChart :prices="prices" />
        </div>

        <!-- 현재 적용 가격 카드 -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div
                v-for="item in currentSummary"
                :key="item.value"
                class="rounded border border-surface-200 dark:border-surface-700 p-3 bg-surface-0 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between mb-1">
                    <Tag :value="item.label" :severity="item.severity" />
                    <span class="text-xs text-surface-500">현재 적용</span>
                </div>
                <div class="text-xl font-semibold text-surface-900 dark:text-surface-0">
                    {{ formatMoney(item.amount) }}
                </div>
            </div>
        </div>

        <Tabs v-model:value="activeTab">
            <TabList>
                <Tab value="all">전체 <span class="ml-1 text-xs text-surface-500">({{ prices.length }})</span></Tab>
                <Tab v-for="opt in priceTypeOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                    <span class="ml-1 text-xs text-surface-500">({{ countByType(opt.value) }})</span>
                </Tab>
            </TabList>
            <TabPanels>
                <TabPanel
                    v-for="key in (['all', 'insurance', 'cost', 'sale'] as const)"
                    :key="key"
                    :value="key"
                >
                    <DataTable :value="filterPrices(key)" data-key="id" :rows="10" paginator responsive-layout="scroll">
                        <template #empty>
                            <div class="text-center text-surface-500 py-6">등록된 가격 이력이 없습니다.</div>
                        </template>

                        <Column field="price_type" header="종류" style="width: 7rem">
                            <template #body="{ data }">
                                <Tag :value="typeLabel(data.price_type)" :severity="typeSeverity(data.price_type)" />
                            </template>
                        </Column>
                        <Column field="amount" header="금액" style="width: 10rem">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ formatMoney(data.amount) }}</span>
                                    <Tag v-if="isCurrent(data)" value="적용중" severity="success" />
                                </div>
                            </template>
                        </Column>
                        <Column field="effective_from" header="시작일" style="width: 8rem">
                            <template #body="{ data }">{{ formatDate(data.effective_from) }}</template>
                        </Column>
                        <Column field="effective_to" header="종료일" style="width: 8rem">
                            <template #body="{ data }">
                                <span :class="{ 'text-surface-400': !data.effective_to }">
                                    {{ data.effective_to ? formatDate(data.effective_to) : '— (현재)' }}
                                </span>
                            </template>
                        </Column>
                        <Column field="source" header="근거">
                            <template #body="{ data }">
                                <span class="text-sm text-surface-700 dark:text-surface-200">{{ data.source ?? '-' }}</span>
                            </template>
                        </Column>
                        <Column field="creator" header="등록자" style="width: 9rem">
                            <template #body="{ data }">
                                <span class="text-sm">{{ data.creator?.name ?? '-' }}</span>
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
                </TabPanel>
            </TabPanels>
        </Tabs>

        <!-- 등록 모달 -->
        <Dialog
            v-model:visible="createOpen"
            modal
            header="가격 이력 등록"
            :style="{ width: '34rem' }"
            :draggable="false"
        >
            <div class="flex flex-col gap-3">
                <Message severity="info" :closable="false">
                    같은 가격 종류에서 직전 이력의 적용 종료일은 신규 시작일 -1일 로 자동 마감됩니다.
                </Message>

                <div>
                    <label class="block text-sm font-medium mb-1">가격 종류 <span class="text-red-500">*</span></label>
                    <Select
                        v-model="createForm.price_type"
                        :options="priceTypeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                        :class="{ 'p-invalid': createForm.errors.price_type }"
                    />
                    <small v-if="createForm.errors.price_type" class="text-red-500">{{ createForm.errors.price_type }}</small>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">금액 <span class="text-red-500">*</span></label>
                        <InputNumber
                            v-model="createForm.amount"
                            mode="decimal"
                            :min="0"
                            :max-fraction-digits="2"
                            suffix=" 원"
                            class="w-full"
                            :class="{ 'p-invalid': createForm.errors.amount }"
                            input-class="w-full"
                            fluid
                        />
                        <small v-if="createForm.errors.amount" class="text-red-500">{{ createForm.errors.amount }}</small>
                    </div>
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
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">근거 (선택)</label>
                    <InputText
                        v-model="createForm.source"
                        class="w-full"
                        placeholder="예: 보건복지부 고시 2026-XX, 제약사 견적서"
                        :class="{ 'p-invalid': createForm.errors.source }"
                        fluid
                    />
                    <small v-if="createForm.errors.source" class="text-red-500">{{ createForm.errors.source }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">비고 (선택)</label>
                    <Textarea
                        v-model="createForm.note"
                        rows="2"
                        auto-resize
                        class="w-full"
                        :class="{ 'p-invalid': createForm.errors.note }"
                    />
                    <small v-if="createForm.errors.note" class="text-red-500">{{ createForm.errors.note }}</small>
                </div>
            </div>
            <template #footer>
                <Button label="취소" severity="secondary" text @click="createOpen = false" />
                <Button
                    label="등록"
                    icon="pi pi-check"
                    :loading="createForm.processing"
                    :disabled="!createForm.amount || !createForm.effective_from"
                    @click="submitCreate"
                />
            </template>
        </Dialog>

        <!-- 수정 모달 -->
        <Dialog
            v-model:visible="editOpen"
            modal
            header="가격 이력 수정"
            :style="{ width: '34rem' }"
            :draggable="false"
        >
            <div v-if="editTarget" class="flex flex-col gap-3">
                <Message severity="warn" :closable="false">
                    가격 종류와 적용 시작일은 변경할 수 없습니다. 변경이 필요하면 이력을 삭제 후 재등록하세요.
                </Message>

                <div class="rounded bg-surface-50 dark:bg-surface-800 p-3 text-sm">
                    <div class="flex items-center gap-2">
                        <Tag :value="typeLabel(editTarget.price_type)" :severity="typeSeverity(editTarget.price_type)" />
                        <span class="text-surface-600 dark:text-surface-300">시작일: {{ formatDate(editTarget.effective_from) }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">금액 <span class="text-red-500">*</span></label>
                        <InputNumber
                            v-model="editForm.amount"
                            mode="decimal"
                            :min="0"
                            :max-fraction-digits="2"
                            suffix=" 원"
                            class="w-full"
                            :class="{ 'p-invalid': editForm.errors.amount }"
                            input-class="w-full"
                            fluid
                        />
                        <small v-if="editForm.errors.amount" class="text-red-500">{{ editForm.errors.amount }}</small>
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
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">근거 (선택)</label>
                    <InputText
                        v-model="editForm.source"
                        class="w-full"
                        :class="{ 'p-invalid': editForm.errors.source }"
                        fluid
                    />
                    <small v-if="editForm.errors.source" class="text-red-500">{{ editForm.errors.source }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">비고 (선택)</label>
                    <Textarea
                        v-model="editForm.note"
                        rows="2"
                        auto-resize
                        class="w-full"
                        :class="{ 'p-invalid': editForm.errors.note }"
                    />
                    <small v-if="editForm.errors.note" class="text-red-500">{{ editForm.errors.note }}</small>
                </div>
            </div>
            <template #footer>
                <Button label="취소" severity="secondary" text @click="editOpen = false" />
                <Button
                    label="저장"
                    icon="pi pi-check"
                    :loading="editForm.processing"
                    :disabled="!editForm.amount"
                    @click="submitEdit"
                />
            </template>
        </Dialog>
    </div>
</template>
