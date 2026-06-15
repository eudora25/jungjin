<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: 'platform' | 'pharma' | 'cso';
    is_active: boolean;
    tenant_name: string | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    users: Paginated<UserRow>;
    filters: { search: string; role: string | null };
}>();

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? null);

const roleOptions = [
    { label: '플랫폼 운영자', value: 'platform' },
    { label: '제약사 관리자', value: 'pharma' },
    { label: '영업사원', value: 'cso' },
];

const roleLabel = (r: string) =>
    ({ platform: '플랫폼 운영자', pharma: '제약사 관리자', cso: '영업(CSO)' })[r] ?? r;
const roleSeverity = (r: string) =>
    ({ platform: 'danger', pharma: 'info', cso: 'secondary' })[r] as any;

const refresh = () => {
    router.get(route('platform.users.index'),
        { search: search.value || undefined, role: role.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true });
};
debouncedWatch([search, role], refresh, { debounce: 300 });

const onPage = (e: { page: number }) => {
    router.get(route('platform.users.index'),
        { search: search.value || undefined, role: role.value || undefined, page: e.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head title="사용자 관리 (전역)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">사용자 관리 <span class="text-base font-normal text-surface-500">(전체 제약사)</span></h1>
                    <p class="text-surface-500 mt-1 text-sm">모든 제약사의 계정 — 전체 {{ users.total }}명 · platform 계정은 콘솔에서 관리</p>
                </div>
                <Link :href="route('platform.users.create')">
                    <Button label="사용자 등록" icon="pi pi-user-plus" />
                </Link>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
                <InputText v-model="search" placeholder="이름·이메일 검색" class="w-full sm:w-80" />
                <Select v-model="role" :options="roleOptions" option-label="label" option-value="value"
                        placeholder="권한" show-clear class="w-full sm:w-48" />
            </div>

            <DataTable :value="users.data" striped-rows class="text-sm">
                <template #empty>
                    <div class="text-center py-10 text-surface-500">사용자가 없습니다.</div>
                </template>
                <Column header="제약사" style="width: 160px">
                    <template #body="{ data }">
                        <Tag :value="data.tenant_name ?? '— (플랫폼)'" severity="contrast" />
                    </template>
                </Column>
                <Column header="이름" style="min-width: 140px">
                    <template #body="{ data }">
                        <Link :href="route('platform.users.show', data.id)" class="font-medium hover:text-primary">{{ data.name }}</Link>
                    </template>
                </Column>
                <Column header="이메일" field="email" />
                <Column header="권한" style="width: 130px">
                    <template #body="{ data }">
                        <Tag :value="roleLabel(data.role)" :severity="roleSeverity(data.role)" />
                    </template>
                </Column>
                <Column header="활성" style="width: 80px">
                    <template #body="{ data }">
                        <Tag :value="data.is_active ? '활성' : '비활성'" :severity="data.is_active ? 'success' : 'danger'" />
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="users.per_page" :total-records="users.total"
                       :first="(users.current_page - 1) * users.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
