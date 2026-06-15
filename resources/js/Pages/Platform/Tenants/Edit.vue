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
    representative_name: string | null;
    postcode: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    status: 'active' | 'inactive';
}

const props = defineProps<{ tenant: Tenant }>();

const form = useForm({
    name: props.tenant.name,
    code: props.tenant.code ?? '',
    business_registration_number: props.tenant.business_registration_number ?? '',
    representative_name: props.tenant.representative_name ?? '',
    postcode: props.tenant.postcode ?? '',
    address: props.tenant.address ?? '',
    phone: props.tenant.phone ?? '',
    email: props.tenant.email ?? '',
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
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">제약사 수정</h1>
                <Link :href="route('platform.tenants.show', tenant.id)">
                    <Button label="상세로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>

            <Card>
                <template #content>
                    <form class="flex flex-col gap-4" @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                <label class="block text-sm mb-1">대표자명</label>
                                <InputText v-model="form.representative_name" class="w-full" />
                                <Message v-if="form.errors.representative_name" severity="error" size="small" variant="simple">{{ form.errors.representative_name }}</Message>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">우편번호</label>
                                <InputText v-model="form.postcode" class="w-full" />
                                <Message v-if="form.errors.postcode" severity="error" size="small" variant="simple">{{ form.errors.postcode }}</Message>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">사업장 소재지</label>
                                <InputText v-model="form.address" class="w-full" placeholder="도로명/지번 주소" />
                                <Message v-if="form.errors.address" severity="error" size="small" variant="simple">{{ form.errors.address }}</Message>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">연락처</label>
                                <InputText v-model="form.phone" class="w-full" />
                                <Message v-if="form.errors.phone" severity="error" size="small" variant="simple">{{ form.errors.phone }}</Message>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">이메일</label>
                                <InputText v-model="form.email" type="email" class="w-full" />
                                <Message v-if="form.errors.email" severity="error" size="small" variant="simple">{{ form.errors.email }}</Message>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">상태</label>
                                <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
                            </div>
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
