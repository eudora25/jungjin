<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';

interface Performance {
    id: number;
    performance_no: string;
    performance_date: string;
    quantity: number;
    note: string | null;
    status: string;
    company: { id: number; company_name: string };
    product: { id: number; product_name: string; insurance_code: string };
}

const props = defineProps<{ performance: Performance }>();

const form = useForm({
    performance_date: new Date(props.performance.performance_date) as Date | null,
    quantity: props.performance.quantity as number | null,
    note: props.performance.note ?? '' as string,
});

const toDateString = (d: Date | null): string | null => {
    if (!d) return null;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const submit = () => {
    form
        .transform((d) => ({
            ...d,
            performance_date: toDateString(d.performance_date as Date | null),
        }))
        .put(route('performance.update', props.performance.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`실적 수정 · ${performance.performance_no}`" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <Link :href="route('performance.show', performance.id)" class="text-primary-600 text-sm">
                <i class="pi pi-chevron-left" /> 실적 상세
            </Link>

            <div class="card">
                <h1 class="text-2xl font-bold mb-2">실적 수정 · {{ performance.performance_no }}</h1>
                <Message severity="info" :closable="false" class="mb-4">
                    거래처/제품은 변경할 수 없습니다 (삭제 후 재등록하세요). 실적일을 변경하면 단가·수수료율 스냅샷이 해당 시점 기준으로 재해석됩니다.
                </Message>

                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <div class="text-sm text-surface-500 mb-2">
                            거래처: <b>{{ performance.company.company_name }}</b>
                            · 제품: <b>{{ performance.product.product_name }}</b>
                            <span class="text-xs text-surface-400">({{ performance.product.insurance_code }})</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm mb-1">실적 발생일 *</label>
                        <DatePicker v-model="form.performance_date" date-format="yy-mm-dd" show-icon class="w-full"
                                    :invalid="!!form.errors.performance_date" />
                        <small v-if="form.errors.performance_date" class="text-red-500">
                            {{ form.errors.performance_date }}
                        </small>
                    </div>

                    <div>
                        <label class="block text-sm mb-1">수량 *</label>
                        <InputNumber v-model="form.quantity" :use-grouping="true" class="w-full"
                                     :invalid="!!form.errors.quantity" />
                        <small v-if="form.errors.quantity" class="block text-red-500">{{ form.errors.quantity }}</small>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">비고</label>
                        <Textarea v-model="form.note" rows="2" auto-resize class="w-full" />
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-2 mt-2">
                        <Link :href="route('performance.show', performance.id)">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button type="submit" label="저장" icon="pi pi-save" :loading="form.processing" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
