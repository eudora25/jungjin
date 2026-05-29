<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';

type FileType = 'license' | 'safety' | 'catalog' | 'etc';

interface ProductFile {
    id: number;
    file_type: FileType;
    original_name: string;
    stored_name: string;
    path: string;
    size: number | null;
    mime_type: string | null;
    extension: string | null;
    uploaded_by: number | null;
    uploader: { id: number; name: string } | null;
    created_at: string;
}

const props = defineProps<{
    productId: number;
    files: ProductFile[];
    canUpload: boolean;
    canDelete: boolean;
}>();

const fileTypeOptions: { label: string; value: FileType }[] = [
    { label: '허가 문서', value: 'license' },
    { label: '안전성 자료', value: 'safety' },
    { label: '카탈로그', value: 'catalog' },
    { label: '기타', value: 'etc' },
];

const fileTypeLabel = (t: FileType) => fileTypeOptions.find((o) => o.value === t)?.label ?? t;
const fileTypeSeverity = (t: FileType) =>
    ({ license: 'info', safety: 'warn', catalog: 'success', etc: 'secondary' }[t] as
        | 'info'
        | 'warn'
        | 'success'
        | 'secondary');

const formatSize = (bytes: number | null) => {
    if (bytes === null || bytes === undefined) return '-';
    if (bytes < 1024) return `${bytes} B`;
    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} KB`;
    const mb = kb / 1024;
    return `${mb.toFixed(2)} MB`;
};

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('ko-KR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

const downloadHref = (file: ProductFile) =>
    route('products.files.download', { product: props.productId, file: file.id });

// --- 탭 (전체 + 4종) ---
const activeTab = ref<'all' | FileType>('all');

const filterFiles = (key: 'all' | FileType) => {
    if (key === 'all') return props.files;
    return props.files.filter((f) => f.file_type === key);
};

const countByType = (type: FileType) => props.files.filter((f) => f.file_type === type).length;

// --- 업로드 모달 ---
const uploadOpen = ref(false);
const uploadForm = useForm<{ file_type: FileType; file: File | null }>({
    file_type: 'license',
    file: null,
});

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    uploadForm.file = target.files && target.files[0] ? target.files[0] : null;
};

const openUpload = () => {
    uploadForm.reset();
    uploadForm.clearErrors();
    if (activeTab.value !== 'all') {
        uploadForm.file_type = activeTab.value;
    }
    uploadOpen.value = true;
};

const submitUpload = () => {
    uploadForm.post(route('products.files.store', props.productId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadOpen.value = false;
        },
    });
};

// --- 삭제 ---
const confirm = useConfirm();
const onDelete = (file: ProductFile) => {
    confirm.require({
        header: '첨부 파일 삭제',
        message: `"${file.original_name}" 파일을 삭제하시겠습니까? 실제 파일도 함께 제거됩니다.`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(route('products.files.destroy', { product: props.productId, file: file.id }), {
                preserveScroll: true,
            }),
    });
};
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">첨부 문서</h2>
                <p class="text-xs text-surface-500 mt-0.5">
                    허가 문서, 안전성 자료, 카탈로그 등 제품 관련 파일을 관리합니다. (최대 10MB · PDF/이미지/오피스/ZIP)
                </p>
            </div>
            <Button
                v-if="canUpload"
                label="파일 업로드"
                icon="pi pi-upload"
                @click="openUpload"
            />
        </div>

        <Tabs v-model:value="activeTab">
            <TabList>
                <Tab value="all">전체 <span class="ml-1 text-xs text-surface-500">({{ files.length }})</span></Tab>
                <Tab v-for="opt in fileTypeOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                    <span class="ml-1 text-xs text-surface-500">({{ countByType(opt.value) }})</span>
                </Tab>
            </TabList>
            <TabPanels>
                <TabPanel v-for="key in (['all', 'license', 'safety', 'catalog', 'etc'] as const)" :key="key" :value="key">
                    <DataTable :value="filterFiles(key)" data-key="id" responsive-layout="scroll" :rows="10" paginator>
                        <template #empty>
                            <div class="text-center text-surface-500 py-6">등록된 첨부 파일이 없습니다.</div>
                        </template>

                        <Column field="file_type" header="종류" style="width: 8rem">
                            <template #body="{ data }">
                                <Tag :value="fileTypeLabel(data.file_type)" :severity="fileTypeSeverity(data.file_type)" />
                            </template>
                        </Column>
                        <Column field="original_name" header="파일명">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-file text-surface-400" />
                                    <span class="truncate">{{ data.original_name }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column field="size" header="크기" style="width: 7rem">
                            <template #body="{ data }">
                                <span class="text-sm text-surface-600">{{ formatSize(data.size) }}</span>
                            </template>
                        </Column>
                        <Column field="uploader" header="업로더" style="width: 10rem">
                            <template #body="{ data }">
                                <span class="text-sm">{{ data.uploader?.name ?? '-' }}</span>
                            </template>
                        </Column>
                        <Column field="created_at" header="업로드 시각" style="width: 12rem">
                            <template #body="{ data }">
                                <span class="text-sm text-surface-600">{{ formatDate(data.created_at) }}</span>
                            </template>
                        </Column>
                        <Column header="" style="width: 9rem">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <a :href="downloadHref(data)" target="_blank">
                                        <Button icon="pi pi-download" severity="secondary" text rounded title="다운로드" />
                                    </a>
                                    <Button
                                        v-if="canDelete"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        rounded
                                        title="삭제"
                                        @click="onDelete(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </TabPanel>
            </TabPanels>
        </Tabs>

        <!-- 업로드 모달 -->
        <Dialog v-model:visible="uploadOpen" modal header="첨부 파일 업로드" :style="{ width: '32rem' }" :draggable="false">
            <div class="flex flex-col gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">문서 종류 <span class="text-red-500">*</span></label>
                    <Select
                        v-model="uploadForm.file_type"
                        :options="fileTypeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                        :class="{ 'p-invalid': uploadForm.errors.file_type }"
                    />
                    <small v-if="uploadForm.errors.file_type" class="text-red-500">{{ uploadForm.errors.file_type }}</small>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">파일 <span class="text-red-500">*</span></label>
                    <input
                        type="file"
                        class="block w-full text-sm text-surface-700 dark:text-surface-200 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:bg-primary file:text-white file:font-medium hover:file:bg-primary-emphasis"
                        accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                        @change="onFileChange"
                    />
                    <small v-if="uploadForm.errors.file" class="text-red-500 block mt-1">{{ uploadForm.errors.file }}</small>
                    <small v-else class="text-surface-500 block mt-1">최대 10MB · PDF, 이미지, MS Office, ZIP</small>
                </div>
            </div>
            <template #footer>
                <Button label="취소" severity="secondary" text @click="uploadOpen = false" />
                <Button
                    label="업로드"
                    icon="pi pi-upload"
                    :loading="uploadForm.processing"
                    :disabled="!uploadForm.file"
                    @click="submitUpload"
                />
            </template>
        </Dialog>
    </div>
</template>
