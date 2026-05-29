<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './Partials/ProductForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
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

const submit = () => form.post(route('products.store'), { forceFormData: true });
</script>

<template>
    <Head title="제품 등록" />

    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl">
            <Link :href="route('products.index')" class="text-sm text-surface-500 hover:text-primary">
                <i class="pi pi-arrow-left mr-1" />제품 목록
            </Link>
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">제품 등록</h1>
            <ProductForm :form="form" submit-label="등록" @submit="submit" />
        </div>
    </AdminLayout>
</template>
