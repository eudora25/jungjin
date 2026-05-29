<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './Partials/ProductForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

const submit = () => form.post(route('products.update', props.product.id), { forceFormData: true });
</script>

<template>
    <Head title="제품 수정" />

    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl">
            <Link :href="route('products.show', product.id)" class="text-sm text-surface-500 hover:text-primary">
                <i class="pi pi-arrow-left mr-1" />제품 상세
            </Link>
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">제품 수정</h1>
            <ProductForm
                :form="form"
                :existing-image-url="product.image_url"
                submit-label="수정"
                @submit="submit"
            />
        </div>
    </AdminLayout>
</template>
