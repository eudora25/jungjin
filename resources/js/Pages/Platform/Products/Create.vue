<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from '@/Pages/Products/Partials/ProductForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Select from 'primevue/select';

interface TenantOption {
    value: number;
    label: string;
    status: string;
}

defineProps<{ tenants: TenantOption[] }>();

const form = useForm({
    tenant_id: null as number | null,
    insurance_code: '',
    standard_code: '',
    barcode_gtin: '',
    product_code: '',
    product_name: '',
    generic_name: '',
    strength: '',
    unit: '',
    pack_size: null as number | null,
    manufacturer: '',
    category: '',
    drug_type: 'general',
    storage_condition: 'room',
    nims_item_code: '',
    description: '',
    price: null as number | null,
    status: 'active',
    remarks: '',
    image: null as File | null,
});

const submit = () => form.post(route('platform.products.store'), { forceFormData: true });
</script>

<template>
    <Head title="의약품 등록 (플랫폼)" />

    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">의약품 등록</h1>
                    <p class="text-surface-500 text-sm mt-1">대상 제약사를 선택해 신규 의약품을 등록합니다.</p>
                </div>
                <Link :href="route('platform.products.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>

            <Card>
                <template #content>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm">제약사 <span class="text-red-500">*</span></label>
                        <Select
                            v-model="form.tenant_id"
                            :options="tenants"
                            option-label="label"
                            option-value="value"
                            filter
                            placeholder="대상 제약사를 선택하세요"
                            :invalid="!!form.errors.tenant_id"
                            class="w-full md:w-[28rem]"
                        />
                        <small v-if="form.errors.tenant_id" class="text-red-500">{{ form.errors.tenant_id }}</small>
                        <small v-else class="text-surface-500">이 의약품이 소속될 제약사입니다(등록 후 변경 불가).</small>
                    </div>
                </template>
            </Card>

            <ProductForm :form="form" submit-label="등록" @submit="submit" />
        </div>
    </AdminLayout>
</template>
