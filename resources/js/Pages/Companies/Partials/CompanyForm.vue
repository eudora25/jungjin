<script setup lang="ts">
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps<{
    form: any;
    submitLabel: string;
}>();

const emit = defineEmits<{ (e: 'submit'): void }>();

const gradeOptions = [
    { label: '선택 안 함', value: null },
    { label: 'A (최우선)', value: 'A' },
    { label: 'B', value: 'B' },
    { label: 'C', value: 'C' },
    { label: 'D', value: 'D' },
    { label: 'E', value: 'E' },
];

const statusOptions = [
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];

const approvalOptions = [
    { label: '대기', value: 'pending' },
    { label: '승인', value: 'approved' },
    { label: '반려', value: 'rejected' },
];
</script>

<template>
    <Card>
        <template #content>
            <form @submit.prevent="emit('submit')" class="flex flex-col gap-6">
                <section class="flex flex-col gap-4">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">기본 정보</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-sm">업체명 *</label>
                            <InputText v-model="form.company_name" :invalid="!!form.errors.company_name" required autofocus />
                            <small v-if="form.errors.company_name" class="text-red-500">{{ form.errors.company_name }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">사업자등록번호</label>
                            <InputText v-model="form.business_registration_number" :invalid="!!form.errors.business_registration_number" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">대표자명</label>
                            <InputText v-model="form.representative_name" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">업체 구분</label>
                            <InputText v-model="form.company_group" placeholder="예: 제약사, 의료기기, 도매상" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">기본 수수료 등급</label>
                            <Select
                                v-model="form.default_commission_grade"
                                :options="gradeOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="등급 선택"
                            />
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">주소</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">우편번호</label>
                            <InputText v-model="form.postcode" />
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-3">
                            <label class="text-sm">사업장 주소</label>
                            <InputText v-model="form.business_address" />
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">담당자/연락처</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">담당자명</label>
                            <InputText v-model="form.contact_person_name" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">대표 전화</label>
                            <InputText v-model="form.landline_phone" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">담당자 휴대폰</label>
                            <InputText v-model="form.mobile_phone" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">보조 휴대폰</label>
                            <InputText v-model="form.mobile_phone_2" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">이메일</label>
                            <InputText v-model="form.email" type="email" :invalid="!!form.errors.email" />
                            <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">수신 이메일 (공지 등)</label>
                            <InputText v-model="form.receive_email" type="email" />
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-sm">담당 제약사 관리자</label>
                            <InputText v-model="form.assigned_pharmacist_contact" />
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">상태</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">활성 상태</label>
                            <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">승인 상태</label>
                            <Select v-model="form.approval_status" :options="approvalOptions" option-label="label" option-value="value" />
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-surface-700 dark:text-surface-200">비고</label>
                    <Textarea v-model="form.remarks" rows="4" autoResize />
                </section>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="submit" :label="submitLabel" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </template>
    </Card>
</template>
