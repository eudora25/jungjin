<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBusinessNumber } from '@/utils/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';

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
    users_count: number;
}

interface TenantUser {
    id: number;
    name: string;
    email: string;
    role: 'pharma' | 'cso';
    is_active: boolean;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    tenant: Tenant;
    users: Paginated<TenantUser>;
    filters: { search: string };
    can: { update: boolean; delete: boolean; manageAdmins: boolean };
}>();

const confirm = useConfirm();

// 소속 사용자 검색·페이지네이션 (부분 리로드)
const search = ref(props.filters.search ?? '');

const reloadUsers = (page?: number) => {
    router.get(
        route('platform.tenants.show', props.tenant.id),
        { search: search.value || undefined, page },
        { only: ['users', 'filters'], preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, () => reloadUsers(), { debounce: 400 });

const onUsersPage = (e: { page: number }) => reloadUsers(e.page + 1);

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

// 관리자(pharma) 계정 수정 (성명·이메일 — 비밀번호는 '비밀번호 재설정'에서)
const showEditDialog = ref(false);
const editingUser = ref<TenantUser | null>(null);
const editForm = useForm({
    name: '',
    email: '',
});

const openEditDialog = (user: TenantUser) => {
    editingUser.value = user;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    showEditDialog.value = true;
};

const submitEdit = () => {
    if (!editingUser.value) return;
    editForm.put(route('platform.tenants.admins.update', [props.tenant.id, editingUser.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            editForm.reset();
        },
    });
};

// 관리자(pharma) 비밀번호 재설정
const showResetDialog = ref(false);
const resettingUser = ref<TenantUser | null>(null);
const resetForm = useForm({
    password: '',
    password_confirmation: '',
});

const openResetDialog = (user: TenantUser) => {
    resettingUser.value = user;
    resetForm.reset();
    resetForm.clearErrors();
    showResetDialog.value = true;
};

const submitReset = () => {
    if (!resettingUser.value) return;
    resetForm.post(route('platform.tenants.admins.reset-password', [props.tenant.id, resettingUser.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showResetDialog.value = false;
            resetForm.reset();
        },
    });
};

const confirmDeleteAdmin = (user: TenantUser) => {
    confirm.require({
        message: `${roleLabel(user.role)} 계정 ${user.name} (${user.email}) 을(를) 삭제하시겠습니까?`,
        header: '계정 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(route('platform.tenants.admins.destroy', [props.tenant.id, user.id]), {
                preserveScroll: true,
            }),
    });
};

const roleLabel = (r: string) => (r === 'pharma' ? '관리자' : '영업사원');

const enterTenant = () => router.post(route('platform.tenants.enter', props.tenant.id));
</script>

<template>
    <Head :title="tenant.name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ tenant.name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">제약사(테넌트) 상세</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('platform.tenants.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Button label="이 제약사로 진입" icon="pi pi-sign-in" severity="help"
                            @click="enterTenant" />
                    <Link v-if="can.update" :href="route('platform.tenants.edit', tenant.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                    <Button v-if="can.delete" label="삭제" icon="pi pi-trash" severity="danger" outlined @click="confirmDelete" />
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="detail-grid">
                        <div>
                            <dt class="field-label">코드</dt>
                            <dd>{{ tenant.code ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">상태</dt>
                            <dd>
                                <Tag :value="tenant.status === 'active' ? '활성' : '비활성'"
                                     :severity="tenant.status === 'active' ? 'success' : 'secondary'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">사업자등록번호</dt>
                            <dd>{{ formatBusinessNumber(tenant.business_registration_number) }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">대표자명</dt>
                            <dd>{{ tenant.representative_name ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="field-label">사업장 소재지</dt>
                            <dd>
                                <span v-if="tenant.address">
                                    <span v-if="tenant.postcode" class="text-surface-400">({{ tenant.postcode }}) </span>{{ tenant.address }}
                                </span>
                                <span v-else>-</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">연락처</dt>
                            <dd>{{ tenant.phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">이메일</dt>
                            <dd>{{ tenant.email ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">소속 사용자</dt>
                            <dd>{{ tenant.users_count }}명</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>소속 사용자 <span class="text-surface-400 text-sm font-normal">({{ tenant.users_count }}명)</span></span>
                        <Button v-if="can.manageAdmins" label="관리자 계정 생성" icon="pi pi-user-plus" size="small"
                                @click="showAdminDialog = true" />
                    </div>
                </template>
                <template #content>
                    <div class="mb-3">
                        <InputText v-model="search" placeholder="이름·이메일 검색" class="w-full md:w-80" />
                    </div>
                    <DataTable :value="users.data" striped-rows>
                        <template #empty>
                            <div class="text-center py-8 text-surface-500">
                                <span v-if="filters.search">"{{ filters.search }}" 검색 결과가 없습니다.</span>
                                <span v-else>소속 사용자가 없습니다. "관리자 계정 생성"으로 제약사 관리자를 추가하세요.</span>
                            </div>
                        </template>
                        <Column header="이름" field="name" />
                        <Column header="이메일" field="email" />
                        <Column header="권한" body-class="text-center" style="width: 110px">
                            <template #body="{ data }">
                                <Tag :value="roleLabel(data.role)" :severity="data.role === 'pharma' ? 'info' : 'secondary'" />
                            </template>
                        </Column>
                        <Column header="활성" body-class="text-center" style="width: 80px">
                            <template #body="{ data }">
                                <Tag :value="data.is_active ? '활성' : '비활성'" :severity="data.is_active ? 'success' : 'danger'" />
                            </template>
                        </Column>
                        <Column v-if="can.manageAdmins" header="작업" body-class="text-center" style="width: 160px">
                            <template #body="{ data }">
                                <div class="flex gap-1">
                                    <Button icon="pi pi-pencil" size="small" severity="secondary" outlined
                                            v-tooltip.top="'수정'" @click="openEditDialog(data)" />
                                    <Button icon="pi pi-key" size="small" severity="secondary" outlined
                                            v-tooltip.top="'비밀번호 재설정'" @click="openResetDialog(data)" />
                                    <Button icon="pi pi-trash" size="small" severity="danger" outlined
                                            v-tooltip.top="'삭제'" @click="confirmDeleteAdmin(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                    <Paginator v-if="users.total > users.per_page" :rows="users.per_page" :total-records="users.total"
                               :first="(users.current_page - 1) * users.per_page" @page="onUsersPage" />
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

        <Dialog v-model:visible="showEditDialog" modal :header="editingUser ? `${roleLabel(editingUser.role)} 계정 수정` : '계정 수정'" :style="{ width: '28rem' }">
            <form class="flex flex-col gap-3" @submit.prevent="submitEdit">
                <div>
                    <label class="block text-sm mb-1">성명 <span class="text-red-500">*</span></label>
                    <InputText v-model="editForm.name" class="w-full" />
                    <Message v-if="editForm.errors.name" severity="error" size="small" variant="simple">{{ editForm.errors.name }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">이메일 <span class="text-red-500">*</span></label>
                    <InputText v-model="editForm.email" class="w-full" />
                    <Message v-if="editForm.errors.email" severity="error" size="small" variant="simple">{{ editForm.errors.email }}</Message>
                </div>
                <p class="text-xs text-surface-400">비밀번호 변경은 목록의 <i class="pi pi-key" /> 비밀번호 재설정 버튼을 이용하세요.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="showEditDialog = false" />
                    <Button label="저장" icon="pi pi-check" type="submit" :loading="editForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="showResetDialog" modal header="비밀번호 재설정" :style="{ width: '28rem' }">
            <form class="flex flex-col gap-3" @submit.prevent="submitReset">
                <p class="text-sm text-surface-500">
                    {{ resettingUser?.name }} ({{ resettingUser?.email }}) 의 비밀번호를 재설정합니다.
                </p>
                <div>
                    <label class="block text-sm mb-1">새 비밀번호 <span class="text-red-500">*</span></label>
                    <InputText v-model="resetForm.password" type="password" class="w-full" autocomplete="new-password" />
                    <Message v-if="resetForm.errors.password" severity="error" size="small" variant="simple">{{ resetForm.errors.password }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">새 비밀번호 확인 <span class="text-red-500">*</span></label>
                    <InputText v-model="resetForm.password_confirmation" type="password" class="w-full" autocomplete="new-password" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="showResetDialog = false" />
                    <Button label="재설정" icon="pi pi-check" type="submit" :loading="resetForm.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
