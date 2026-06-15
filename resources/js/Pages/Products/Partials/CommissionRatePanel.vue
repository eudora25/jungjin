<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

interface Rate {
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

const props = defineProps<{
    productId: number;
    rates: Rate[];
    canEdit: boolean;
}>();

const confirm = useConfirm();
const dialogVisible = ref(false);
const editingRate = ref<Rate | null>(null);

const form = useForm({
    base_month: '',
    commission_rate_a: 0,
    commission_rate_b: 0,
    commission_rate_c: 0,
    commission_rate_d: 0,
    commission_rate_e: 0,
    effective_from: '',
    effective_to: '',
    status: 'active',
});

const statusOptions = [
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];

const formatPercent = (v: string | number) => {
    const n = typeof v === 'string' ? parseFloat(v) : v;
    return `${n.toFixed(2)}%`;
};

const formatDate = (iso: string | null) => (iso ? iso.substring(0, 10) : '무제한');

const openCreate = () => {
    editingRate.value = null;
    form.reset();
    form.status = 'active';
    form.commission_rate_a = 0;
    form.commission_rate_b = 0;
    form.commission_rate_c = 0;
    form.commission_rate_d = 0;
    form.commission_rate_e = 0;
    dialogVisible.value = true;
};

const openEdit = (rate: Rate) => {
    editingRate.value = rate;
    form.base_month = rate.base_month;
    form.commission_rate_a = Number(rate.commission_rate_a);
    form.commission_rate_b = Number(rate.commission_rate_b);
    form.commission_rate_c = Number(rate.commission_rate_c);
    form.commission_rate_d = Number(rate.commission_rate_d);
    form.commission_rate_e = Number(rate.commission_rate_e);
    form.effective_from = rate.effective_from.substring(0, 10);
    form.effective_to = rate.effective_to ? rate.effective_to.substring(0, 10) : '';
    form.status = rate.status;
    dialogVisible.value = true;
};

const submit = () => {
    const payload = {
        ...form.data(),
        effective_to: form.effective_to || null,
    };

    const onSuccess = () => {
        dialogVisible.value = false;
        form.reset();
    };

    if (editingRate.value) {
        router.put(
            route('products.commission-rates.update', { product: props.productId, rate: editingRate.value.id }),
            payload,
            { onSuccess, preserveScroll: true },
        );
    } else {
        router.post(
            route('products.commission-rates.store', { product: props.productId }),
            payload,
            { onSuccess, preserveScroll: true },
        );
    }
};

const confirmDelete = (rate: Rate) => {
    confirm.require({
        message: `${rate.base_month} 수수료율을 삭제하시겠습니까?`,
        header: '수수료율 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(
                route('products.commission-rates.destroy', { product: props.productId, rate: rate.id }),
                { preserveScroll: true },
            ),
    });
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">수수료율 ({{ rates.length }}건)</h3>
            <Button v-if="canEdit" label="수수료율 등록" icon="pi pi-plus" size="small" @click="openCreate" />
        </div>

        <DataTable :value="rates" stripedRows responsiveLayout="scroll" size="small">
            <template #empty>
                <div class="text-center py-6 text-surface-500">등록된 수수료율이 없습니다.</div>
            </template>

            <Column header="기준월" field="base_month" style="width: 90px">
                <template #body="{ data }">
                    <span class="font-mono text-sm">{{ data.base_month }}</span>
                </template>
            </Column>
            <Column header="A" style="width: 70px; text-align: right">
                <template #body="{ data }">{{ formatPercent(data.commission_rate_a) }}</template>
            </Column>
            <Column header="B" style="width: 70px; text-align: right">
                <template #body="{ data }">{{ formatPercent(data.commission_rate_b) }}</template>
            </Column>
            <Column header="C" style="width: 70px; text-align: right">
                <template #body="{ data }">{{ formatPercent(data.commission_rate_c) }}</template>
            </Column>
            <Column header="D" style="width: 70px; text-align: right">
                <template #body="{ data }">{{ formatPercent(data.commission_rate_d) }}</template>
            </Column>
            <Column header="E" style="width: 70px; text-align: right">
                <template #body="{ data }">{{ formatPercent(data.commission_rate_e) }}</template>
            </Column>
            <Column header="유효기간" style="width: 200px">
                <template #body="{ data }">
                    <span class="text-sm">{{ formatDate(data.effective_from) }} ~ {{ formatDate(data.effective_to) }}</span>
                </template>
            </Column>
            <Column header="상태" body-class="text-center" style="width: 80px">
                <template #body="{ data }">
                    <Tag
                        :value="data.status === 'active' ? '활성' : '비활성'"
                        :severity="data.status === 'active' ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
            <Column v-if="canEdit" header="" style="width: 100px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" size="small" text @click="openEdit(data)" />
                        <Button icon="pi pi-trash" size="small" severity="danger" text @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog
            v-model:visible="dialogVisible"
            :header="editingRate ? '수수료율 수정' : '수수료율 등록'"
            modal
            :style="{ width: '600px' }"
        >
            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm">기준월 (YYYY-MM) *</label>
                        <InputText v-model="form.base_month" placeholder="2026-04" :invalid="!!form.errors.base_month" />
                        <small v-if="form.errors.base_month" class="text-red-500">{{ form.errors.base_month }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm">상태</label>
                        <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
                    </div>
                </div>

                <div class="grid grid-cols-5 gap-2">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-600">A등급 (%)</label>
                        <InputNumber v-model="form.commission_rate_a" :min="0" :max="100" :max-fraction-digits="2" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-600">B등급 (%)</label>
                        <InputNumber v-model="form.commission_rate_b" :min="0" :max="100" :max-fraction-digits="2" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-600">C등급 (%)</label>
                        <InputNumber v-model="form.commission_rate_c" :min="0" :max="100" :max-fraction-digits="2" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-600">D등급 (%)</label>
                        <InputNumber v-model="form.commission_rate_d" :min="0" :max="100" :max-fraction-digits="2" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-600">E등급 (%)</label>
                        <InputNumber v-model="form.commission_rate_e" :min="0" :max="100" :max-fraction-digits="2" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm">적용 시작일 *</label>
                        <InputText v-model="form.effective_from" type="date" :invalid="!!form.errors.effective_from" />
                        <small v-if="form.errors.effective_from" class="text-red-500">{{ form.errors.effective_from }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm">적용 종료일 (선택)</label>
                        <InputText v-model="form.effective_to" type="date" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined @click="dialogVisible = false" />
                    <Button type="submit" :label="editingRate ? '수정' : '등록'" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </div>
</template>
