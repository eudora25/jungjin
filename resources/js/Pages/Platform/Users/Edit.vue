<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: 'pharma' | 'cso';
    tenant_id: number | null;
    is_active: boolean;
}

interface TenantOption {
    value: number;
    label: string;
    status: string;
}

const props = defineProps<{ user: AppUser; tenants: TenantOption[] }>();

const form = useForm({
    _method: 'put',
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role,
    tenant_id: props.user.tenant_id,
    is_active: props.user.is_active,
});

const roleOptions = [
    { label: '제약사 관리자 (pharma)', value: 'pharma' },
    { label: '영업사원 (cso)', value: 'cso' },
];

const submit = () => form.post(route('platform.users.update', props.user.id));
</script>

<template>
    <Head title="사용자 수정 (플랫폼)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">사용자 수정</h1>
                <Link :href="route('platform.users.show', user.id)">
                    <Button label="상세로" icon="pi pi-arrow-left" severity="secondary" outlined />
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
                            <label class="block text-sm font-medium mb-1">새 비밀번호 (선택)</label>
                            <Password v-model="form.password" toggle-mask class="w-full" input-class="w-full" :invalid="!!form.errors.password" />
                            <small class="text-xs text-surface-500">변경하지 않으려면 비워두세요</small>
                            <br />
                            <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                        </div>
                        <div v-if="form.password">
                            <label class="block text-sm font-medium mb-1">새 비밀번호 확인</label>
                            <Password v-model="form.password_confirmation" toggle-mask class="w-full" input-class="w-full" :feedback="false" />
                        </div>
                        <div class="flex items-center gap-2">
                            <ToggleSwitch v-model="form.is_active" />
                            <span class="text-sm">활성 계정</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <Link :href="route('platform.users.show', user.id)">
                            <Button label="취소" severity="secondary" outlined />
                        </Link>
                        <Button label="저장" icon="pi pi-check" :loading="form.processing" @click="submit" />
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
