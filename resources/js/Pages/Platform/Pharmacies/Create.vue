<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PharmacyForm from '@/Pages/Clients/Pharmacies/Partials/PharmacyForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

const form = useForm({
    pharmacy_code: null as string | null,
    pharmacy_name: '',
    business_registration_number: null as string | null,
    representative_name: null as string | null,
    postcode: null as string | null,
    address: null as string | null,
    landline_phone: null as string | null,
    mobile_phone: null as string | null,
    contact_person_name: null as string | null,
    contact_phone: null as string | null,
    email: null as string | null,
    remarks: null as string | null,
    status: 'active' as 'active' | 'inactive',
});

const submit = () => form.post(route('platform.pharmacies.store'));
</script>

<template>
    <Head title="약국 등록 (플랫폼)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">약국 등록</h1>
                <Link :href="route('platform.pharmacies.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <Card>
                <template #content>
                    <PharmacyForm :form="form" />
                    <div class="flex justify-end gap-2 mt-6">
                        <Link :href="route('platform.pharmacies.index')">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button label="등록" icon="pi pi-check" :loading="form.processing" @click="submit" />
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
