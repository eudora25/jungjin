<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBusinessNumber } from '@/utils/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';

interface Pharmacy {
    id: number;
    pharmacy_code: string | null;
    pharmacy_name: string;
    business_registration_number: string | null;
    representative_name: string | null;
    postcode: string | null;
    address: string | null;
    landline_phone: string | null;
    mobile_phone: string | null;
    contact_person_name: string | null;
    contact_phone: string | null;
    email: string | null;
    remarks: string | null;
    status: 'active' | 'inactive';
}

interface NumberHistory {
    id: number;
    business_registration_number: string;
    is_current: boolean;
    valid_from: string | null;
    valid_to: string | null;
    reason: string | null;
    note: string | null;
}

const props = defineProps<{ pharmacy: Pharmacy; numberHistories: NumberHistory[] }>();

const confirm = useConfirm();
const confirmDelete = () => {
    confirm.require({
        message: `${props.pharmacy.pharmacy_name} 을(를) 삭제하시겠습니까?`,
        header: '약국 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('platform.pharmacies.destroy', props.pharmacy.id)),
    });
};

// 사업자번호 변경 모달
const showChangeDialog = ref(false);
const changeForm = useForm({
    new_business_registration_number: '',
    valid_from: '',
    previous_valid_to: '',
    reason: '',
    note: '',
});

const openChangeDialog = () => {
    changeForm.reset();
    changeForm.clearErrors();
    showChangeDialog.value = true;
};

const submitChange = () =>
    changeForm.post(route('platform.pharmacies.business-number.change', props.pharmacy.id), {
        preserveScroll: true,
        onSuccess: () => {
            showChangeDialog.value = false;
            changeForm.reset();
        },
    });
</script>

<template>
    <Head :title="pharmacy.pharmacy_name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ pharmacy.pharmacy_name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">약국 상세 (공유 마스터)</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('platform.pharmacies.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link :href="route('platform.pharmacies.edit', pharmacy.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                    <Button label="삭제" icon="pi pi-trash" severity="danger" outlined @click="confirmDelete" />
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="detail-grid">
                        <div>
                            <dt class="field-label">약국 코드</dt>
                            <dd>{{ pharmacy.pharmacy_code ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">상태</dt>
                            <dd>
                                <Tag :value="pharmacy.status === 'active' ? '활성' : '비활성'"
                                     :severity="pharmacy.status === 'active' ? 'success' : 'secondary'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">사업자등록번호</dt>
                            <dd>{{ formatBusinessNumber(pharmacy.business_registration_number) }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">대표자명</dt>
                            <dd>{{ pharmacy.representative_name ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="field-label">주소</dt>
                            <dd>
                                <span v-if="pharmacy.postcode" class="text-xs text-surface-400 mr-2">[{{ pharmacy.postcode }}]</span>
                                {{ pharmacy.address ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="field-label">대표 전화</dt>
                            <dd>{{ pharmacy.landline_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">대표자 휴대폰</dt>
                            <dd>{{ pharmacy.mobile_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">담당자</dt>
                            <dd>{{ pharmacy.contact_person_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="field-label">담당자 연락처</dt>
                            <dd>{{ pharmacy.contact_phone ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="field-label">이메일</dt>
                            <dd>{{ pharmacy.email ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="field-label">비고</dt>
                            <dd class="whitespace-pre-line">{{ pharmacy.remarks ?? '-' }}</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <!-- 사업자등록번호 이력 -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>사업자등록번호 이력
                            <span class="text-surface-400 text-sm font-normal">({{ numberHistories.length }})</span>
                        </span>
                        <Button label="사업자번호 변경" icon="pi pi-sync" size="small" @click="openChangeDialog" />
                    </div>
                </template>
                <template #content>
                    <p class="text-surface-500 text-sm mb-3">
                        폐업·재등록 등으로 번호가 바뀌면 변경 이력이 쌓입니다. 옛 번호로 검색해도 이 약국이 조회됩니다.
                    </p>
                    <DataTable :value="numberHistories" striped-rows size="small">
                        <template #empty>
                            <div class="text-center py-6 text-surface-500">등록된 사업자번호 이력이 없습니다.</div>
                        </template>
                        <Column header="사업자등록번호" body-class="text-center" style="min-width: 150px">
                            <template #body="{ data }">
                                <span class="font-medium">{{ formatBusinessNumber(data.business_registration_number) }}</span>
                                <Tag v-if="data.is_current" value="현재" severity="success" class="ml-2" />
                            </template>
                        </Column>
                        <Column header="적용기간" style="min-width: 180px">
                            <template #body="{ data }">
                                {{ data.valid_from ?? '?' }} ~ {{ data.is_current ? '현재' : (data.valid_to ?? '?') }}
                            </template>
                        </Column>
                        <Column header="사유" style="min-width: 140px">
                            <template #body="{ data }">{{ data.reason ?? '-' }}</template>
                        </Column>
                        <Column header="비고" style="min-width: 120px">
                            <template #body="{ data }">{{ data.note ?? '-' }}</template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="showChangeDialog" modal header="사업자등록번호 변경" :style="{ width: '32rem' }">
            <form class="flex flex-col gap-3" @submit.prevent="submitChange">
                <p class="text-sm text-surface-500">
                    현재 번호: <span class="font-medium">{{ pharmacy.business_registration_number ? formatBusinessNumber(pharmacy.business_registration_number) : '미등록' }}</span>
                </p>
                <div>
                    <label class="block text-sm mb-1">새 사업자등록번호 <span class="text-red-500">*</span></label>
                    <InputText v-model="changeForm.new_business_registration_number" class="w-full" placeholder="예: 234-56-78901" />
                    <Message v-if="changeForm.errors.new_business_registration_number" severity="error" size="small" variant="simple">{{ changeForm.errors.new_business_registration_number }}</Message>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">새 번호 적용 시작일</label>
                        <InputText v-model="changeForm.valid_from" type="date" class="w-full" />
                        <Message v-if="changeForm.errors.valid_from" severity="error" size="small" variant="simple">{{ changeForm.errors.valid_from }}</Message>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">이전 번호 종료일(폐업일)</label>
                        <InputText v-model="changeForm.previous_valid_to" type="date" class="w-full" />
                        <Message v-if="changeForm.errors.previous_valid_to" severity="error" size="small" variant="simple">{{ changeForm.errors.previous_valid_to }}</Message>
                    </div>
                </div>
                <div>
                    <label class="block text-sm mb-1">변경 사유</label>
                    <InputText v-model="changeForm.reason" class="w-full" placeholder="예: 폐업 후 재등록" />
                    <Message v-if="changeForm.errors.reason" severity="error" size="small" variant="simple">{{ changeForm.errors.reason }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">비고</label>
                    <Textarea v-model="changeForm.note" class="w-full" rows="2" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="showChangeDialog = false" />
                    <Button label="변경 기록" icon="pi pi-check" type="submit" :loading="changeForm.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
