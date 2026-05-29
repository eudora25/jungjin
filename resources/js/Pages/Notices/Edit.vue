<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import NoticeForm from './Partials/NoticeForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface NoticeFile {
    id: number;
    original_name: string;
    size: number;
}

interface Notice {
    id: number;
    title: string;
    content: string | null;
    is_pinned: boolean;
    files: NoticeFile[];
}

const props = defineProps<{ notice: Notice }>();

const form = useForm({
    _method: 'put',
    title: props.notice.title,
    content: props.notice.content ?? '',
    is_pinned: props.notice.is_pinned,
    attachments: [] as File[],
    removed_file_ids: [] as number[],
});

const submit = () => {
    form.post(route('notices.update', props.notice.id), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="공지 수정" />

    <AdminLayout>
        <div class="flex flex-col gap-4 w-full max-w-none">
            <Link :href="route('notices.show', notice.id)" class="text-primary-600 text-sm">
                <i class="pi pi-chevron-left" /> 공지 상세
            </Link>

            <div class="card w-full max-w-none">
                <h1 class="text-2xl font-bold mb-4">공지 수정</h1>
                <NoticeForm
                    :form="form"
                    :existing-files="notice.files"
                    :cancel-href="route('notices.show', notice.id)"
                    cancel-label="취소"
                    submit-label="수정"
                    @submit="submit"
                />
            </div>
        </div>
    </AdminLayout>
</template>
