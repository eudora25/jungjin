<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import NoticeForm from './Partials/NoticeForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    title: '',
    content: '',
    is_pinned: false,
    attachments: [] as File[],
});

const submit = () => {
    form.post(route('notices.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="공지 작성" />

    <AdminLayout>
        <div class="flex flex-col gap-4 w-full max-w-none">
            <Link :href="route('notices.index')" class="text-primary-600 text-sm">
                <i class="pi pi-chevron-left" /> 공지 목록
            </Link>

            <div class="card w-full max-w-none">
                <h1 class="text-2xl font-bold mb-4">공지 작성</h1>
                <NoticeForm
                    :form="form"
                    :cancel-href="route('notices.index')"
                    cancel-label="취소"
                    submit-label="등록"
                    @submit="submit"
                />
            </div>
        </div>
    </AdminLayout>
</template>
