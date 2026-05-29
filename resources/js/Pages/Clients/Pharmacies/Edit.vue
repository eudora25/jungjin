<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PharmacyForm from './Partials/PharmacyForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

interface Pharmacy {
    id: number;
    pharmacy_code: string | null;
    pharmacy_name: string;
    business_registration_number: string | null;
    representative_name: string | null;
    postcode: string | null;
    address: string | null;
    landline_phone: string | null;
    mobile_phone: string | null;
    contact_person_name: string | null;
    contact_phone: string | null;
    email: string | null;
    remarks: string | null;
    status: 'active' | 'inactive';
}

const props = defineProps<{ pharmacy: Pharmacy }>();

const form = useForm({
    pharmacy_code: props.pharmacy.pharmacy_code,
    pharmacy_name: props.pharmacy.pharmacy_name,
    business_registration_number: props.pharmacy.business_registration_number,
    representative_name: props.pharmacy.representative_name,
    postcode: props.pharmacy.postcode,
    address: props.pharmacy.address,
    landline_phone: props.pharmacy.landline_phone,
    mobile_phone: props.pharmacy.mobile_phone,
    contact_person_name: props.pharmacy.contact_person_name,
    contact_phone: props.pharmacy.contact_phone,
    email: props.pharmacy.email,
    remarks: props.pharmacy.remarks,
    status: props.pharmacy.status,
});

const submit = () => form.put(route('pharmacies.update', props.pharmacy.id));
</script>

<template>
    <Head title="약국 수정" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">약국 수정</h1>
                <Link :href="route('pharmacies.show', pharmacy.id)">
                    <Button label="취소" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <Card>
                <template #content>
                    <PharmacyForm :form="form" />
                    <div class="flex justify-end gap-2 mt-6">
                        <Link :href="route('pharmacies.show', pharmacy.id)">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button label="저장" icon="pi pi-check" :loading="form.processing" @click="submit" />
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
