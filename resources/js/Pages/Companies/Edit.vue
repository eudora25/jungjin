<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from './Partials/CompanyForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Company {
    id: number;
    company_name: string;
    business_registration_number: string | null;
    representative_name: string | null;
    company_group: string | null;
    default_commission_grade: string | null;
    postcode: string | null;
    business_address: string | null;
    contact_person_name: string | null;
    landline_phone: string | null;
    mobile_phone: string | null;
    mobile_phone_2: string | null;
    email: string | null;
    receive_email: string | null;
    assigned_pharmacist_contact: string | null;
    remarks: string | null;
    status: string;
    approval_status: string;
}

const props = defineProps<{ company: Company }>();

const form = useForm({
    _method: 'put',
    company_name: props.company.company_name,
    business_registration_number: props.company.business_registration_number ?? '',
    representative_name: props.company.representative_name ?? '',
    company_group: props.company.company_group ?? '',
    default_commission_grade: props.company.default_commission_grade,
    postcode: props.company.postcode ?? '',
    business_address: props.company.business_address ?? '',
    contact_person_name: props.company.contact_person_name ?? '',
    landline_phone: props.company.landline_phone ?? '',
    mobile_phone: props.company.mobile_phone ?? '',
    mobile_phone_2: props.company.mobile_phone_2 ?? '',
    email: props.company.email ?? '',
    receive_email: props.company.receive_email ?? '',
    assigned_pharmacist_contact: props.company.assigned_pharmacist_contact ?? '',
    remarks: props.company.remarks ?? '',
    status: props.company.status,
    approval_status: props.company.approval_status,
});

const submit = () => form.post(route('companies.update', props.company.id));
</script>

<template>
    <Head title="업체 수정" />

    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl">
            <Link :href="route('companies.show', company.id)" class="text-sm text-surface-500 hover:text-primary">
                <i class="pi pi-arrow-left mr-1" />업체 상세
            </Link>
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">업체 수정</h1>
            <CompanyForm :form="form" submit-label="수정" @submit="submit" />
        </div>
    </AdminLayout>
</template>
