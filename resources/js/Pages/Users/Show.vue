<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Password from 'primevue/password';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: 'pharma' | 'cso';
    is_active: boolean;
    last_sign_in_at: string | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    user: AppUser;
    can: {
        update: boolean;
        delete: boolean;
        toggleActive: boolean;
        resetPassword: boolean;
    };
}>();

const confirm = useConfirm();

const toggleActive = () => {
    const label = props.user.is_active ? '비활성화' : '활성화';
    confirm.require({
        message: `${props.user.name} 을(를) ${label} 하시겠습니까?`,
        header: `사용자 ${label}`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: label,
        rejectLabel: '취소',
        accept: () => router.post(route('users.toggle-active', props.user.id)),
    });
};

const confirmDelete = () => {
    confirm.require({
        message: `${props.user.name} 을(를) 삭제하시겠습니까? 복구할 수 없습니다.`,
        header: '사용자 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('users.destroy', props.user.id)),
    });
};

const showResetDialog = ref(false);

const resetForm = useForm({
    password: '',
    password_confirmation: '',
});

const submitReset = () => {
    resetForm.post(route('users.reset-password', props.user.id), {
        onSuccess: () => {
            showResetDialog.value = false;
            resetForm.reset();
        },
    });
};

const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('ko-KR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso)) : '-';
</script>

<template>
    <Head :title="user.name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">{{ user.email }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('users.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.update" :href="route('users.edit', user.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                        <div>
                            <dt class="field-label">권한</dt>
                            <dd>
                                <Tag :value="user.role === 'pharma' ? '관리자' : '영업사원'"
                                     :severity="user.role === 'pharma' ? 'warn' : 'info'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">상태</dt>
                            <dd>
                                <Tag :value="user.is_active ? '활성' : '비활성'"
                                     :severity="user.is_active ? 'success' : 'secondary'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">최근 로그인</dt>
                            <dd>{{ formatDate(user.last_sign_in_at) }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">가입일</dt>
                            <dd>{{ formatDate(user.created_at) }}</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #title>계정 관리</template>
                <template #content>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="can.toggleActive"
                                :label="user.is_active ? '비활성화' : '활성화'"
                                :icon="user.is_active ? 'pi pi-lock' : 'pi pi-unlock'"
                                :severity="user.is_active ? 'warn' : 'success'"
                                outlined
                                @click="toggleActive" />
                        <Button v-if="can.resetPassword"
                                label="비밀번호 재설정"
                                icon="pi pi-key"
                                severity="info"
                                outlined
                                @click="showResetDialog = true" />
                        <Button v-if="can.delete"
                                label="계정 삭제"
                                icon="pi pi-trash"
                                severity="danger"
                                outlined
                                @click="confirmDelete" />
                    </div>
                </template>
            </Card>

            <Dialog v-model:visible="showResetDialog" header="비밀번호 재설정" modal :style="{ width: '30rem' }">
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">새 비밀번호</label>
                        <Password v-model="resetForm.password" toggle-mask class="w-full" input-class="w-full"
                                  :invalid="!!resetForm.errors.password" />
                        <small v-if="resetForm.errors.password" class="text-red-500">{{ resetForm.errors.password }}</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">새 비밀번호 확인</label>
                        <Password v-model="resetForm.password_confirmation" toggle-mask class="w-full" input-class="w-full"
                                  :feedback="false" />
                    </div>
                </div>
                <template #footer>
                    <Button label="취소" severity="secondary" outlined @click="showResetDialog = false" />
                    <Button label="재설정" icon="pi pi-check" :loading="resetForm.processing" @click="submitReset" />
                </template>
            </Dialog>
        </div>
    </AdminLayout>
</template>
