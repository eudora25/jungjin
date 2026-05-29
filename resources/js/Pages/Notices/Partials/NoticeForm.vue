<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { Link } from '@inertiajs/vue3';

interface ExistingFile {
    id: number;
    original_name: string;
    size: number;
}

const props = defineProps<{
    form: any;
    existingFiles?: ExistingFile[];
    cancelHref: string;
    cancelLabel: string;
    submitLabel: string;
}>();

const emit = defineEmits<{ (e: 'submit'): void }>();

const fileUploadRef = ref();

const onFileSelect = (event: { files: File[] }) => {
    props.form.attachments = event.files;
};

const removeExisting = (id: number) => {
    if (! props.form.removed_file_ids.includes(id)) {
        props.form.removed_file_ids.push(id);
    }
};

const restoreExisting = (id: number) => {
    props.form.removed_file_ids = props.form.removed_file_ids.filter((x: number) => x !== id);
};

const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};
</script>

<template>
    <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="emit('submit')">
        <div class="md:col-span-2">
            <label class="block text-sm mb-1">제목 *</label>
            <InputText
                v-model="form.title"
                placeholder="공지 제목"
                :invalid="!!form.errors.title"
                required
                autofocus
                class="w-full"
            />
            <small v-if="form.errors.title" class="text-red-500">{{ form.errors.title }}</small>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm mb-1">본문</label>
            <Textarea
                v-model="form.content"
                rows="10"
                placeholder="공지 내용"
                :invalid="!!form.errors.content"
                autoResize
                class="w-full"
            />
            <small v-if="form.errors.content" class="text-red-500">{{ form.errors.content }}</small>
        </div>

        <div class="md:col-span-2 flex items-center gap-2">
            <Checkbox v-model="form.is_pinned" :binary="true" inputId="is_pinned" />
            <label for="is_pinned" class="text-sm text-surface-700 dark:text-surface-200">상단 고정</label>
        </div>

        <div v-if="existingFiles && existingFiles.length" class="md:col-span-2 flex flex-col gap-2">
            <label class="block text-sm mb-1">기존 첨부파일</label>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="file in existingFiles"
                    :key="file.id"
                    class="flex items-center justify-between border border-surface rounded-lg px-3 py-2"
                    :class="form.removed_file_ids.includes(file.id) ? 'opacity-50 line-through' : ''"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="pi pi-file text-surface-400" />
                        <span class="truncate">{{ file.original_name }}</span>
                        <span class="text-xs text-surface-400 shrink-0">{{ formatSize(file.size) }}</span>
                    </div>
                    <Button
                        v-if="!form.removed_file_ids.includes(file.id)"
                        label="제거"
                        icon="pi pi-times"
                        severity="danger"
                        size="small"
                        text
                        @click="removeExisting(file.id)"
                    />
                    <Button
                        v-else
                        label="복원"
                        icon="pi pi-undo"
                        severity="secondary"
                        size="small"
                        text
                        @click="restoreExisting(file.id)"
                    />
                </li>
            </ul>
        </div>

        <div class="md:col-span-2 flex flex-col gap-2">
            <label class="block text-sm mb-1">첨부파일 (최대 10개, 각 20MB)</label>
            <FileUpload
                ref="fileUploadRef"
                :multiple="true"
                :custom-upload="true"
                :auto="false"
                :max-file-size="20971520"
                choose-label="파일 선택"
                upload-label="업로드"
                cancel-label="취소"
                :show-upload-button="false"
                :show-cancel-button="false"
                @select="onFileSelect"
                @clear="form.attachments = []"
            >
                <template #empty>
                    <p class="text-sm text-surface-500">여기에 파일을 끌어놓거나 \"파일 선택\"을 누르세요.</p>
                </template>
            </FileUpload>
            <small v-if="form.errors.attachments" class="text-red-500">{{ form.errors.attachments }}</small>
        </div>

        <div class="md:col-span-2 flex justify-end gap-2 mt-2">
            <Link :href="cancelHref">
                <Button :label="cancelLabel" severity="secondary" outlined />
            </Link>
            <Button type="submit" :label="submitLabel" icon="pi pi-check" :loading="form.processing" />
        </div>
    </form>
</template>
