<script setup lang="ts">
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';

interface PharmacyForm {
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

defineProps<{
    form: PharmacyForm & { errors: Partial<Record<keyof PharmacyForm, string>> };
}>();

const statusOptions = [
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
];
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">약국명 <span class="text-red-500">*</span></label>
            <InputText v-model="form.pharmacy_name" class="w-full"
                       :invalid="!!form.errors.pharmacy_name" />
            <small v-if="form.errors.pharmacy_name" class="text-red-500">{{ form.errors.pharmacy_name }}</small>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">약국 코드</label>
            <InputText v-model="form.pharmacy_code" class="w-full"
                       :invalid="!!form.errors.pharmacy_code" />
            <small v-if="form.errors.pharmacy_code" class="text-red-500">{{ form.errors.pharmacy_code }}</small>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">사업자등록번호</label>
            <InputText v-model="form.business_registration_number" class="w-full" placeholder="123-45-67890"
                       :invalid="!!form.errors.business_registration_number" />
            <small v-if="form.errors.business_registration_number" class="text-red-500">{{ form.errors.business_registration_number }}</small>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">대표자명</label>
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
            <InputText v-model="form.landline_phone" class="w-full" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">대표자 휴대폰</label>
            <InputText v-model="form.mobile_phone" class="w-full" />
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
