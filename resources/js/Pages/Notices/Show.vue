<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

interface NoticeFile {
    id: number;
    original_name: string;
    path: string;
    size: number;
    mime_type: string;
    extension: string;
}

interface Notice {
    id: number;
    title: string;
    content: string | null;
    is_pinned: boolean;
    view_count: number;
    created_at: string;
    updated_at: string;
    author: { id: number; name: string; email: string } | null;
    files: NoticeFile[];
}

interface ReadStats {
    total: number;
    read_count: number;
    readers: Array<{ id: number; name: string; read_at: string }>;
}

const props = defineProps<{
    notice: Notice;
    readStats: ReadStats | null;
    can: { update: boolean; delete: boolean };
}>();

const confirm = useConfirm();

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('ko-KR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));

const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const attachmentUrl = (file: NoticeFile) => route('notices.files.download', [props.notice.id, file.id]);

const confirmDelete = () => {
    confirm.require({
        message: '이 공지를 삭제하시겠습니까?',
        header: '공지 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('notices.destroy', props.notice.id)),
    });
};
</script>

<template>
    <Head :title="notice.title" />

    <ConfirmDialog />

    <AdminLayout>
        <div class="flex flex-col gap-4 w-full max-w-none">
            <Link :href="route('notices.index')" class="text-primary-600 text-sm">
                <i class="pi pi-chevron-left" /> 공지 목록
            </Link>

            <div class="card w-full max-w-none">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <Tag v-if="notice.is_pinned" value="필수" severity="warn" />
                        </div>
                        <h1 class="text-2xl font-bold mb-2">{{ notice.title }}</h1>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-surface-500">
                            <span><i class="pi pi-user mr-1" />{{ notice.author?.name ?? '-' }}</span>
                            <span><i class="pi pi-calendar mr-1" />{{ formatDate(notice.created_at) }}</span>
                            <span><i class="pi pi-eye mr-1" />{{ notice.view_count }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="can.update" :href="route('notices.edit', notice.id)">
                            <Button label="수정" icon="pi pi-pencil" severity="secondary" outlined />
                        </Link>
                        <Button
                            v-if="can.delete"
                            label="삭제"
                            icon="pi pi-trash"
                            severity="danger"
                            outlined
                            @click="confirmDelete"
                        />
                    </div>
                </div>

                <hr class="border-surface-200 dark:border-surface-700 my-4" />

                <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap">
                    {{ notice.content || '(본문 없음)' }}
                </div>

                <div v-if="notice.files.length" class="mt-6">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-2">
                        첨부파일 ({{ notice.files.length }})
                    </h3>
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="file in notice.files"
                            :key="file.id"
                            class="flex items-center justify-between border border-surface rounded-lg px-3 py-2 bg-surface-50 dark:bg-surface-900"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="pi pi-file text-surface-400" />
                                <span class="truncate">{{ file.original_name }}</span>
                                <span class="text-xs text-surface-400 shrink-0">{{ formatSize(file.size) }}</span>
                            </div>
                            <a :href="attachmentUrl(file)" class="text-primary text-sm hover:underline">
                                다운로드
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 읽음 현황 (관리자 전용) -->
        <div v-if="readStats" class="card w-full max-w-none">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-semibold">읽음 현황</h3>
                <span class="text-sm text-surface-500">
                    <span class="font-semibold text-primary-600">{{ readStats.read_count }}</span>
                    / {{ readStats.total }}명 읽음
                    ({{ readStats.total > 0 ? Math.round(readStats.read_count / readStats.total * 100) : 0 }}%)
                </span>
            </div>

            <div class="w-full bg-surface-200 dark:bg-surface-700 rounded-full h-2 mb-4">
                <div class="bg-primary-500 h-2 rounded-full transition-all"
                     :style="{ width: readStats.total > 0 ? `${readStats.read_count / readStats.total * 100}%` : '0%' }" />
            </div>

            <DataTable :value="readStats.readers" :striped-rows="true" data-key="id" size="small">
                <Column field="name" header="이름" />
                <Column header="읽은 시각">
                    <template #body="{ data }">
                        {{ new Intl.DateTimeFormat('ko-KR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(data.read_at)) }}
                    </template>
                </Column>
                <template #empty>
                    <div class="py-4 text-center text-surface-400 text-sm">아직 아무도 읽지 않았습니다.</div>
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
