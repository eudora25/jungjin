<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
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
    role: 'super_admin' | 'admin' | 'sales';
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
    { label: '전체', value: null },
    { label: '플랫폼 운영자', value: 'super_admin' },
    { label: '제약사 관리자', value: 'admin' },
    { label: '영업사원', value: 'sales' },
];

const roleLabel = (r: string) =>
    ({ super_admin: '플랫폼 운영자', admin: '제약사 관리자', sales: '영업사원' })[r] ?? r;
const roleSeverity = (r: string) =>
    ({ super_admin: 'danger', admin: 'info', sales: 'secondary' })[r] as any;

const refresh = () => {
    router.get(route('platform.users.index'),
        { search: search.value || undefined, role: role.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true });
};
debouncedWatch(search, refresh, { debounce: 400 });

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
            <div>
                <h1 class="text-2xl font-bold">사용자 관리 <span class="text-base font-normal text-surface-500">(전체 제약사)</span></h1>
                <p class="text-surface-500 mt-1 text-sm">모든 제약사의 계정 — 전체 {{ users.total }}명 · 등록/수정은 다음 단계</p>
            </div>

            <div class="flex flex-col md:flex-row gap-3">
                <InputText v-model="search" placeholder="이름·이메일 검색" class="w-full md:w-[28rem]" />
                <Select v-model="role" :options="roleOptions" option-label="label" option-value="value"
                        placeholder="권한" class="w-full md:w-[220px]" @change="refresh" />
            </div>

            <DataTable :value="users.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">사용자가 없습니다.</div>
                </template>
                <Column header="제약사" style="width: 160px">
                    <template #body="{ data }">
                        <Tag :value="data.tenant_name ?? '— (플랫폼)'" severity="contrast" />
                    </template>
                </Column>
                <Column header="이름" field="name" />
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
