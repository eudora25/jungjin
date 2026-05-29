<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    is_active: boolean;
}

const props = defineProps<{ user: AppUser }>();

const page = usePage();
const isSelf = computed(() => (page.props as any).auth?.user?.id === props.user.id);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role,
    is_active: props.user.is_active,
});

const roleOptions = [
    { label: '관리자', value: 'pharma' },
    { label: '영업사원', value: 'cso' },
];

const submit = () => form.put(route('users.update', props.user.id));
</script>

<template>
    <Head title="사용자 수정" />
    <AdminLayout>
        <div class="max-w-2xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">사용자 수정</h1>
                <Link :href="route('users.show', user.id)">
                    <Button label="취소" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <Card>
                <template #content>
                    <div class="flex flex-col gap-4">
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
                            <Password v-model="form.password" toggle-mask class="w-full" input-class="w-full"
                                      :invalid="!!form.errors.password" />
                            <small class="text-xs text-surface-500">변경하지 않으려면 비워두세요</small>
                            <br />
                            <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                        </div>
                        <div v-if="form.password">
                            <label class="block text-sm font-medium mb-1">새 비밀번호 확인</label>
                            <Password v-model="form.password_confirmation" toggle-mask class="w-full" input-class="w-full"
                                      :feedback="false" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">권한</label>
                            <Select v-model="form.role" :options="roleOptions" option-label="label" option-value="value"
                                    class="w-full" :disabled="isSelf" />
                            <small v-if="isSelf" class="text-xs text-surface-500">자기 자신의 권한은 변경할 수 없습니다</small>
                        </div>
                        <div class="flex items-center gap-2">
                            <ToggleSwitch v-model="form.is_active" :disabled="isSelf" />
                            <span class="text-sm">활성 계정</span>
                            <span v-if="isSelf" class="text-xs text-surface-500 ml-2">(자기 자신은 변경 불가)</span>
                        </div>

                        <div class="flex justify-end gap-2 mt-4">
                            <Link :href="route('users.show', user.id)">
                                <Button label="취소" severity="secondary" outlined />
                            </Link>
                            <Button label="저장" icon="pi pi-check" :loading="form.processing" @click="submit" />
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
