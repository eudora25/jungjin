<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';

interface CodeGroup {
    id: number;
    group_code: string;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
}

const props = defineProps<{
    codeGroup: CodeGroup;
}>();

const form = useForm({
    group_code: props.codeGroup.group_code,
    name: props.codeGroup.name,
    description: props.codeGroup.description ?? '',
    sort_order: props.codeGroup.sort_order,
    is_active: props.codeGroup.is_active,
});

const activeOptions = [
    { label: '활성', value: true },
    { label: '비활성', value: false },
];

const submit = () => form.put(route('platform.code-groups.update', props.codeGroup.id));
</script>

<template>
    <Head :title="`${codeGroup.name} 수정`" />
    <AdminLayout>
        <div class="max-w-2xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">코드 그룹 수정</h1>
                <Link :href="route('platform.code-groups.show', codeGroup.id)">
                    <Button label="상세로" icon="pi pi-arrow-left" severity="secondary" outlined />
                </Link>
            </div>

            <Card>
                <template #content>
                    <form class="flex flex-col gap-4" @submit.prevent="submit">
                        <div>
                            <label class="block text-sm mb-1">코드 그룹 값 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.group_code" class="w-full font-mono" />
                            <small class="text-surface-400">변경 시 하위 코드의 group_code 도 함께 갱신됩니다.</small>
                            <Message v-if="form.errors.group_code" severity="error" size="small" variant="simple">{{ form.errors.group_code }}</Message>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">그룹 라벨 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.name" class="w-full" />
                            <Message v-if="form.errors.name" severity="error" size="small" variant="simple">{{ form.errors.name }}</Message>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">설명</label>
                            <Textarea v-model="form.description" class="w-full" rows="3" />
                            <Message v-if="form.errors.description" severity="error" size="small" variant="simple">{{ form.errors.description }}</Message>
                        </div>
                        <div class="flex gap-3">
                            <div class="w-40">
                                <label class="block text-sm mb-1">정렬 순서</label>
                                <InputNumber v-model="form.sort_order" class="w-full" :min="0" show-buttons />
                                <Message v-if="form.errors.sort_order" severity="error" size="small" variant="simple">{{ form.errors.sort_order }}</Message>
                            </div>
                            <div class="w-40">
                                <label class="block text-sm mb-1">상태</label>
                                <Select v-model="form.is_active" :options="activeOptions" option-label="label" option-value="value" class="w-full" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Link :href="route('platform.code-groups.show', codeGroup.id)">
                                <Button label="취소" severity="secondary" outlined type="button" />
                            </Link>
                            <Button label="저장" icon="pi pi-check" type="submit" :loading="form.processing" />
                        </div>
                    </form>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
