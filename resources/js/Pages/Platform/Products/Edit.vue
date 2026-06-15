<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from '@/Pages/Products/Partials/ProductForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

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
    drug_type: 'general' | 'etc' | 'narcotic' | 'psychotropic';
    storage_condition: 'room' | 'cold' | 'frozen';
    nims_item_code: string | null;
    description: string | null;
    price: string | number | null;
    status: 'active' | 'inactive' | 'discontinued';
    remarks: string | null;
    image_url: string | null;
}

const props = defineProps<{ product: Product }>();

const form = useForm({
    _method: 'put',
    insurance_code: props.product.insurance_code,
    standard_code: props.product.standard_code ?? '',
    barcode_gtin: props.product.barcode_gtin ?? '',
    product_code: props.product.product_code,
    product_name: props.product.product_name,
    generic_name: props.product.generic_name ?? '',
    strength: props.product.strength ?? '',
    unit: props.product.unit ?? '',
    pack_size: props.product.pack_size ?? null,
    manufacturer: props.product.manufacturer ?? '',
    category: props.product.category ?? '',
    drug_type: props.product.drug_type ?? 'general',
    storage_condition: props.product.storage_condition ?? 'room',
    nims_item_code: props.product.nims_item_code ?? '',
    description: props.product.description ?? '',
    price: props.product.price !== null ? Number(props.product.price) : null,
    status: props.product.status,
    remarks: props.product.remarks ?? '',
    change_reason: '',
    image: null as File | null,
    remove_image: false,
});

const submit = () => form.post(route('platform.products.update', props.product.id), { forceFormData: true });
</script>

<template>
    <Head title="의약품 수정 (플랫폼)" />

    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">의약품 수정</h1>
                    <p class="text-surface-500 text-sm mt-1">{{ product.tenant_name ?? '제약사 미지정' }} · {{ product.product_name }}</p>
                </div>
                <Link :href="route('platform.products.show', product.id)">
                    <Button label="상세로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <ProductForm
                :form="form"
                :existing-image-url="product.image_url"
                submit-label="저장"
                @submit="submit"
            />
        </div>
    </AdminLayout>
</template>
