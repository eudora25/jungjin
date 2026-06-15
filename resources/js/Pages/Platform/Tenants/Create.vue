<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Message from 'primevue/message';

const form = useForm({
    name: '',
    code: '',
    business_registration_number: '',
    representative_name: '',
    postcode: '',
    address: '',
    phone: '',
    email: '',
    status: 'active',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const statusOptions = [
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];

const submit = () => form.post(route('platform.tenants.store'));
</script>

<template>
    <Head title="제약사 등록" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">제약사 등록</h1>
                <Link :href="route('platform.tenants.index')">
                    <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
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
                                <InputText v-model="form.code" class="w-full" placeholder="선택 (예: HANMI)" />
                                <Message v-if="form.errors.code" severity="error" size="small" variant="simple">{{ form.errors.code }}</Message>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">사업자등록번호</label>
                                <InputText v-model="form.business_registration_number" class="w-full" placeholder="선택" />
                                <Message v-if="form.errors.business_registration_number" severity="error" size="small" variant="simple">{{ form.errors.business_registration_number }}</Message>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">대표자명</label>
                                <InputText v-model="form.representative_name" class="w-full" placeholder="선택" />
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

                        <div class="border-t border-surface-200 dark:border-surface-700 pt-4 mt-2">
                            <h2 class="text-base font-semibold mb-1">초기 관리자 계정</h2>
                            <p class="text-surface-500 text-sm mb-3">이 제약사를 운영할 관리자(pharma) 로그인 계정을 함께 생성합니다.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm mb-1">성명 <span class="text-red-500">*</span></label>
                                    <InputText v-model="form.admin_name" class="w-full" />
                                    <Message v-if="form.errors.admin_name" severity="error" size="small" variant="simple">{{ form.errors.admin_name }}</Message>
                                </div>
                                <div>
                                    <label class="block text-sm mb-1">이메일 <span class="text-red-500">*</span></label>
                                    <InputText v-model="form.admin_email" type="email" class="w-full" placeholder="로그인 아이디로 사용" />
                                    <Message v-if="form.errors.admin_email" severity="error" size="small" variant="simple">{{ form.errors.admin_email }}</Message>
                                </div>
                                <div>
                                    <label class="block text-sm mb-1">비밀번호 <span class="text-red-500">*</span></label>
                                    <InputText v-model="form.admin_password" type="password" class="w-full" />
                                    <Message v-if="form.errors.admin_password" severity="error" size="small" variant="simple">{{ form.errors.admin_password }}</Message>
                                </div>
                                <div>
                                    <label class="block text-sm mb-1">비밀번호 확인 <span class="text-red-500">*</span></label>
                                    <InputText v-model="form.admin_password_confirmation" type="password" class="w-full" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Link :href="route('platform.tenants.index')">
                                <Button label="취소" severity="secondary" outlined type="button" />
                            </Link>
                            <Button label="등록" icon="pi pi-check" type="submit" :loading="form.processing" />
                        </div>
                    </form>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
