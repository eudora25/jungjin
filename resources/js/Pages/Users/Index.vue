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

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'sales';
    is_active: boolean;
    last_sign_in_at: string | null;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    users: Paginated<AppUser>;
    filters: { search: string; role: string | null; active: string | null };
}>();

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? null);
const active = ref(props.filters.active ?? null);

const roleOptions = [
    { label: '전체', value: null },
    { label: '관리자', value: 'admin' },
    { label: '영업사원', value: 'sales' },
];

const activeOptions = [
    { label: '전체', value: null },
    { label: '활성', value: '1' },
    { label: '비활성', value: '0' },
];

const refresh = () => {
    router.get(
        route('users.index'),
        {
            search: search.value || undefined,
            role: role.value || undefined,
            active: active.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, refresh, { debounce: 400 });

const resetFilters = () => {
    search.value = '';
    role.value = null;
    active.value = null;
    refresh();
};

const onPage = (e: { page: number }) => {
    router.get(
        route('users.index'),
        {
            search: search.value || undefined,
            role: role.value || undefined,
            active: active.value || undefined,
            page: e.page + 1,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('ko-KR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso)) : '-';
</script>

<template>
    <Head title="사용자 관리" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">사용자 관리</h1>
                    <p class="text-surface-500 mt-1 text-sm">전체 {{ users.total }}명 — admin 전용</p>
                </div>
                <Link :href="route('users.create')">
                    <Button label="사용자 등록" icon="pi pi-user-plus" />
                </Link>
            </div>

            <div class="flex flex-col md:flex-row md:flex-wrap gap-3">
                <InputText v-model="search" placeholder="이름·이메일 검색" class="w-full md:w-[28rem]" />
                <Select
                    v-model="role"
                    :options="roleOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="권한"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <Select
                    v-model="active"
                    :options="activeOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="상태"
                    class="w-full md:w-[220px] shrink-0"
                    @change="refresh"
                />
                <div class="flex items-center gap-2 w-full md:w-auto md:ml-auto">
                    <Button icon="pi pi-filter" label="검색" severity="info" @click="refresh" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilters" />
                </div>
            </div>

            <DataTable :value="users.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 사용자가 없습니다.</div>
                </template>
                <Column header="이름" field="name">
                    <template #body="{ data }">
                        <Link :href="route('users.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.name }}
                        </Link>
                    </template>
                </Column>
                <Column header="이메일" field="email" />
                <Column header="권한" style="width: 100px">
                    <template #body="{ data }">
                        <Tag :value="data.role === 'admin' ? '관리자' : '영업사원'"
                             :severity="data.role === 'admin' ? 'warn' : 'info'" />
                    </template>
                </Column>
                <Column header="상태" style="width: 100px">
                    <template #body="{ data }">
                        <Tag :value="data.is_active ? '활성' : '비활성'"
                             :severity="data.is_active ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column header="최근 로그인" style="width: 180px">
                    <template #body="{ data }">
                        <span class="text-sm text-surface-500">{{ formatDate(data.last_sign_in_at) }}</span>
                    </template>
                </Column>
                <Column header="가입일" style="width: 180px">
                    <template #body="{ data }">
                        <span class="text-sm text-surface-500">{{ formatDate(data.created_at) }}</span>
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="users.per_page" :total-records="users.total"
                       :first="(users.current_page - 1) * users.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
