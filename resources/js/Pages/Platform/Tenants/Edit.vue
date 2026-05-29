<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Message from 'primevue/message';

interface Tenant {
    id: number;
    name: string;
    code: string | null;
    business_registration_number: string | null;
    status: 'active' | 'inactive';
}

const props = defineProps<{ tenant: Tenant }>();

const form = useForm({
    name: props.tenant.name,
    code: props.tenant.code ?? '',
    business_registration_number: props.tenant.business_registration_number ?? '',
    status: props.tenant.status,
});

const statusOptions = [
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];

const submit = () => form.put(route('platform.tenants.update', props.tenant.id));
</script>

<template>
    <Head :title="`${tenant.name} 수정`" />
    <AdminLayout>
        <div class="max-w-2xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">제약사 수정</h1>
                <Link :href="route('platform.tenants.show', tenant.id)">
                    <Button label="상세로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>

            <Card>
                <template #content>
                    <form class="flex flex-col gap-4" @submit.prevent="submit">
                        <div>
                            <label class="block text-sm mb-1">제약사명 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.name" class="w-full" />
                            <Message v-if="form.errors.name" severity="error" size="small" variant="simple">{{ form.errors.name }}</Message>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">코드</label>
                            <InputText v-model="form.code" class="w-full" />
                            <Message v-if="form.errors.code" severity="error" size="small" variant="simple">{{ form.errors.code }}</Message>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">사업자등록번호</label>
                            <InputText v-model="form.business_registration_number" class="w-full" />
                            <Message v-if="form.errors.business_registration_number" severity="error" size="small" variant="simple">{{ form.errors.business_registration_number }}</Message>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">상태</label>
                            <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Link :href="route('platform.tenants.show', tenant.id)">
                                <Button label="취소" severity="secondary" outlined type="button" />
                            </Link>
                            <Button label="저장" icon="pi pi-check" type="submit" :loading="form.processing" />
                        </div>
                    </form>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
