<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Message from 'primevue/message';

interface SalesUser {
    id: number;
    name: string;
    email: string;
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
    sales: Paginated<SalesUser>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

const refresh = () => {
    router.get(
        route('sales.index'),
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, refresh, { debounce: 400 });

const onPage = (e: { page: number }) => {
    router.get(
        route('sales.index'),
        { search: search.value || undefined, page: e.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('ko-KR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso)) : '-';
</script>

<template>
    <Head title="영업사원 목록" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-2xl font-bold">영업사원</h1>
                <p class="text-surface-500 mt-1 text-sm">전체 {{ sales.total }}명 — 신규 가입/권한 변경은 사용자 관리 페이지에서 처리합니다.</p>
            </div>

            <Message severity="info" :closable="false">
                영업사원은 시스템 사용자(role=sales)로 관리됩니다. 전체 사용자 관리는
                <Link :href="'/users'" class="underline">사용자 관리</Link>
                에서 이용할 수 있습니다.
            </Message>

            <InputText v-model="search" placeholder="이름·이메일 검색" class="w-96" />

            <DataTable :value="sales.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 영업사원이 없습니다.</div>
                </template>
                <Column header="이름" field="name">
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.name }}</span>
                    </template>
                </Column>
                <Column header="이메일" field="email" />
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

            <Paginator :rows="sales.per_page" :total-records="sales.total"
                       :first="(sales.current_page - 1) * sales.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
