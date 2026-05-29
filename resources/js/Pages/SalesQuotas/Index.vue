<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import ProgressBar from 'primevue/progressbar';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

interface SalesUser {
    id: number;
    name: string;
}

interface Product {
    id: number;
    product_name: string;
}

interface Quota {
    id: number;
    user_id: number;
    user_name: string;
    product_id: number | null;
    product_name: string | null;
    period_type: 'monthly' | 'quarterly' | 'yearly';
    period: string;
    target_amount: number;
    achieved_amount: number;
    achievement_rate: number;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    quotas: Paginated<Quota>;
    salesUsers: SalesUser[];
    products: Product[];
    filters: {
        user_id: number | null;
        period_type: string | null;
        period: string | null;
    };
}>();

const confirm = useConfirm();

// ── 필터 ─────────────────────────────────────────────────────────────────────

const filterUserId = ref<number | null>(props.filters.user_id ?? null);
const filterPeriodType = ref<string | null>(props.filters.period_type ?? null);
const filterPeriod = ref<string | null>(props.filters.period ?? null);

const periodTypeOptions = [
    { label: '전체', value: null },
    { label: '월별', value: 'monthly' },
    { label: '분기별', value: 'quarterly' },
    { label: '연간', value: 'yearly' },
];

const userOptions = computed(() => [
    { label: '전체', value: null },
    ...props.salesUsers.map((u) => ({ label: u.name, value: u.id })),
]);

const applyFilters = () => {
    router.get(
        route('sales-quotas.index'),
        {
            user_id: filterUserId.value || undefined,
            period_type: filterPeriodType.value || undefined,
            period: filterPeriod.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const resetFilters = () => {
    filterUserId.value = null;
    filterPeriodType.value = null;
    filterPeriod.value = null;
    applyFilters();
};

const onPage = (e: { page: number }) => {
    router.get(
        route('sales-quotas.index'),
        {
            user_id: filterUserId.value || undefined,
            period_type: filterPeriodType.value || undefined,
            period: filterPeriod.value || undefined,
            page: e.page + 1,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

// ── 등록/수정 모달 ────────────────────────────────────────────────────────────

const dialogVisible = ref(false);
const editingId = ref<number | null>(null);

const productOptions = computed(() => [
    { label: '전체 (제품 구분 없음)', value: null },
    ...props.products.map((p) => ({ label: p.product_name, value: p.id })),
]);

const form = useForm({
    user_id: null as number | null,
    product_id: null as number | null,
    period_type: 'monthly' as 'monthly' | 'quarterly' | 'yearly',
    period: '',
    target_amount: 0,
});

const periodTypesForForm = [
    { label: '월별', value: 'monthly' },
    { label: '분기별', value: 'quarterly' },
    { label: '연간', value: 'yearly' },
];

const periodPlaceholder = computed(() => {
    return {
        monthly: '2026-04',
        quarterly: '2026-Q2',
        yearly: '2026',
    }[form.period_type];
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.period_type = 'monthly';
    dialogVisible.value = true;
};

const openEdit = (q: Quota) => {
    editingId.value = q.id;
    form.user_id = q.user_id;
    form.product_id = q.product_id;
    form.period_type = q.period_type;
    form.period = q.period;
    form.target_amount = q.target_amount;
    dialogVisible.value = true;
};

const submit = () => {
    const onSuccess = () => {
        dialogVisible.value = false;
        form.reset();
    };

    if (editingId.value) {
        form.put(route('sales-quotas.update', editingId.value), { onSuccess, preserveScroll: true });
    } else {
        form.post(route('sales-quotas.store'), { onSuccess, preserveScroll: true });
    }
};

const confirmDelete = (q: Quota) => {
    confirm.require({
        message: `${q.user_name}의 ${q.period} 목표를 삭제하시겠습니까?`,
        header: '목표 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(route('sales-quotas.destroy', q.id), { preserveScroll: true }),
    });
};

// ── 포맷 헬퍼 ─────────────────────────────────────────────────────────────────

const formatAmount = (n: number) =>
    new Intl.NumberFormat('ko-KR', { style: 'currency', currency: 'KRW', maximumFractionDigits: 0 }).format(n);

const periodTypeLabel = (t: string) =>
    ({ monthly: '월별', quarterly: '분기별', yearly: '연간' }[t] ?? t);

const rateColor = (rate: number) => {
    if (rate >= 100) return 'success';
    if (rate >= 70) return 'info';
    if (rate >= 40) return 'warn';
    return 'danger';
};
</script>

<template>
    <Head title="목표 관리" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">목표 관리</h1>
                    <p class="text-surface-500 mt-1 text-sm">전체 {{ quotas.total }}건</p>
                </div>
                <Button label="목표 등록" icon="pi pi-plus" @click="openCreate" />
            </div>

            <!-- 필터 -->
            <div class="flex flex-col md:flex-row md:flex-wrap gap-3">
                <Select
                    v-model="filterUserId"
                    :options="userOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="영업사원"
                    class="w-full md:w-[220px] shrink-0"
                    @change="applyFilters"
                />
                <Select
                    v-model="filterPeriodType"
                    :options="periodTypeOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="기간 유형"
                    class="w-full md:w-[180px] shrink-0"
                    @change="applyFilters"
                />
                <InputText
                    v-model="filterPeriod"
                    placeholder="기간 (예: 2026-04)"
                    class="w-full md:w-[200px]"
                    @keyup.enter="applyFilters"
                />
                <div class="flex items-center gap-2 w-full md:w-auto md:ml-auto">
                    <Button icon="pi pi-search" label="검색" severity="info" @click="applyFilters" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilters" />
                </div>
            </div>

            <!-- 목록 -->
            <DataTable :value="quotas.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 목표가 없습니다.</div>
                </template>
                <Column header="영업사원" field="user_name" style="width: 120px" />
                <Column header="제품" style="min-width: 150px">
                    <template #body="{ data }">
                        <span class="text-surface-500 text-sm">{{ data.product_name ?? '전체' }}</span>
                    </template>
                </Column>
                <Column header="기간 유형" style="width: 100px">
                    <template #body="{ data }">
                        <Tag :value="periodTypeLabel(data.period_type)" severity="secondary" />
                    </template>
                </Column>
                <Column header="기간" field="period" style="width: 100px">
                    <template #body="{ data }">
                        <span class="font-mono text-sm">{{ data.period }}</span>
                    </template>
                </Column>
                <Column header="목표금액" style="width: 140px; text-align: right">
                    <template #body="{ data }">
                        <span class="font-medium">{{ formatAmount(data.target_amount) }}</span>
                    </template>
                </Column>
                <Column header="달성금액" style="width: 140px; text-align: right">
                    <template #body="{ data }">
                        <span>{{ formatAmount(data.achieved_amount) }}</span>
                    </template>
                </Column>
                <Column header="달성률" style="width: 180px">
                    <template #body="{ data }">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-sm">
                                <Tag
                                    :value="`${data.achievement_rate}%`"
                                    :severity="rateColor(data.achievement_rate)"
                                />
                            </div>
                            <ProgressBar
                                :value="Math.min(data.achievement_rate, 100)"
                                :show-value="false"
                                style="height: 6px"
                            />
                        </div>
                    </template>
                </Column>
                <Column header="" style="width: 90px">
                    <template #body="{ data }">
                        <div class="flex gap-1">
                            <Button icon="pi pi-pencil" size="small" text @click="openEdit(data)" />
                            <Button icon="pi pi-trash" size="small" severity="danger" text @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <Paginator
                :rows="quotas.per_page"
                :total-records="quotas.total"
                :first="(quotas.current_page - 1) * quotas.per_page"
                @page="onPage"
            />
        </div>

        <!-- 등록/수정 모달 -->
        <Dialog
            v-model:visible="dialogVisible"
            :header="editingId ? '목표 수정' : '목표 등록'"
            modal
            :style="{ width: '480px' }"
        >
            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">영업사원 *</label>
                    <Select
                        v-model="form.user_id"
                        :options="salesUsers"
                        option-label="name"
                        option-value="id"
                        placeholder="영업사원 선택"
                        :invalid="!!form.errors.user_id"
                        :disabled="!!editingId"
                    />
                    <small v-if="form.errors.user_id" class="text-red-500">{{ form.errors.user_id }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">제품 (미선택 시 전체)</label>
                    <Select
                        v-model="form.product_id"
                        :options="productOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="전체 (제품 구분 없음)"
                        :disabled="!!editingId"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">기간 유형 *</label>
                        <Select
                            v-model="form.period_type"
                            :options="periodTypesForForm"
                            option-label="label"
                            option-value="value"
                            :disabled="!!editingId"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">기간 *</label>
                        <InputText
                            v-model="form.period"
                            :placeholder="periodPlaceholder"
                            :invalid="!!form.errors.period"
                            :disabled="!!editingId"
                        />
                        <small v-if="form.errors.period" class="text-red-500">{{ form.errors.period }}</small>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">목표 금액 *</label>
                    <InputNumber
                        v-model="form.target_amount"
                        :min="1"
                        mode="currency"
                        currency="KRW"
                        locale="ko-KR"
                        :invalid="!!form.errors.target_amount"
                        class="w-full"
                    />
                    <small v-if="form.errors.target_amount" class="text-red-500">{{ form.errors.target_amount }}</small>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined @click="dialogVisible = false" />
                    <Button
                        type="submit"
                        :label="editingId ? '수정' : '등록'"
                        icon="pi pi-check"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
