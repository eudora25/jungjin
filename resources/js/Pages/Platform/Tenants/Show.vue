<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';

interface Tenant {
    id: number;
    name: string;
    code: string | null;
    business_registration_number: string | null;
    status: 'active' | 'inactive';
    users_count: number;
}

interface TenantUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'sales';
    is_active: boolean;
}

const props = defineProps<{
    tenant: Tenant;
    users: TenantUser[];
    can: { update: boolean; delete: boolean; manageAdmins: boolean };
}>();

const confirm = useConfirm();

const confirmDelete = () => {
    confirm.require({
        message: `${props.tenant.name} 을(를) 삭제하시겠습니까?`,
        header: '제약사 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('platform.tenants.destroy', props.tenant.id)),
    });
};

const showAdminDialog = ref(false);
const adminForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submitAdmin = () =>
    adminForm.post(route('platform.tenants.admins.store', props.tenant.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAdminDialog.value = false;
            adminForm.reset();
        },
    });

const roleLabel = (r: string) => (r === 'admin' ? '관리자' : '영업사원');
</script>

<template>
    <Head :title="tenant.name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="max-w-4xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ tenant.name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">제약사(테넌트) 상세</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('platform.tenants.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.update" :href="route('platform.tenants.edit', tenant.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                    <Button v-if="can.delete" label="삭제" icon="pi pi-trash" severity="danger" outlined @click="confirmDelete" />
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                        <div>
                            <dt class="text-surface-500 mb-1">코드</dt>
                            <dd>{{ tenant.code ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">상태</dt>
                            <dd>
                                <Tag :value="tenant.status === 'active' ? '활성' : '비활성'"
                                     :severity="tenant.status === 'active' ? 'success' : 'secondary'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">사업자등록번호</dt>
                            <dd>{{ tenant.business_registration_number ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">소속 사용자</dt>
                            <dd>{{ tenant.users_count }}명</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>소속 사용자</span>
                        <Button v-if="can.manageAdmins" label="관리자 계정 생성" icon="pi pi-user-plus" size="small"
                                @click="showAdminDialog = true" />
                    </div>
                </template>
                <template #content>
                    <DataTable :value="users" striped-rows>
                        <template #empty>
                            <div class="text-center py-8 text-surface-500">
                                소속 사용자가 없습니다. "관리자 계정 생성"으로 제약사 관리자를 추가하세요.
                            </div>
                        </template>
                        <Column header="이름" field="name" />
                        <Column header="이메일" field="email" />
                        <Column header="권한" style="width: 110px">
                            <template #body="{ data }">
                                <Tag :value="roleLabel(data.role)" :severity="data.role === 'admin' ? 'info' : 'secondary'" />
                            </template>
                        </Column>
                        <Column header="활성" style="width: 80px">
                            <template #body="{ data }">
                                <Tag :value="data.is_active ? '활성' : '비활성'" :severity="data.is_active ? 'success' : 'danger'" />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="showAdminDialog" modal header="제약사 관리자 계정 생성" :style="{ width: '28rem' }">
            <form class="flex flex-col gap-3" @submit.prevent="submitAdmin">
                <div>
                    <label class="block text-sm mb-1">성명 <span class="text-red-500">*</span></label>
                    <InputText v-model="adminForm.name" class="w-full" />
                    <Message v-if="adminForm.errors.name" severity="error" size="small" variant="simple">{{ adminForm.errors.name }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">이메일 <span class="text-red-500">*</span></label>
                    <InputText v-model="adminForm.email" class="w-full" />
                    <Message v-if="adminForm.errors.email" severity="error" size="small" variant="simple">{{ adminForm.errors.email }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">비밀번호 <span class="text-red-500">*</span></label>
                    <InputText v-model="adminForm.password" type="password" class="w-full" />
                    <Message v-if="adminForm.errors.password" severity="error" size="small" variant="simple">{{ adminForm.errors.password }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">비밀번호 확인 <span class="text-red-500">*</span></label>
                    <InputText v-model="adminForm.password_confirmation" type="password" class="w-full" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="showAdminDialog = false" />
                    <Button label="생성" icon="pi pi-check" type="submit" :loading="adminForm.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
