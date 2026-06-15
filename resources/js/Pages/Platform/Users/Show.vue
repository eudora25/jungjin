<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import ConfirmDialog from 'primevue/confirmdialog';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: 'platform' | 'pharma' | 'cso';
    is_active: boolean;
    tenant_name: string | null;
    created_at: string | null;
}

const props = defineProps<{ user: AppUser; manageable: boolean }>();

const roleLabel: Record<string, string> = {
    platform: '플랫폼 운영자',
    pharma: '제약사 관리자',
    cso: '영업(CSO)',
};
const roleSeverity: Record<string, string> = { platform: 'danger', pharma: 'info', cso: 'secondary' };

const confirm = useConfirm();
const confirmDelete = () => {
    confirm.require({
        message: `${props.user.name} 을(를) 삭제하시겠습니까? (되돌릴 수 없습니다)`,
        header: '사용자 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('platform.users.destroy', props.user.id)),
    });
};

const toggleActive = () => router.post(route('platform.users.toggle-active', props.user.id));
</script>

<template>
    <Head :title="user.name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">사용자 상세 ({{ user.tenant_name ?? '플랫폼' }})</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('platform.users.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <template v-if="manageable">
                        <Button :label="user.is_active ? '비활성화' : '활성화'" :icon="user.is_active ? 'pi pi-ban' : 'pi pi-check-circle'"
                                severity="secondary" outlined @click="toggleActive" />
                        <Link :href="route('platform.users.edit', user.id)">
                            <Button label="수정" icon="pi pi-pencil" />
                        </Link>
                        <Button label="삭제" icon="pi pi-trash" severity="danger" outlined @click="confirmDelete" />
                    </template>
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="detail-grid">
                        <div>
                            <dt class="field-label">소속 제약사</dt>
                            <dd><Tag :value="user.tenant_name ?? '— (플랫폼)'" severity="contrast" /></dd>
                        </div>
                        <div>
                            <dt class="field-label">권한</dt>
                            <dd><Tag :value="roleLabel[user.role] ?? user.role" :severity="roleSeverity[user.role] as any" /></dd>
                        </div>
                        <div>
                            <dt class="field-label">상태</dt>
                            <dd><Tag :value="user.is_active ? '활성' : '비활성'" :severity="user.is_active ? 'success' : 'secondary'" /></dd>
                        </div>
                        <div>
                            <dt class="field-label">가입일</dt>
                            <dd>{{ user.created_at ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="field-label">이메일</dt>
                            <dd>{{ user.email }}</dd>
                        </div>
                    </dl>
                    <p v-if="!manageable" class="text-sm text-surface-500 mt-4">
                        <i class="pi pi-info-circle mr-1" />플랫폼 운영자 계정은 콘솔(artisan)에서 관리합니다.
                    </p>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
