<script setup lang="ts">
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';

interface HospitalForm {
    hospital_code: string | null;
    hospital_name: string;
    business_registration_number: string | null;
    hospital_type: string | null;
    specialty: string | null;
    representative_name: string | null;
    postcode: string | null;
    address: string | null;
    phone: string | null;
    contact_person_name: string | null;
    contact_phone: string | null;
    email: string | null;
    remarks: string | null;
    status: 'active' | 'inactive';
}

const props = defineProps<{
    form: HospitalForm & { errors: Partial<Record<keyof HospitalForm, string>> };
    types: string[];
}>();

const typeLabels: Record<string, string> = {
    general_hospital: '종합병원',
    hospital: '병원',
    clinic: '의원',
    dental: '치과',
    oriental: '한의원',
    other: '기타',
};

const typeOptions = [
    { label: '선택 안 함', value: null },
    ...props.types.map((t) => ({ label: typeLabels[t] ?? t, value: t })),
];

const statusOptions = [
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">병의원명 <span class="text-red-500">*</span></label>
            <InputText v-model="form.hospital_name" class="w-full"
                       :invalid="!!form.errors.hospital_name" />
            <small v-if="form.errors.hospital_name" class="text-red-500">{{ form.errors.hospital_name }}</small>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">병의원 코드</label>
            <InputText v-model="form.hospital_code" class="w-full"
                       :invalid="!!form.errors.hospital_code" />
            <small v-if="form.errors.hospital_code" class="text-red-500">{{ form.errors.hospital_code }}</small>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">유형</label>
            <Select v-model="form.hospital_type" :options="typeOptions" option-label="label" option-value="value"
                    class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">전문 분야</label>
            <InputText v-model="form.specialty" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">사업자등록번호</label>
            <InputText v-model="form.business_registration_number" class="w-full" placeholder="123-45-67890"
                       :invalid="!!form.errors.business_registration_number" />
            <small v-if="form.errors.business_registration_number" class="text-red-500">{{ form.errors.business_registration_number }}</small>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">원장/대표자명</label>
            <InputText v-model="form.representative_name" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">우편번호</label>
            <InputText v-model="form.postcode" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">주소</label>
            <InputText v-model="form.address" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">대표 전화</label>
            <InputText v-model="form.phone" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">담당자명</label>
            <InputText v-model="form.contact_person_name" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">담당자 연락처</label>
            <InputText v-model="form.contact_phone" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">이메일</label>
            <InputText v-model="form.email" class="w-full" type="email"
                       :invalid="!!form.errors.email" />
            <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">상태</label>
            <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value"
                    class="w-full" />
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">비고</label>
            <Textarea v-model="form.remarks" rows="3" class="w-full" />
        </div>
    </div>
</template>
