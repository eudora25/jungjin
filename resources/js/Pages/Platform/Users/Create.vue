<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';

interface TenantOption {
    value: number;
    label: string;
    status: string;
}

defineProps<{ tenants: TenantOption[] }>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'pharma' as 'pharma' | 'cso',
    tenant_id: null as number | null,
    is_active: true,
});

const roleOptions = [
    { label: '제약사 관리자 (pharma)', value: 'pharma' },
    { label: '영업사원 (cso)', value: 'cso' },
];

const submit = () => form.post(route('platform.users.store'));
</script>

<template>
    <Head title="사용자 등록 (플랫폼)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">사용자 등록</h1>
                <Link :href="route('platform.users.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <Card>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">제약사 <span class="text-red-500">*</span></label>
                            <Select v-model="form.tenant_id" :options="tenants" option-label="label" option-value="value"
                                    filter placeholder="소속 제약사 선택" class="w-full" :invalid="!!form.errors.tenant_id" />
                            <small v-if="form.errors.tenant_id" class="text-red-500">{{ form.errors.tenant_id }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">권한 <span class="text-red-500">*</span></label>
                            <Select v-model="form.role" :options="roleOptions" option-label="label" option-value="value"
                                    class="w-full" :invalid="!!form.errors.role" />
                            <small v-if="form.errors.role" class="text-red-500">{{ form.errors.role }}</small>
                            <small v-else class="text-surface-500">플랫폼 운영자 계정은 콘솔(artisan)에서 생성합니다.</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">성명 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.name" class="w-full" :invalid="!!form.errors.name" />
                            <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">이메일 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.email" type="email" class="w-full" :invalid="!!form.errors.email" />
                            <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">비밀번호 <span class="text-red-500">*</span></label>
                            <Password v-model="form.password" toggle-mask class="w-full" input-class="w-full"
                                      :invalid="!!form.errors.password" />
                            <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">비밀번호 확인 <span class="text-red-500">*</span></label>
                            <Password v-model="form.password_confirmation" toggle-mask class="w-full" input-class="w-full" :feedback="false" />
                        </div>
                        <div class="flex items-center gap-2">
                            <ToggleSwitch v-model="form.is_active" />
                            <span class="text-sm">활성 계정</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <Link :href="route('platform.users.index')">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button label="등록" icon="pi pi-check" :loading="form.processing" @click="submit" />
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
