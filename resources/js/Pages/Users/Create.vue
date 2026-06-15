<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'cso' as const, // pharma 는 자사 영업사원(CSO)만 등록
    is_active: true,
});

const submit = () => form.post(route('users.store'));
</script>

<template>
    <Head title="사용자 등록" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">사용자 등록</h1>
                <Link :href="route('users.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>
            <Card>
                <template #content>
                    <div class="flex flex-col gap-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                <Password v-model="form.password_confirmation" toggle-mask class="w-full" input-class="w-full"
                                          :feedback="false" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">권한</label>
                                <div class="flex items-center gap-2 h-[42px]">
                                    <Tag value="영업사원 (CSO)" severity="info" />
                                    <small class="text-surface-500">자사 영업사원만 등록할 수 있습니다(관리자 계정은 플랫폼 운영자가 생성).</small>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 md:pt-7">
                                <ToggleSwitch v-model="form.is_active" />
                                <span class="text-sm">활성 계정</span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 mt-4">
                            <Link :href="route('users.index')">
                                <Button label="취소" severity="secondary" outlined />
                            </Link>
                            <Button label="등록" icon="pi pi-check" :loading="form.processing" @click="submit" />
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
