<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { debouncedWatch } from '@vueuse/core';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

interface PharmacyRow {
    id: number;
    region: string | null;
    pharmacy_name: string;
    pharmacy_code: string | null;
    representative_name: string | null;
    phone: string | null;
    business_registration_number: string | null;
    address: string | null;
    status: string;
    matched_old_numbers: string[];
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    pharmacies: Paginated<PharmacyRow>;
    filters: { search: string; region: string };
    regionOptions: string[];
}>();

const search = ref(props.filters.search ?? '');
const region = ref(props.filters.region || null);

const regionSelect = computed(() => props.regionOptions.map((r) => ({ label: r, value: r })));

const query = (extra: Record<string, unknown> = {}) => ({
    search: search.value || undefined,
    region: region.value || undefined,
    ...extra,
});

const refresh = () => {
    router.get(route('platform.pharmacies.index'), query(),
        { preserveState: true, preserveScroll: true, replace: true });
};
debouncedWatch([search, region], refresh, { debounce: 300 });

const onPage = (e: { page: number }) => {
    router.get(route('platform.pharmacies.index'), query({ page: e.page + 1 }),
        { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head title="약국 관리 (공유)" />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">약국 관리 <span class="text-base font-normal text-surface-500">(공유 마스터)</span></h1>
                    <p class="text-surface-500 mt-1 text-sm">전 제약사 공용 약국 마스터 — 전체 {{ pharmacies.total }}건</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('platform.pharmacies.import.form')">
                        <Button label="CSV 일괄 등록" icon="pi pi-upload" severity="secondary" outlined />
                    </Link>
                    <Link :href="route('platform.pharmacies.create')">
                        <Button label="약국 등록" icon="pi pi-plus" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
                <InputText v-model="search" placeholder="약국명·코드·사업자번호 검색" class="w-full sm:w-80" />
                <Select v-model="region" :options="regionSelect" option-label="label" option-value="value"
                        placeholder="지역(시도)" show-clear class="w-full sm:w-48" />
            </div>

            <DataTable :value="pharmacies.data" striped-rows class="text-sm">
                <template #empty>
                    <div class="text-center py-10 text-surface-500">등록된 약국이 없습니다.</div>
                </template>
                <Column header="지역" style="width: 120px">
                    <template #body="{ data }">{{ data.region ?? '-' }}</template>
                </Column>
                <Column header="약국명" style="min-width: 160px">
                    <template #body="{ data }">
                        <Link :href="route('platform.pharmacies.show', data.id)" class="font-medium hover:text-primary">
                            {{ data.pharmacy_name }}
                        </Link>
                        <div v-if="data.pharmacy_code" class="text-xs text-surface-400 mt-1">{{ data.pharmacy_code }}</div>
                        <div v-if="data.matched_old_numbers?.length" class="mt-1">
                            <Tag v-for="n in data.matched_old_numbers" :key="n" severity="warn" class="mr-1"
                                 :value="`과거 번호 ${n}`" v-tooltip.top="'옛 사업자번호로 검색되어 매칭됨'" />
                        </div>
                    </template>
                </Column>
                <Column header="대표" style="width: 110px">
                    <template #body="{ data }">{{ data.representative_name ?? '-' }}</template>
                </Column>
                <Column header="전화번호" style="width: 130px">
                    <template #body="{ data }">{{ data.phone ?? '-' }}</template>
                </Column>
                <Column header="사업자번호" style="width: 120px">
                    <template #body="{ data }">{{ data.business_registration_number ?? '-' }}</template>
                </Column>
                <Column header="주소" style="min-width: 200px">
                    <template #body="{ data }">{{ data.address ?? '-' }}</template>
                </Column>
                <Column header="상태" style="width: 80px">
                    <template #body="{ data }">
                        <Tag :value="data.status === 'active' ? '활성' : '비활성'" :severity="data.status === 'active' ? 'success' : 'secondary'" />
                    </template>
                </Column>
            </DataTable>

            <Paginator :rows="pharmacies.per_page" :total-records="pharmacies.total"
                       :first="(pharmacies.current_page - 1) * pharmacies.per_page" @page="onPage" />
        </div>
    </AdminLayout>
</template>
