<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';

interface Activity {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string;
    subject_id: number | null;
    causer: { id: number; name: string } | null;
    properties: Record<string, unknown>;
    created_at: string;
}

const props = defineProps<{
    activities: Activity[];
}>();

const activeTab = ref<'all' | 'product' | 'price' | 'file' | 'override' | 'nims'>('all');

const isNims = (a: Activity) => (a.log_name ?? '').startsWith('nims.');

const filtered = computed(() => {
    if (activeTab.value === 'all') return props.activities;
    if (activeTab.value === 'nims') return props.activities.filter(isNims);
    if (activeTab.value === 'product')
        return props.activities.filter((a) => a.log_name === 'product');
    if (activeTab.value === 'price')
        return props.activities.filter((a) => a.log_name === 'product.price');
    if (activeTab.value === 'file')
        return props.activities.filter((a) => a.log_name === 'product.file');
    if (activeTab.value === 'override')
        return props.activities.filter((a) => a.log_name === 'product.override');
    return props.activities;
});

const counts = computed(() => ({
    all: props.activities.length,
    product: props.activities.filter((a) => a.log_name === 'product').length,
    price: props.activities.filter((a) => a.log_name === 'product.price').length,
    file: props.activities.filter((a) => a.log_name === 'product.file').length,
    override: props.activities.filter((a) => a.log_name === 'product.override').length,
    nims: props.activities.filter(isNims).length,
}));

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('ko-KR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

const eventLabel = (a: Activity) => {
    const map: Record<string, string> = {
        created: '생성',
        updated: '수정',
        deleted: '삭제',
        submit: '검수 요청',
        review: '검수 완료',
        approve: '최종 승인',
        reject: '반려',
        discontinue: '단종',
    };
    return map[a.event ?? ''] ?? a.description ?? '-';
};

const eventSeverity = (a: Activity): 'success' | 'info' | 'warn' | 'danger' | 'secondary' => {
    if (a.event === 'approve') return 'success';
    if (a.event === 'reject' || a.event === 'deleted' || a.event === 'discontinue') return 'danger';
    if (a.event === 'submit' || a.event === 'review') return 'info';
    if (a.event === 'created') return 'success';
    if (a.event === 'updated') return 'warn';
    return 'secondary';
};

const subjectLabel = (a: Activity) => {
    const map: Record<string, string> = {
        Product: '제품',
        ProductPrice: '가격',
        ProductFile: '첨부',
        CompanyProductOverride: '거래처 예외',
    };
    return map[a.subject_type] ?? a.subject_type;
};

const propsToString = (p: Record<string, unknown>) => {
    if (!p || Object.keys(p).length === 0) return '-';
    const parts: string[] = [];
    if (p.reason) parts.push(`사유: ${String(p.reason)}`);
    if (p.attributes && typeof p.attributes === 'object') {
        const attr = p.attributes as Record<string, unknown>;
        const old = (p.old as Record<string, unknown>) ?? {};
        for (const k of Object.keys(attr)) {
            const before = old[k];
            const after = attr[k];
            if (before !== undefined) {
                parts.push(`${k}: ${JSON.stringify(before)} → ${JSON.stringify(after)}`);
            } else {
                parts.push(`${k}: ${JSON.stringify(after)}`);
            }
        }
    }
    return parts.join(' · ') || '-';
};

const expanded = ref<Set<number>>(new Set());
const toggle = (id: number) => {
    if (expanded.value.has(id)) expanded.value.delete(id);
    else expanded.value.add(id);
};
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">변경 이력</h2>
                <p class="text-xs text-surface-500 mt-0.5">
                    제품 마스터/가격/첨부/거래처 예외의 모든 변경이 기록됩니다.
                    NIMS 관련 변경은 별도 채널(`nims.product`)로 분리됩니다.
                </p>
            </div>
        </div>

        <Tabs v-model:value="activeTab">
            <TabList>
                <Tab value="all">전체 <span class="ml-1 text-xs text-surface-500">({{ counts.all }})</span></Tab>
                <Tab value="product">제품 <span class="ml-1 text-xs text-surface-500">({{ counts.product }})</span></Tab>
                <Tab value="price">가격 <span class="ml-1 text-xs text-surface-500">({{ counts.price }})</span></Tab>
                <Tab value="override">거래처 예외 <span class="ml-1 text-xs text-surface-500">({{ counts.override }})</span></Tab>
                <Tab value="file">첨부 <span class="ml-1 text-xs text-surface-500">({{ counts.file }})</span></Tab>
                <Tab value="nims">NIMS <span class="ml-1 text-xs text-red-500">({{ counts.nims }})</span></Tab>
            </TabList>
            <TabPanels>
                <TabPanel value="all" />
                <TabPanel value="product" />
                <TabPanel value="price" />
                <TabPanel value="override" />
                <TabPanel value="file" />
                <TabPanel value="nims" />
            </TabPanels>
        </Tabs>

        <DataTable :value="filtered" data-key="id" :rows="20" paginator responsive-layout="scroll">
            <template #empty>
                <div class="text-center text-surface-500 py-6">표시할 변경 이력이 없습니다.</div>
            </template>

            <Column header="시각" style="width: 11rem">
                <template #body="{ data }">
                    <span class="text-sm">{{ formatDate(data.created_at) }}</span>
                </template>
            </Column>
            <Column header="대상" style="width: 8rem">
                <template #body="{ data }">
                    <Tag :value="subjectLabel(data)" severity="secondary" />
                    <Tag v-if="isNims(data)" value="NIMS" severity="danger" class="ml-1" />
                </template>
            </Column>
            <Column header="이벤트" style="width: 9rem">
                <template #body="{ data }">
                    <Tag :value="eventLabel(data)" :severity="eventSeverity(data)" />
                </template>
            </Column>
            <Column header="작업자" style="width: 9rem">
                <template #body="{ data }">
                    <span class="text-sm">{{ data.causer?.name ?? '-' }}</span>
                </template>
            </Column>
            <Column header="변경 내용">
                <template #body="{ data }">
                    <div class="text-sm">
                        <div v-if="!expanded.has(data.id)" class="truncate max-w-xl">{{ propsToString(data.properties) }}</div>
                        <pre v-else class="text-xs bg-surface-50 dark:bg-surface-800 p-2 rounded overflow-x-auto">{{ JSON.stringify(data.properties, null, 2) }}</pre>
                    </div>
                </template>
            </Column>
            <Column header="" style="width: 4rem">
                <template #body="{ data }">
                    <Button
                        :icon="expanded.has(data.id) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                        severity="secondary"
                        text
                        rounded
                        @click="toggle(data.id)"
                    />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
