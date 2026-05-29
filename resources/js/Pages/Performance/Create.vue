<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanySearchAutoComplete from '@/Pages/Products/Partials/CompanySearchAutoComplete.vue';
import ProductSearchAutoComplete from '@/Pages/Products/Partials/ProductSearchAutoComplete.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

interface Preview {
    unit_price: number;
    commission_rate: number | null;
    price_source: 'override' | 'product_sale' | 'products_price' | 'manual';
    commission_source: 'override' | 'matrix' | 'manual' | 'none';
    preview: {
        quantity: number;
        unit_price: number;
        commission_rate: number | null;
        subtotal: number;
        commission_amount: number | null;
    };
    product: { is_salable: boolean; approval_status: string; status: string };
    company: { is_approved: boolean; approval_status: string };
}

const props = defineProps<{ today: string }>();

const form = useForm({
    performance_date: new Date(props.today) as Date | null,
    company_id: null as number | null,
    product_id: null as number | null,
    quantity: 1 as number | null,
    note: '' as string,
});

const preview = ref<Preview | null>(null);
const previewLoading = ref(false);
const previewError = ref<string | null>(null);

const toDateString = (d: Date | null): string | null => {
    if (!d) return null;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const canResolve = computed(
    () => !!form.company_id && !!form.product_id && !!form.performance_date,
);

const fetchPreview = async () => {
    if (!canResolve.value) {
        preview.value = null;
        return;
    }
    previewLoading.value = true;
    previewError.value = null;
    try {
        const res = await fetch(route('performance.resolve-preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                company_id: form.company_id,
                product_id: form.product_id,
                performance_date: toDateString(form.performance_date),
                quantity: form.quantity,
            }),
        });
        if (!res.ok) {
            previewError.value = `미리보기 실패 (HTTP ${res.status})`;
            preview.value = null;
            return;
        }
        preview.value = (await res.json()) as Preview;
    } catch (e) {
        previewError.value = '미리보기 중 오류가 발생했습니다.';
        preview.value = null;
    } finally {
        previewLoading.value = false;
    }
};

watch(
    () => [form.company_id, form.product_id, form.performance_date, form.quantity],
    () => {
        fetchPreview();
    },
    { immediate: true },
);

const submit = () => {
    form
        .transform((d) => ({
            ...d,
            performance_date: toDateString(d.performance_date as Date | null),
        }))
        .post(route('performance.store'), { preserveScroll: true });
};

const priceSourceLabel: Record<Preview['price_source'], string> = {
    override: '거래처 예외 단가',
    product_sale: '제품 매출가 이력',
    products_price: '제품 기본 약가(fallback)',
    manual: '수동 입력',
};

const commissionSourceLabel: Record<Preview['commission_source'], string> = {
    override: '거래처 예외 수수료율',
    matrix: '등급 매트릭스',
    manual: '수동 입력',
    none: '없음',
};

const fmt = (n: number | null | undefined) => {
    if (n === null || n === undefined) return '-';
    return n.toLocaleString('ko-KR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};
</script>

<template>
    <Head title="실적 등록" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <Link :href="route('performance.index')" class="text-primary-600 text-sm">
                <i class="pi pi-chevron-left" /> 실적 목록
            </Link>

            <div class="card">
                <h1 class="text-2xl font-bold mb-4">실적 등록</h1>

                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">실적 발생일 *</label>
                        <DatePicker
                            v-model="form.performance_date"
                            date-format="yy-mm-dd"
                            show-icon
                            class="w-full md:w-[220px]"
                            :invalid="!!form.errors.performance_date"
                        />
                        <small v-if="form.errors.performance_date" class="text-red-500">
                            {{ form.errors.performance_date }}
                        </small>
                    </div>

                    <div>
                        <label class="block text-sm mb-1">거래처 *</label>
                        <CompanySearchAutoComplete
                            v-model="form.company_id"
                            :invalid="!!form.errors.company_id"
                            input-class="p-inputtext p-component w-full md:w-[28rem]"
                        />
                        <small v-if="form.errors.company_id" class="text-red-500">{{ form.errors.company_id }}</small>
                    </div>

                    <div>
                        <label class="block text-sm mb-1">제품 *</label>
                        <ProductSearchAutoComplete
                            v-model="form.product_id"
                            :invalid="!!form.errors.product_id"
                            input-class="p-inputtext p-component w-full md:w-[28rem]"
                        />
                        <small v-if="form.errors.product_id" class="text-red-500">{{ form.errors.product_id }}</small>
                    </div>

                    <div>
                        <label class="block text-sm mb-1">수량 *</label>
                        <InputNumber
                            v-model="form.quantity"
                            :use-grouping="true"
                            class="w-full md:w-[220px]"
                            input-class="w-full"
                            :invalid="!!form.errors.quantity"
                        />
                        <small class="text-surface-400 text-xs">반품은 음수로 입력</small>
                        <small v-if="form.errors.quantity" class="block text-red-500">{{ form.errors.quantity }}</small>
                    </div>

                    <div>
                        <label class="block text-sm mb-1">비고</label>
                        <Textarea v-model="form.note" rows="1" auto-resize class="w-full md:w-[28rem]" />
                    </div>

                    <!-- 미리보기 -->
                    <div class="md:col-span-2 border border-surface-200 rounded-lg p-4 bg-surface-50">
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="font-semibold text-sm">스냅샷 미리보기</h2>
                            <span v-if="previewLoading" class="text-xs text-surface-500">계산 중…</span>
                        </div>
                        <Message v-if="previewError" severity="error" :closable="false" class="mb-2">
                            {{ previewError }}
                        </Message>
                        <div v-if="!preview" class="text-sm text-surface-500">
                            거래처·제품·일자·수량을 입력하면 적용될 단가/수수료율이 여기에 표시됩니다.
                        </div>
                        <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <div class="text-xs text-surface-500">단가</div>
                                <div class="text-lg font-semibold">{{ fmt(preview.preview.unit_price) }}원</div>
                                <Tag :value="priceSourceLabel[preview.price_source]"
                                     severity="info" class="mt-1" />
                            </div>
                            <div>
                                <div class="text-xs text-surface-500">수수료율</div>
                                <div class="text-lg font-semibold">
                                    <span v-if="preview.preview.commission_rate !== null">
                                        {{ preview.preview.commission_rate }}%
                                    </span>
                                    <span v-else class="text-surface-400">없음</span>
                                </div>
                                <Tag :value="commissionSourceLabel[preview.commission_source]"
                                     :severity="preview.commission_source === 'none' ? 'secondary' : 'info'"
                                     class="mt-1" />
                            </div>
                            <div>
                                <div class="text-xs text-surface-500">매출 (수량 × 단가)</div>
                                <div class="text-lg font-semibold">{{ fmt(preview.preview.subtotal) }}원</div>
                            </div>
                            <div>
                                <div class="text-xs text-surface-500">수수료</div>
                                <div class="text-lg font-semibold">
                                    <span v-if="preview.preview.commission_amount !== null">
                                        {{ fmt(preview.preview.commission_amount) }}원
                                    </span>
                                    <span v-else class="text-surface-400">-</span>
                                </div>
                            </div>
                            <div class="md:col-span-4" v-if="preview.company && !preview.company.is_approved">
                                <Message severity="error" :closable="false">
                                    해당 거래처는 미승인 상태입니다 ({{ preview.company.approval_status }}). 실적을 등록할 수 없습니다.
                                </Message>
                            </div>
                            <div class="md:col-span-4" v-if="preview.product && !preview.product.is_salable">
                                <Message severity="warn" :closable="false">
                                    해당 제품은 현재 판매 가능 상태가 아닙니다 (승인: {{ preview.product.approval_status }} · 상태: {{ preview.product.status }}).
                                </Message>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-2 mt-2">
                        <Link :href="route('performance.index')">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button type="submit" label="실적 등록" icon="pi pi-check"
                                :loading="form.processing"
                                :disabled="!canResolve || !preview || !preview.company?.is_approved || !preview.product?.is_salable" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
