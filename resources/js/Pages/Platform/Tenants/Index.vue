<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBusinessNumber } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';

interface TenantAdmin {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
}

interface Tenant {
    id: number;
    name: string;
    code: string | null;
    business_registration_number: string | null;
    status: 'active' | 'inactive';
    users_count: number;
    users: TenantAdmin[];
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    tenants: Paginated<Tenant>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

const refresh = () => {
    router.get(
        route('platform.tenants.index'),
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

debouncedWatch(search, refresh, { debounce: 400 });

const onPage = (e: { page: number }) => {
    router.get(
        route('platform.tenants.index'),
        { search: search.value || undefined, page: e.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

// 관리자 계정 전체 목록 레이어 팝업
const adminsDialog = ref<Tenant | null>(null);
const openAdmins = (tenant: Tenant) => {
    adminsDialog.value = tenant;
};
</script>

<template>
    <Head title="제약사 관리" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">제약사 관리</h1>
                    <p class="text-surface-500 mt-1 text-sm">플랫폼에 입주한 제약사 — 전체 {{ tenants.total }}곳</p>
                </div>
                <Link :href="route('platform.tenants.create')">
                    <Button label="제약사 등록" icon="pi pi-plus" />
                </Link>
            </div>

            <div class="flex items-center gap-2">
                <InputText v-model="search" placeholder="제약사명·코드·사업자번호 검색" class="w-full md:w-[28rem]" />
            </div>

            <DataTable :value="tenants.data" striped-rows>
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 제약사가 없습니다.</div>
                </template>
                <Column header="제약사명">
                    <template #body="{ data }">
                        <Link :href="route('platform.tenants.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.name }}
                        </Link>
                        <div v-if="data.code" class="text-xs text-surface-400 mt-1">{{ data.code }}</div>
                    </template>
                </Column>
                <Column header="사업자번호" style="width: 140px">
                    <template #body="{ data }">{{ formatBusinessNumber(data.business_registration_number) }}</template>
                </Column>
                <Column header="관리자 계정" style="min-width: 220px">
                    <template #body="{ data }">
                        <div v-if="data.users.length" class="flex items-center gap-2">
                            <span class="font-medium">{{ data.users[0].name }}</span>
                            <span class="text-surface-400 text-xs">{{ data.users[0].email }}</span>
                            <Tag v-if="!data.users[0].is_active" value="비활성" severity="secondary" />
                            <Button v-if="data.users.length > 1"
                                    :label="`외 ${data.users.length - 1}명`"
                                    size="small" severity="secondary" text rounded
                                    @click="openAdmins(data)" />
                        </div>
                        <span v-else class="text-surface-400 text-sm">관리자 미지정</span>
                    </template>
                </Column>
                <Column header="소속 사용자" style="width: 100px">
                    <template #body="{ data }">{{ data.users_count }}명</template>
                </Column>
                <Column header="상태" style="width: 90px">
                    <template #body="{ data }">
                        <Tag :value="data.status === 'active' ? '활성' : '비활성'"
                             :severity="data.status === 'active' ? 'success' : 'secondary'" />
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="tenants.per_page" :total-records="tenants.total"
                       :first="(tenants.current_page - 1) * tenants.per_page" @page="onPage" />
        </div>

        <Dialog :visible="!!adminsDialog" modal :style="{ width: '32rem' }"
                :header="adminsDialog ? `${adminsDialog.name} — 관리자 계정` : '관리자 계정'"
                @update:visible="(v) => { if (!v) adminsDialog = null; }">
            <div v-if="adminsDialog" class="flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
                <div v-for="admin in adminsDialog.users" :key="admin.id"
                     class="flex items-center justify-between gap-3 py-2">
                    <div class="flex flex-col">
                        <span class="font-medium">{{ admin.name }}</span>
                        <span class="text-surface-400 text-xs">{{ admin.email }}</span>
                    </div>
                    <Tag :value="admin.is_active ? '활성' : '비활성'"
                         :severity="admin.is_active ? 'success' : 'secondary'" />
                </div>
            </div>
            <template #footer>
                <Link v-if="adminsDialog" :href="route('platform.tenants.show', adminsDialog.id)">
                    <Button label="제약사 상세로" icon="pi pi-arrow-right" icon-pos="right" text />
                </Link>
                <Button label="닫기" severity="secondary" outlined @click="adminsDialog = null" />
            </template>
        </Dialog>
    </AdminLayout>
</template>
