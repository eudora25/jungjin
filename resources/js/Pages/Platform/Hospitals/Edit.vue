<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import HospitalForm from '@/Pages/Clients/Hospitals/Partials/HospitalForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

interface Hospital {
    id: number;
    hospital_code: string | null;
    hospital_name: string;
    business_registration_number: string | null;
    hospital_type: string | null;
    specialty: string | null;
    representative_name: string | null;
    postcode: string | null;
    address: string | null;
    phone: string | null;
    contact_person_name: string | null;
    contact_phone: string | null;
    email: string | null;
    remarks: string | null;
    status: 'active' | 'inactive';
}

const props = defineProps<{ hospital: Hospital; types: string[] }>();

const form = useForm({
    hospital_code: props.hospital.hospital_code,
    hospital_name: props.hospital.hospital_name,
    business_registration_number: props.hospital.business_registration_number,
    hospital_type: props.hospital.hospital_type,
    specialty: props.hospital.specialty,
    representative_name: props.hospital.representative_name,
    postcode: props.hospital.postcode,
    address: props.hospital.address,
    phone: props.hospital.phone,
    contact_person_name: props.hospital.contact_person_name,
    contact_phone: props.hospital.contact_phone,
    email: props.hospital.email,
    remarks: props.hospital.remarks,
    status: props.hospital.status,
});

const submit = () => form.put(route('platform.hospitals.update', props.hospital.id));
</script>

<template>
    <Head :title="`${hospital.hospital_name} 수정`" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">병의원 수정</h1>
                <Link :href="route('platform.hospitals.show', hospital.id)">
                    <Button label="상세로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <Card>
                <template #content>
                    <HospitalForm :form="form" :types="types" />
                    <div class="flex justify-end gap-2 mt-6">
                        <Link :href="route('platform.hospitals.show', hospital.id)">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button label="저장" icon="pi pi-check" :loading="form.processing" @click="submit" />
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
