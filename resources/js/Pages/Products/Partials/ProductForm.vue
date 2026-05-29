<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps<{
    form: any;
    existingImageUrl?: string | null;
    submitLabel: string;
}>();

const emit = defineEmits<{ (e: 'submit'): void }>();

const statusOptions = [
    { label: '활성', value: 'active' },
    { label: '비활성', value: 'inactive' },
    { label: '단종', value: 'discontinued' },
];

const drugTypeOptions = [
    { label: '일반', value: 'general' },
    { label: 'ETC (전문)', value: 'etc' },
    { label: '마약', value: 'narcotic' },
    { label: '향정신성', value: 'psychotropic' },
];

const storageOptions = [
    { label: '실온', value: 'room' },
    { label: '냉장', value: 'cold' },
    { label: '냉동', value: 'frozen' },
];

// --- Edit 모드 감지 (form 객체에 해당 키가 있는지로 판단) ---
const hasField = (key: string) => key in props.form;
const isEdit = computed(() => hasField('change_reason') || hasField('remove_image'));

// --- 마약/향정 선택 시 NIMS 자동 관리 안내 ---
const isNims = computed(() =>
    ['narcotic', 'psychotropic'].includes(props.form.drug_type),
);

// --- NIMS 핵심 컬럼 변경 감지: change_reason 필요 안내 ---
const needsChangeReason = computed(() => isEdit.value && isNims.value);

// --- 이미지 처리 (기존 로직 유지) ---
const removeImage = ref(false);
const imagePreview = ref<string | null>(null);

const showExistingImage = computed(
    () => props.existingImageUrl && !removeImage.value && !imagePreview.value,
);

const onImageSelect = (event: { files: File[] }) => {
    if (event.files.length > 0) {
        props.form.image = event.files[0];
        imagePreview.value = URL.createObjectURL(event.files[0]);
        removeImage.value = false;
        if (hasField('remove_image')) props.form.remove_image = false;
    }
};

const onImageClear = () => {
    props.form.image = null;
    imagePreview.value = null;
};

const toggleRemoveExisting = () => {
    removeImage.value = !removeImage.value;
    if (hasField('remove_image')) {
        props.form.remove_image = removeImage.value;
    }
};
</script>

<template>
    <Card>
        <template #content>
            <form @submit.prevent="emit('submit')" class="flex flex-col gap-6">
                <!-- 1. 식별 정보 -->
                <section class="flex flex-col gap-4">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">식별 정보</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-sm">제품명 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.product_name" :invalid="!!form.errors.product_name" required autofocus />
                            <small v-if="form.errors.product_name" class="text-red-500">{{ form.errors.product_name }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">보험코드 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.insurance_code" :invalid="!!form.errors.insurance_code" required />
                            <small v-if="form.errors.insurance_code" class="text-red-500">{{ form.errors.insurance_code }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">제품코드 <span class="text-red-500">*</span></label>
                            <InputText v-model="form.product_code" :invalid="!!form.errors.product_code" required />
                            <small v-if="form.errors.product_code" class="text-red-500">{{ form.errors.product_code }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">표준코드 (KD)</label>
                            <InputText v-model="form.standard_code" :invalid="!!form.errors.standard_code" placeholder="예: 643000010" />
                            <small v-if="form.errors.standard_code" class="text-red-500">{{ form.errors.standard_code }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">바코드 (GTIN)</label>
                            <InputText v-model="form.barcode_gtin" :invalid="!!form.errors.barcode_gtin" placeholder="예: 8806789012345" />
                            <small v-if="form.errors.barcode_gtin" class="text-red-500">{{ form.errors.barcode_gtin }}</small>
                        </div>
                    </div>
                </section>

                <!-- 2. 약품 상세 -->
                <section class="flex flex-col gap-4">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">약품 상세</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">성분명 (Generic Name)</label>
                            <InputText v-model="form.generic_name" :invalid="!!form.errors.generic_name" placeholder="예: Acetaminophen" />
                            <small v-if="form.errors.generic_name" class="text-red-500">{{ form.errors.generic_name }}</small>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm">함량</label>
                                <InputText v-model="form.strength" :invalid="!!form.errors.strength" placeholder="예: 500" />
                                <small v-if="form.errors.strength" class="text-red-500">{{ form.errors.strength }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm">단위</label>
                                <InputText v-model="form.unit" :invalid="!!form.errors.unit" placeholder="mg / ml / g" />
                                <small v-if="form.errors.unit" class="text-red-500">{{ form.errors.unit }}</small>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">포장 수량 (1팩 기준)</label>
                            <InputNumber v-model="form.pack_size" :min="1" :invalid="!!form.errors.pack_size" placeholder="예: 30" />
                            <small v-if="form.errors.pack_size" class="text-red-500">{{ form.errors.pack_size }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">제조사</label>
                            <InputText v-model="form.manufacturer" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">약품 유형</label>
                            <Select
                                v-model="form.drug_type"
                                :options="drugTypeOptions"
                                option-label="label"
                                option-value="value"
                            />
                            <small v-if="isNims" class="text-amber-600">
                                <i class="pi pi-shield mr-1" />마약/향정 선택 시 NIMS 관리 대상으로 자동 등록됩니다.
                            </small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">보관 조건</label>
                            <Select
                                v-model="form.storage_condition"
                                :options="storageOptions"
                                option-label="label"
                                option-value="value"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">카테고리</label>
                            <InputText v-model="form.category" placeholder="예: 진통제, 비타민" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">약가 (원)</label>
                            <InputNumber v-model="form.price" :min="0" :max-fraction-digits="2" />
                        </div>
                        <div v-if="isEdit" class="flex flex-col gap-2">
                            <label class="text-sm">판매 상태</label>
                            <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
                            <small class="text-surface-500">단종 처리는 상세 화면의 "단종 처리" 버튼 사용을 권장합니다 (대체품 지정 가능).</small>
                        </div>
                    </div>
                </section>

                <!-- 3. NIMS 정보 (마약/향정일 때만) -->
                <section v-if="isNims" class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">NIMS 연계</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm">NIMS 품목코드</label>
                            <InputText v-model="form.nims_item_code" :invalid="!!form.errors.nims_item_code" placeholder="식약처 NIMS 등록 품목코드" />
                            <small v-if="form.errors.nims_item_code" class="text-red-500">{{ form.errors.nims_item_code }}</small>
                        </div>
                    </div>
                </section>

                <!-- 4. 제품 설명 -->
                <section class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">제품 설명</h3>
                    <Textarea v-model="form.description" rows="4" autoResize />
                </section>

                <!-- 5. 이미지 -->
                <section class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">제품 이미지 (5MB 이하)</h3>
                    <div v-if="showExistingImage" class="flex items-center gap-3 border border-surface rounded-lg p-3">
                        <img :src="existingImageUrl!" class="w-20 h-20 object-cover rounded" alt="현재 이미지" />
                        <Button label="기존 이미지 제거" icon="pi pi-times" severity="danger" outlined size="small" @click="toggleRemoveExisting" />
                    </div>
                    <div v-else-if="existingImageUrl && removeImage" class="flex items-center gap-3 border border-surface rounded-lg p-3 opacity-50">
                        <img :src="existingImageUrl!" class="w-20 h-20 object-cover rounded line-through" alt="삭제 예정" />
                        <Button label="기존 이미지 복원" icon="pi pi-undo" severity="secondary" outlined size="small" @click="toggleRemoveExisting" />
                    </div>
                    <div v-if="imagePreview" class="flex items-center gap-3 border border-primary rounded-lg p-3">
                        <img :src="imagePreview" class="w-20 h-20 object-cover rounded" alt="미리보기" />
                        <span class="text-sm text-surface-600">새 이미지 미리보기</span>
                    </div>
                    <FileUpload
                        :multiple="false"
                        :custom-upload="true"
                        :auto="false"
                        :max-file-size="5242880"
                        accept="image/*"
                        choose-label="이미지 선택"
                        :show-upload-button="false"
                        :show-cancel-button="false"
                        @select="onImageSelect"
                        @clear="onImageClear"
                    >
                        <template #empty>
                            <p class="text-sm text-surface-500">이미지 파일을 선택하세요.</p>
                        </template>
                    </FileUpload>
                </section>

                <!-- 6. 비고 -->
                <section class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">비고</h3>
                    <Textarea v-model="form.remarks" rows="3" autoResize />
                </section>

                <!-- 7. 변경 사유 (Edit + NIMS 관리 대상에서 강조) -->
                <section v-if="isEdit" class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200">
                        변경 사유 <span v-if="needsChangeReason" class="text-red-500">*</span>
                    </h3>
                    <Message v-if="needsChangeReason" severity="warn" :closable="false" class="mb-1">
                        마약/향정(NIMS 관리 대상) 제품의 핵심 컬럼을 변경할 때는 변경 사유 입력이 필수입니다.
                    </Message>
                    <Textarea
                        v-model="form.change_reason"
                        rows="2"
                        autoResize
                        :invalid="!!form.errors.change_reason"
                        :placeholder="needsChangeReason ? '예) NIMS 등록 변경 통보 반영' : '변경 사유 (선택)'"
                    />
                    <small v-if="form.errors.change_reason" class="text-red-500">{{ form.errors.change_reason }}</small>
                </section>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="submit" :label="submitLabel" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </template>
    </Card>
</template>
