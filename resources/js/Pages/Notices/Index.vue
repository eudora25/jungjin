<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Notice {
    id: number;
    title: string;
    is_pinned: boolean;
    view_count: number;
    files_count: number;
    created_at: string;
    author: { id: number; name: string; email: string } | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    notices: Paginator<Notice>;
    filters: { search: string };
    can: { create: boolean };
}>();

const search = ref(props.filters.search ?? '');

const refresh = () => {
    router.get(
        route('notices.index'),
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, refresh, { debounce: 400 });

const resetFilters = () => {
    search.value = '';
    refresh();
};

const onPage = (event: { page: number }) => {
    router.get(
        route('notices.index'),
        { search: search.value || undefined, page: event.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('ko-KR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
</script>

<template>
    <Head title="공지사항" />

    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">공지사항</h1>
                    <p class="text-surface-500 dark:text-surface-400 mt-1">전사 공지를 확인합니다. 전체 {{ notices.total }}건</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="can.create" :href="route('notices.create')">
                        <Button label="새 공지" icon="pi pi-plus" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:flex-wrap gap-3">
                <InputText v-model="search" placeholder="제목 검색" class="w-full md:w-[28rem]" />
                <div class="flex items-center gap-2 w-full md:w-auto md:ml-auto">
                    <Button icon="pi pi-filter" label="검색" severity="info" @click="refresh" />
                    <Button icon="pi pi-times" label="초기화" severity="secondary" outlined @click="resetFilters" />
                </div>
            </div>

            <DataTable
                :value="notices.data"
                stripedRows
                responsiveLayout="scroll"
                :rowClass="(row) => (row.is_pinned ? 'bg-amber-50 dark:bg-amber-950/20' : '')"
            >
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 공지가 없습니다.</div>
                </template>

                <Column header="" style="width: 72px; white-space: nowrap">
                    <template #body="{ data }">
                        <Tag v-if="data.is_pinned" value="필수" severity="warn" class="whitespace-nowrap" />
                    </template>
                </Column>

                <Column header="제목">
                    <template #body="{ data }">
                        <Link
                            :href="route('notices.show', data.id)"
                            class="font-medium text-surface-900 dark:text-surface-0 hover:text-primary"
                        >
                            {{ data.title }}
                        </Link>
                        <i v-if="data.files_count > 0" class="pi pi-paperclip text-surface-400 ml-2 text-xs" />
                    </template>
                </Column>

                <Column header="작성자" body-class="text-center" style="width: 180px">
                    <template #body="{ data }">
                        <span class="text-sm">{{ data.author?.name ?? '-' }}</span>
                    </template>
                </Column>

                <Column header="조회" style="width: 80px; text-align: right">
                    <template #body="{ data }">
                        <span class="text-sm text-surface-500">{{ data.view_count }}</span>
                    </template>
                </Column>

                <Column header="작성일" body-class="text-center" style="width: 200px">
                    <template #body="{ data }">
                        <span class="text-sm text-surface-500">{{ formatDate(data.created_at) }}</span>
                    </template>
                </Column>
            </DataTable>

            <Paginator
                :rows="notices.per_page"
                :totalRecords="notices.total"
                :first="(notices.current_page - 1) * notices.per_page"
                @page="onPage"
            />
        </div>
    </AdminLayout>
</template>
