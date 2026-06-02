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
import Tag from 'primevue/tag';

interface CodeGroup {
    id: number;
    group_code: string;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    definitions_count: number;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    codeGroups: Paginated<CodeGroup>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

const reload = (page?: number) => {
    router.get(
        route('platform.code-groups.index'),
        { search: search.value || undefined, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, () => reload(), { debounce: 400 });

const onPage = (e: { page: number }) => reload(e.page + 1);
</script>

<template>
    <Head title="코드 그룹 관리" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">코드 그룹 관리</h1>
                    <p class="text-surface-500 mt-1 text-sm">공통 코드 그룹과 하위 코드 정의 — 전체 {{ codeGroups.total }}개</p>
                </div>
                <Link :href="route('platform.code-groups.create')">
                    <Button label="코드 그룹 등록" icon="pi pi-plus" />
                </Link>
            </div>

            <div class="flex items-center gap-2">
                <InputText v-model="search" placeholder="코드 그룹 값·라벨 검색" class="w-full md:w-[28rem]" />
            </div>

            <DataTable :value="codeGroups.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 코드 그룹이 없습니다.</div>
                </template>
                <Column header="코드 그룹">
                    <template #body="{ data }">
                        <Link :href="route('platform.code-groups.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.name }}
                        </Link>
                        <div class="text-xs text-surface-400 mt-1 font-mono">{{ data.group_code }}</div>
                    </template>
                </Column>
                <Column header="설명">
                    <template #body="{ data }">
                        <span class="text-sm text-surface-500">{{ data.description ?? '-' }}</span>
                    </template>
                </Column>
                <Column header="코드 수" style="width: 90px">
                    <template #body="{ data }">{{ data.definitions_count }}개</template>
                </Column>
                <Column header="정렬" style="width: 80px">
                    <template #body="{ data }">{{ data.sort_order }}</template>
                </Column>
                <Column header="상태" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="data.is_active ? '활성' : '비활성'"
                             :severity="data.is_active ? 'success' : 'secondary'" />
                    </template>
                </Column>
            </DataTable>

            <Paginator v-if="codeGroups.total > codeGroups.per_page" :rows="codeGroups.per_page"
                       :total-records="codeGroups.total"
                       :first="(codeGroups.current_page - 1) * codeGroups.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
