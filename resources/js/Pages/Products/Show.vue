<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CommissionRatePanel from './Partials/CommissionRatePanel.vue';
import CompanyProductOverridePanel from './Partials/CompanyProductOverridePanel.vue';
import ProductActivityLogPanel from './Partials/ProductActivityLogPanel.vue';
import ProductFilePanel from './Partials/ProductFilePanel.vue';
import ProductPricePanel from './Partials/ProductPricePanel.vue';
import ProductSearchAutoComplete from './Partials/ProductSearchAutoComplete.vue';
import RecentPerformancePanel from './Partials/RecentPerformancePanel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Card from 'primevue/card';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';

interface CommissionRate {
    id: number;
    base_month: string;
    commission_rate_a: string | number;
    commission_rate_b: string | number;
    commission_rate_c: string | number;
    commission_rate_d: string | number;
    commission_rate_e: string | number;
    effective_from: string;
    effective_to: string | null;
    status: 'active' | 'inactive';
}

interface UserRef {
    id: number;
    name: string;
    email?: string;
}

interface ReplacementRef {
    id: number;
    product_name: string;
    insurance_code: string;
    product_code: string;
}

interface ProductFile {
    id: number;
    file_type: 'license' | 'safety' | 'catalog' | 'etc';
    original_name: string;
    stored_name: string;
    path: string;
    size: number | null;
    mime_type: string | null;
    extension: string | null;
    uploaded_by: number | null;
    uploader: { id: number; name: string } | null;
    created_at: string;
}

interface ProductPriceRow {
    id: number;
    product_id: number;
    price_type: 'insurance' | 'cost' | 'sale';
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

interface CompanyOverrideRow {
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

interface Product {
    id: number;
    insurance_code: string;
    standard_code: string | null;
    barcode_gtin: string | null;
    product_code: string;
    product_name: string;
    generic_name: string | null;
    strength: string | null;
    unit: string | null;
    pack_size: number | null;
    manufacturer: string | null;
    category: string | null;
    drug_type: 'general' | 'etc' | 'narcotic' | 'psychotropic';
    storage_condition: 'room' | 'cold' | 'frozen';
    nims_managed: boolean;
    nims_item_code: string | null;
    description: string | null;
    image_url: string | null;
    price: string | number | null;
    status: 'active' | 'inactive' | 'discontinued';
    approval_status: 'draft' | 'pending' | 'reviewed' | 'approved' | 'rejected';
    reviewed_at: string | null;
    approved_at: string | null;
    remarks: string | null;
    created_at: string;
    updated_at: string;
    creator: UserRef | null;
    updater: UserRef | null;
    reviewer: UserRef | null;
    approver: UserRef | null;
    replacement: ReplacementRef | null;
    commission_rates: CommissionRate[];
    files: ProductFile[];
    prices: ProductPriceRow[];
    current_prices: { insurance: number | string | null; cost: number | string | null; sale: number | string | null };
    company_overrides: CompanyOverrideRow[];
}

interface Capabilities {
    update: boolean;
    delete: boolean;
    submit: boolean;
    review: boolean;
    approve: boolean;
    reject: boolean;
    discontinue: boolean;
    managePrices: boolean;
    manageOverrides: boolean;
}

interface ActivityRow {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string;
    subject_id: number | null;
    causer: { id: number; name: string } | null;
    properties: Record<string, unknown>;
    created_at: string;
}

interface RecentPerformanceRow {
    id: number;
    performance_no: string;
    performance_date: string | null;
    company: { id: number; company_name: string; default_commission_grade: string | null } | null;
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
    commission_rate: string | number | null;
    commission_amount: string | number | null;
    status: string;
}

const props = defineProps<{
    product: Product;
    can: Capabilities;
    activities: ActivityRow[];
    recentPerformances: RecentPerformanceRow[];
}>();

const confirm = useConfirm();

// --- 상태/라벨 ---
const statusLabel = (s: string) =>
    ({ active: '활성', inactive: '비활성', discontinued: '단종' }[s] ?? s);
const statusSeverity = (s: string) =>
    ({ active: 'success', inactive: 'secondary', discontinued: 'danger' }[s] ?? 'secondary');

const approvalLabel = (s: string) =>
    ({ draft: '초안', pending: '검수 대기', reviewed: '검수 완료', approved: '승인 완료', rejected: '반려' }[s] ?? s);
const approvalSeverity = (s: string) =>
    ({ draft: 'secondary', pending: 'warn', reviewed: 'info', approved: 'success', rejected: 'danger' }[s] ?? 'secondary');

const drugTypeLabel = (s: string) =>
    ({ general: '일반', etc: 'ETC(전문)', narcotic: '마약', psychotropic: '향정신성' }[s] ?? s);
const drugTypeSeverity = (s: string) =>
    ({ general: 'secondary', etc: 'info', narcotic: 'danger', psychotropic: 'warn' }[s] ?? 'secondary');

const storageLabel = (s: string) =>
    ({ room: '실온', cold: '냉장', frozen: '냉동' }[s] ?? s);

// --- 포맷터 ---
const formatPrice = (p: string | number | null) => {
    if (p === null || p === undefined || p === '') return '-';
    const n = typeof p === 'string' ? parseFloat(p) : p;
    if (Number.isNaN(n)) return '-';
    return new Intl.NumberFormat('ko-KR').format(n) + '원';
};
const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('ko-KR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso)) : '-';

// --- 단순 액션 (확인 모달 → POST) ---
const postAction = (routeName: string, header: string, message: string, acceptLabel: string, severity: 'primary' | 'danger' = 'primary') => {
    confirm.require({
        header,
        message,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel,
        rejectLabel: '취소',
        acceptClass: severity === 'danger' ? 'p-button-danger' : '',
        accept: () => router.post(route(routeName, props.product.id), {}, { preserveScroll: true }),
    });
};

const onSubmit = () =>
    postAction('products.submit', '검수 요청', '이 제품을 검수 단계로 제출하시겠습니까?', '검수 요청');
const onReview = () =>
    postAction('products.review', '검수 완료', '이 제품의 검수를 완료 처리하시겠습니까? (다음 단계: 최종 승인)', '검수 완료');
const onApprove = () =>
    postAction('products.approve', '최종 승인', '이 제품을 최종 승인하시겠습니까? 승인 후에는 정상 판매 가능 상태가 됩니다.', '최종 승인');

const confirmDelete = () => {
    confirm.require({
        message: '이 제품을 삭제하시겠습니까? (soft delete로 복원 가능)',
        header: '제품 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('products.destroy', props.product.id)),
    });
};

// --- 반려 모달 ---
const rejectOpen = ref(false);
const rejectForm = useForm({ reason: '' });
const openReject = () => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectOpen.value = true;
};
const submitReject = () => {
    rejectForm.post(route('products.reject', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejectOpen.value = false;
        },
    });
};

// --- 단종 모달 ---
const discontinueOpen = ref(false);
const discontinueForm = useForm<{ replacement_product_id: number | null; reason: string }>({
    replacement_product_id: null,
    reason: '',
});
const openDiscontinue = () => {
    discontinueForm.reset();
    discontinueForm.clearErrors();
    discontinueOpen.value = true;
};
const submitDiscontinue = () => {
    discontinueForm.post(route('products.discontinue', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            discontinueOpen.value = false;
        },
    });
};

// --- 다음 단계 안내 ---
const nextStepHint = computed(() => {
    switch (props.product.approval_status) {
        case 'draft':
            return '초안 상태입니다. 내용을 확인한 뒤 "검수 요청"을 누르세요.';
        case 'pending':
            return '검수자의 확인을 기다리는 중입니다.';
        case 'reviewed':
            return '검수가 완료되었습니다. 최종 승인이 필요합니다.';
        case 'approved':
            return '최종 승인이 완료된 제품입니다. 정상 판매 가능 상태입니다.';
        case 'rejected':
            return '반려된 제품입니다. 내용을 수정한 뒤 다시 "검수 요청"을 누르세요.';
        default:
            return '';
    }
});

const hasAnyAction = computed(() =>
    props.can.submit || props.can.review || props.can.approve || props.can.reject || props.can.discontinue,
);
</script>

<template>
    <Head :title="product.product_name" />

    <ConfirmDialog />

    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl">
            <Link :href="route('products.index')" class="text-sm text-surface-500 hover:text-primary">
                <i class="pi pi-arrow-left mr-1" />제품 목록
            </Link>

            <Card>
                <template #content>
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-start gap-4 flex-1">
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                class="w-24 h-24 object-cover rounded-lg border border-surface"
                                :alt="product.product_name"
                            />
                            <div
                                v-else
                                class="w-24 h-24 rounded-lg border border-dashed border-surface-300 dark:border-surface-700 flex items-center justify-center text-surface-400"
                            >
                                <i class="pi pi-image" style="font-size: 1.5rem" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center flex-wrap gap-2 mb-2">
                                    <Tag :value="approvalLabel(product.approval_status)" :severity="approvalSeverity(product.approval_status)" />
                                    <Tag :value="statusLabel(product.status)" :severity="statusSeverity(product.status)" />
                                    <Tag :value="drugTypeLabel(product.drug_type)" :severity="drugTypeSeverity(product.drug_type)" />
                                    <Tag v-if="product.nims_managed" value="NIMS 관리" severity="danger" icon="pi pi-shield" />
                                    <Tag v-if="product.category" :value="product.category" severity="info" />
                                </div>
                                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0 truncate">{{ product.product_name }}</h1>
                                <div class="text-sm text-surface-500 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                                    <span v-if="product.generic_name">{{ product.generic_name }}</span>
                                    <span v-if="product.strength">{{ product.strength }}{{ product.unit ?? '' }}</span>
                                    <span v-if="product.manufacturer">· {{ product.manufacturer }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <Link v-if="can.update" :href="route('products.edit', product.id)">
                                <Button label="수정" icon="pi pi-pencil" severity="secondary" outlined />
                            </Link>
                            <Button
                                v-if="can.delete"
                                label="삭제"
                                icon="pi pi-trash"
                                severity="danger"
                                outlined
                                @click="confirmDelete"
                            />
                        </div>
                    </div>

                    <Message
                        v-if="nextStepHint"
                        :severity="approvalSeverity(product.approval_status) === 'success' ? 'success' : approvalSeverity(product.approval_status) === 'danger' ? 'error' : 'info'"
                        :closable="false"
                        class="mb-4"
                    >
                        {{ nextStepHint }}
                    </Message>

                    <div v-if="hasAnyAction" class="flex flex-wrap items-center gap-2 mb-4">
                        <Button
                            v-if="can.submit"
                            label="검수 요청"
                            icon="pi pi-send"
                            severity="info"
                            @click="onSubmit"
                        />
                        <Button
                            v-if="can.review"
                            label="검수 완료"
                            icon="pi pi-check-square"
                            severity="info"
                            @click="onReview"
                        />
                        <Button
                            v-if="can.approve"
                            label="최종 승인"
                            icon="pi pi-verified"
                            severity="success"
                            @click="onApprove"
                        />
                        <Button
                            v-if="can.reject"
                            label="반려"
                            icon="pi pi-times-circle"
                            severity="danger"
                            outlined
                            @click="openReject"
                        />
                        <Button
                            v-if="can.discontinue"
                            label="단종 처리"
                            icon="pi pi-ban"
                            severity="warn"
                            outlined
                            @click="openDiscontinue"
                        />
                    </div>

                    <hr class="border-surface-200 dark:border-surface-700 my-4" />

                    <h2 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-3">기본 정보</h2>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt class="text-surface-500 mb-1">보험코드</dt>
                            <dd class="font-mono">{{ product.insurance_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">제품코드</dt>
                            <dd class="font-mono">{{ product.product_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">표준코드(KD)</dt>
                            <dd class="font-mono">{{ product.standard_code || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">바코드(GTIN)</dt>
                            <dd class="font-mono">{{ product.barcode_gtin || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">성분명</dt>
                            <dd>{{ product.generic_name || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">함량 / 단위</dt>
                            <dd>{{ product.strength || '-' }} {{ product.unit || '' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">포장 수량</dt>
                            <dd>{{ product.pack_size ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">보관 조건</dt>
                            <dd>{{ storageLabel(product.storage_condition) }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">약가</dt>
                            <dd class="font-semibold">{{ formatPrice(product.price) }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">카테고리</dt>
                            <dd>{{ product.category ?? '-' }}</dd>
                        </div>
                        <div v-if="product.nims_managed">
                            <dt class="text-surface-500 mb-1">NIMS 품목코드</dt>
                            <dd class="font-mono">{{ product.nims_item_code || '미연동' }}</dd>
                        </div>
                        <div v-if="product.replacement">
                            <dt class="text-surface-500 mb-1">대체품</dt>
                            <dd>
                                <Link :href="route('products.show', product.replacement.id)" class="text-primary hover:underline">
                                    {{ product.replacement.product_name }}
                                    <span class="text-xs text-surface-500 ml-1">({{ product.replacement.insurance_code }})</span>
                                </Link>
                            </dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">제품 설명</dt>
                            <dd class="whitespace-pre-wrap">{{ product.description || '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">비고</dt>
                            <dd class="whitespace-pre-wrap">{{ product.remarks || '-' }}</dd>
                        </div>
                    </dl>

                    <hr class="border-surface-200 dark:border-surface-700 my-4" />

                    <h2 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-3">변경 이력</h2>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs text-surface-500">
                        <div>
                            <dt class="mb-1">등록</dt>
                            <dd>{{ formatDate(product.created_at) }} · {{ product.creator?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1">최종 수정</dt>
                            <dd>{{ formatDate(product.updated_at) }} · {{ product.updater?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1">검수</dt>
                            <dd>{{ formatDate(product.reviewed_at) }} · {{ product.reviewer?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1">최종 승인</dt>
                            <dd>{{ formatDate(product.approved_at) }} · {{ product.approver?.name ?? '-' }}</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #content>
                    <ProductPricePanel
                        :product-id="product.id"
                        :prices="product.prices ?? []"
                        :current-prices="product.current_prices ?? { insurance: null, cost: null, sale: null }"
                        :can-manage="can.managePrices"
                    />
                </template>
            </Card>

            <Card>
                <template #content>
                    <CompanyProductOverridePanel
                        :product-id="product.id"
                        :overrides="product.company_overrides ?? []"
                        :can-manage="can.manageOverrides"
                    />
                </template>
            </Card>

            <Card>
                <template #content>
                    <CommissionRatePanel
                        :product-id="product.id"
                        :rates="product.commission_rates"
                        :can-edit="can.update"
                    />
                </template>
            </Card>

            <Card>
                <template #content>
                    <ProductFilePanel
                        :product-id="product.id"
                        :files="product.files ?? []"
                        :can-upload="can.update"
                        :can-delete="can.delete"
                    />
                </template>
            </Card>

            <Card>
                <template #content>
                    <RecentPerformancePanel :rows="recentPerformances ?? []" />
                </template>
            </Card>

            <Card>
                <template #content>
                    <ProductActivityLogPanel :activities="activities ?? []" />
                </template>
            </Card>
        </div>

        <!-- 반려 모달 -->
        <Dialog v-model:visible="rejectOpen" modal header="제품 반려" :style="{ width: '32rem' }" :draggable="false">
            <div class="flex flex-col gap-3">
                <p class="text-sm text-surface-600 dark:text-surface-300">
                    반려 사유를 입력해주세요. 반려된 제품은 <b>초안 상태로 되돌아가지 않고 '반려' 상태</b>로 표시되며,
                    수정 후 다시 검수 요청이 가능합니다.
                </p>
                <div>
                    <label for="reject-reason" class="block text-sm font-medium mb-1">반려 사유 <span class="text-red-500">*</span></label>
                    <Textarea
                        id="reject-reason"
                        v-model="rejectForm.reason"
                        rows="4"
                        class="w-full"
                        :class="{ 'p-invalid': rejectForm.errors.reason }"
                        placeholder="예) 보험코드가 NIMS 등록 정보와 일치하지 않습니다."
                    />
                    <small v-if="rejectForm.errors.reason" class="text-red-500">{{ rejectForm.errors.reason }}</small>
                </div>
            </div>
            <template #footer>
                <Button label="취소" severity="secondary" text @click="rejectOpen = false" />
                <Button
                    label="반려"
                    icon="pi pi-times-circle"
                    severity="danger"
                    :loading="rejectForm.processing"
                    @click="submitReject"
                />
            </template>
        </Dialog>

        <!-- 단종 모달 -->
        <Dialog v-model:visible="discontinueOpen" modal header="제품 단종 처리" :style="{ width: '34rem' }" :draggable="false">
            <div class="flex flex-col gap-3">
                <p class="text-sm text-surface-600 dark:text-surface-300">
                    단종 처리 시 제품 상태가 <b>'단종'</b>으로 전환됩니다. 대체품과 사유는 선택 입력입니다.
                </p>
                <div>
                    <label class="block text-sm font-medium mb-1">대체품 (선택)</label>
                    <ProductSearchAutoComplete
                        v-model="discontinueForm.replacement_product_id"
                        :exclude-id="product.id"
                        :invalid="!!discontinueForm.errors.replacement_product_id"
                        placeholder="제품명·보험코드·제품코드로 검색"
                    />
                    <small v-if="discontinueForm.errors.replacement_product_id" class="text-red-500">
                        {{ discontinueForm.errors.replacement_product_id }}
                    </small>
                    <small v-else class="text-surface-500">제품을 검색해 선택하세요. 자기 자신은 검색 결과에서 제외됩니다.</small>
                </div>
                <div>
                    <label for="dc-reason" class="block text-sm font-medium mb-1">단종 사유 (선택)</label>
                    <Textarea
                        id="dc-reason"
                        v-model="discontinueForm.reason"
                        rows="3"
                        class="w-full"
                        :class="{ 'p-invalid': discontinueForm.errors.reason }"
                        placeholder="예) 제조사 단종 통보, 대체 품목 출시 등"
                    />
                    <small v-if="discontinueForm.errors.reason" class="text-red-500">{{ discontinueForm.errors.reason }}</small>
                </div>
            </div>
            <template #footer>
                <Button label="취소" severity="secondary" text @click="discontinueOpen = false" />
                <Button
                    label="단종 처리"
                    icon="pi pi-ban"
                    severity="warn"
                    :loading="discontinueForm.processing"
                    @click="submitDiscontinue"
                />
            </template>
        </Dialog>
    </AdminLayout>
</template>
