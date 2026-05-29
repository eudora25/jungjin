<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from './Partials/CompanyForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    company_name: '',
    business_registration_number: '',
    representative_name: '',
    company_group: '',
    default_commission_grade: null as string | null,
    postcode: '',
    business_address: '',
    contact_person_name: '',
    landline_phone: '',
    mobile_phone: '',
    mobile_phone_2: '',
    email: '',
    receive_email: '',
    assigned_pharmacist_contact: '',
    remarks: '',
    status: 'active',
    approval_status: 'pending',
});

const submit = () => form.post(route('companies.store'));
</script>

<template>
    <Head title="업체 등록" />

    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl">
            <Link :href="route('companies.index')" class="text-sm text-surface-500 hover:text-primary">
                <i class="pi pi-arrow-left mr-1" />업체 목록
            </Link>
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">업체 등록</h1>
            <CompanyForm :form="form" submit-label="등록" @submit="submit" />
        </div>
    </AdminLayout>
</template>
