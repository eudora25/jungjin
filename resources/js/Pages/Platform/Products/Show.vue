<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import ConfirmDialog from 'primevue/confirmdialog';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';

interface Product {
    id: number;
    tenant_name: string | null;
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
    drug_type: string;
    storage_condition: string;
    nims_item_code: string | null;
    description: string | null;
    price: string | number | null;
    status: string;
    approval_status: string;
    remarks: string | null;
    image_url: string | null;
    creator_name: string | null;
    updater_name: string | null;
    updated_at: string | null;
}

const props = defineProps<{ product: Product }>();

const statusLabel: Record<string, string> = { active: '활성', inactive: '비활성', discontinued: '단종' };
const approvalLabel: Record<string, string> = {
    draft: '작성중', pending: '검수대기', reviewed: '검수됨', approved: '승인', rejected: '반려',
};
const drugTypeLabel: Record<string, string> = {
    general: '일반', etc: 'ETC (전문)', narcotic: '마약', psychotropic: '향정신성',
};
const storageLabel: Record<string, string> = { room: '실온', cold: '냉장', frozen: '냉동' };

const approvalSeverity = computed(() =>
    props.product.approval_status === 'approved' ? 'success'
        : props.product.approval_status === 'rejected' ? 'danger' : 'warn');

const strengthUnit = computed(() =>
    [props.product.strength, props.product.unit].filter(Boolean).join(' ') || '-');
const priceText = computed(() =>
    props.product.price != null && props.product.price !== ''
        ? Number(props.product.price).toLocaleString('ko-KR') + ' 원' : '-');

const confirm = useConfirm();
const confirmDelete = () => {
    confirm.require({
        message: `${props.product.product_name} 을(를) 삭제하시겠습니까?`,
        header: '의약품 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('platform.products.destroy', props.product.id)),
    });
};
</script>

<template>
    <Head :title="product.product_name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ product.product_name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">의약품 상세 ({{ product.tenant_name ?? '제약사 미지정' }})</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('platform.products.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link :href="route('platform.products.edit', product.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                    <Button label="삭제" icon="pi pi-trash" severity="danger" outlined @click="confirmDelete" />
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="detail-grid">
                        <div>
                            <dt class="field-label">제약사</dt>
                            <dd><Tag :value="product.tenant_name ?? '-'" severity="contrast" /></dd>
                        </div>
                        <div>
                            <dt class="field-label">상태 / 승인</dt>
                            <dd class="flex gap-2">
                                <Tag :value="statusLabel[product.status] ?? product.status" :severity="product.status === 'active' ? 'success' : 'secondary'" />
                                <Tag :value="approvalLabel[product.approval_status] ?? product.approval_status" :severity="approvalSeverity" />
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">보험코드</dt>
                            <dd>{{ product.insurance_code }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">제품코드</dt>
                            <dd>{{ product.product_code }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">표준코드 (KD)</dt>
                            <dd>{{ product.standard_code ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">바코드 (GTIN)</dt>
                            <dd>{{ product.barcode_gtin ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">성분명</dt>
                            <dd>{{ product.generic_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">함량 / 단위</dt>
                            <dd>{{ strengthUnit }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">포장 수량</dt>
                            <dd>{{ product.pack_size ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">제조사</dt>
                            <dd>{{ product.manufacturer ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">카테고리</dt>
                            <dd>{{ product.category ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">약품 유형</dt>
                            <dd>
                                <Tag :value="drugTypeLabel[product.drug_type] ?? product.drug_type"
                                     :severity="['narcotic', 'psychotropic'].includes(product.drug_type) ? 'warn' : 'secondary'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">보관 조건</dt>
                            <dd>{{ storageLabel[product.storage_condition] ?? product.storage_condition }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">약가</dt>
                            <dd>{{ priceText }}</dd>
                        </div>
                        <div v-if="product.nims_item_code">
                            <dt class="field-label">NIMS 품목코드</dt>
                            <dd>{{ product.nims_item_code }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="field-label">제품 설명</dt>
                            <dd class="whitespace-pre-line">{{ product.description ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="field-label">비고</dt>
                            <dd class="whitespace-pre-line">{{ product.remarks ?? '-' }}</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <!-- 제품 이미지 -->
            <Card v-if="product.image_url">
                <template #title>제품 이미지</template>
                <template #content>
                    <img :src="product.image_url" class="w-40 h-40 object-cover rounded border border-surface-200 dark:border-surface-700" alt="제품 이미지" />
                </template>
            </Card>

            <div class="text-xs text-surface-400">
                등록: {{ product.creator_name ?? '-' }} · 최종 수정: {{ product.updater_name ?? '-' }}
                <span v-if="product.updated_at"> ({{ product.updated_at }})</span>
            </div>
        </div>
    </AdminLayout>
</template>
