<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';

interface CodeDefinition {
    id: number;
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
}

interface CodeGroup {
    id: number;
    group_code: string;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    definitions: CodeDefinition[];
}

const props = defineProps<{
    codeGroup: CodeGroup;
    can: { update: boolean; delete: boolean; manageDefinitions: boolean };
}>();

const confirm = useConfirm();

const activeOptions = [
    { label: '활성', value: true },
    { label: '비활성', value: false },
];

const confirmDeleteGroup = () => {
    confirm.require({
        message: `코드 그룹 ${props.codeGroup.name} 을(를) 삭제하시겠습니까?`,
        header: '코드 그룹 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('platform.code-groups.destroy', props.codeGroup.id)),
    });
};

// 코드 정의 추가
const showAddDialog = ref(false);
const addForm = useForm({
    code: '',
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const openAddDialog = () => {
    addForm.reset();
    addForm.clearErrors();
    showAddDialog.value = true;
};

const submitAdd = () =>
    addForm.post(route('platform.code-groups.definitions.store', props.codeGroup.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddDialog.value = false;
            addForm.reset();
        },
    });

// 코드 정의 수정
const showEditDialog = ref(false);
const editing = ref<CodeDefinition | null>(null);
const editForm = useForm({
    code: '',
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const openEditDialog = (def: CodeDefinition) => {
    editing.value = def;
    editForm.clearErrors();
    editForm.code = def.code;
    editForm.name = def.name;
    editForm.description = def.description ?? '';
    editForm.sort_order = def.sort_order;
    editForm.is_active = def.is_active;
    showEditDialog.value = true;
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.put(route('platform.code-groups.definitions.update', [props.codeGroup.id, editing.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            editForm.reset();
        },
    });
};

const confirmDeleteDefinition = (def: CodeDefinition) => {
    confirm.require({
        message: `코드 ${def.name} (${def.code}) 을(를) 삭제하시겠습니까?`,
        header: '코드 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(route('platform.code-groups.definitions.destroy', [props.codeGroup.id, def.id]), {
                preserveScroll: true,
            }),
    });
};
</script>

<template>
    <Head :title="codeGroup.name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="max-w-4xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ codeGroup.name }}</h1>
                    <p class="text-surface-500 text-sm mt-1 font-mono">{{ codeGroup.group_code }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('platform.code-groups.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.update" :href="route('platform.code-groups.edit', codeGroup.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                    <Button v-if="can.delete" label="삭제" icon="pi pi-trash" severity="danger" outlined @click="confirmDeleteGroup" />
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                        <div>
                            <dt class="text-surface-500 mb-1">코드 그룹 값</dt>
                            <dd class="font-mono">{{ codeGroup.group_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">상태</dt>
                            <dd>
                                <Tag :value="codeGroup.is_active ? '활성' : '비활성'"
                                     :severity="codeGroup.is_active ? 'success' : 'secondary'" />
                            </dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">설명</dt>
                            <dd>{{ codeGroup.description ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">정렬 순서</dt>
                            <dd>{{ codeGroup.sort_order }}</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>코드 정의 <span class="text-surface-400 text-sm font-normal">({{ codeGroup.definitions.length }}개)</span></span>
                        <Button v-if="can.manageDefinitions" label="코드 추가" icon="pi pi-plus" size="small" @click="openAddDialog" />
                    </div>
                </template>
                <template #content>
                    <DataTable :value="codeGroup.definitions" striped-rows>
                        <template #empty>
                            <div class="text-center py-8 text-surface-500">코드가 없습니다. "코드 추가"로 등록하세요.</div>
                        </template>
                        <Column header="코드" style="width: 140px">
                            <template #body="{ data }"><span class="font-mono">{{ data.code }}</span></template>
                        </Column>
                        <Column header="라벨" field="name" />
                        <Column header="설명">
                            <template #body="{ data }">
                                <span class="text-sm text-surface-500">{{ data.description ?? '-' }}</span>
                            </template>
                        </Column>
                        <Column header="정렬" style="width: 70px">
                            <template #body="{ data }">{{ data.sort_order }}</template>
                        </Column>
                        <Column header="활성" style="width: 80px">
                            <template #body="{ data }">
                                <Tag :value="data.is_active ? '활성' : '비활성'" :severity="data.is_active ? 'success' : 'secondary'" />
                            </template>
                        </Column>
                        <Column v-if="can.manageDefinitions" header="작업" style="width: 110px">
                            <template #body="{ data }">
                                <div class="flex gap-1">
                                    <Button icon="pi pi-pencil" size="small" severity="secondary" outlined
                                            v-tooltip.top="'수정'" @click="openEditDialog(data)" />
                                    <Button icon="pi pi-trash" size="small" severity="danger" outlined
                                            v-tooltip.top="'삭제'" @click="confirmDeleteDefinition(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="showAddDialog" modal header="코드 추가" :style="{ width: '30rem' }">
            <form class="flex flex-col gap-3" @submit.prevent="submitAdd">
                <div>
                    <label class="block text-sm mb-1">코드 값 <span class="text-red-500">*</span></label>
                    <InputText v-model="addForm.code" class="w-full font-mono" placeholder="예: paid" />
                    <Message v-if="addForm.errors.code" severity="error" size="small" variant="simple">{{ addForm.errors.code }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">라벨 <span class="text-red-500">*</span></label>
                    <InputText v-model="addForm.name" class="w-full" placeholder="예: 지급완료" />
                    <Message v-if="addForm.errors.name" severity="error" size="small" variant="simple">{{ addForm.errors.name }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">설명</label>
                    <Textarea v-model="addForm.description" class="w-full" rows="2" />
                </div>
                <div class="flex gap-3">
                    <div class="w-32">
                        <label class="block text-sm mb-1">정렬 순서</label>
                        <InputNumber v-model="addForm.sort_order" class="w-full" :min="0" show-buttons />
                    </div>
                    <div class="w-32">
                        <label class="block text-sm mb-1">상태</label>
                        <Select v-model="addForm.is_active" :options="activeOptions" option-label="label" option-value="value" class="w-full" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="showAddDialog = false" />
                    <Button label="추가" icon="pi pi-check" type="submit" :loading="addForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="showEditDialog" modal header="코드 수정" :style="{ width: '30rem' }">
            <form class="flex flex-col gap-3" @submit.prevent="submitEdit">
                <div>
                    <label class="block text-sm mb-1">코드 값 <span class="text-red-500">*</span></label>
                    <InputText v-model="editForm.code" class="w-full font-mono" />
                    <Message v-if="editForm.errors.code" severity="error" size="small" variant="simple">{{ editForm.errors.code }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">라벨 <span class="text-red-500">*</span></label>
                    <InputText v-model="editForm.name" class="w-full" />
                    <Message v-if="editForm.errors.name" severity="error" size="small" variant="simple">{{ editForm.errors.name }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">설명</label>
                    <Textarea v-model="editForm.description" class="w-full" rows="2" />
                </div>
                <div class="flex gap-3">
                    <div class="w-32">
                        <label class="block text-sm mb-1">정렬 순서</label>
                        <InputNumber v-model="editForm.sort_order" class="w-full" :min="0" show-buttons />
                    </div>
                    <div class="w-32">
                        <label class="block text-sm mb-1">상태</label>
                        <Select v-model="editForm.is_active" :options="activeOptions" option-label="label" option-value="value" class="w-full" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="showEditDialog = false" />
                    <Button label="저장" icon="pi pi-check" type="submit" :loading="editForm.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
