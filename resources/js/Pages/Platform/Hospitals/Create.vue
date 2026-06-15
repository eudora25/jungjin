<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import HospitalForm from '@/Pages/Clients/Hospitals/Partials/HospitalForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

defineProps<{ types: string[] }>();

const form = useForm({
    hospital_code: null as string | null,
    hospital_name: '',
    business_registration_number: null as string | null,
    hospital_type: null as string | null,
    specialty: null as string | null,
    representative_name: null as string | null,
    postcode: null as string | null,
    address: null as string | null,
    phone: null as string | null,
    contact_person_name: null as string | null,
    contact_phone: null as string | null,
    email: null as string | null,
    remarks: null as string | null,
    status: 'active' as 'active' | 'inactive',
});

const submit = () => form.post(route('platform.hospitals.store'));
</script>

<template>
    <Head title="병의원 등록 (플랫폼)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">병의원 등록</h1>
                <Link :href="route('platform.hospitals.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <Card>
                <template #content>
                    <HospitalForm :form="form" :types="types" />
                    <div class="flex justify-end gap-2 mt-6">
                        <Link :href="route('platform.hospitals.index')">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button label="등록" icon="pi pi-check" :loading="form.processing" @click="submit" />
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
