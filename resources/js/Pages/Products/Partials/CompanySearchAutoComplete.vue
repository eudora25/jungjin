<script setup lang="ts">
import { ref, watch } from 'vue';
import AutoComplete from 'primevue/autocomplete';

interface CompanySuggestion {
    id: number;
    company_name: string;
    business_registration_number: string | null;
    default_commission_grade: string | null;
    partner_type?: 'company' | 'pharmacy' | 'hospital';
}

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        placeholder?: string;
        invalid?: boolean;
        containerClass?: string;
        inputClass?: string;
        initialLabel?: { company_name: string; business_registration_number: string | null } | null;
    }>(),
    {
        placeholder: '거래처명·사업자번호·담당자 검색',
        invalid: false,
        containerClass: '',
        inputClass: '',
        initialLabel: null,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', value: CompanySuggestion): void;
    (e: 'clear'): void;
}>();

const selected = ref<CompanySuggestion | null>(
    props.modelValue && props.initialLabel
        ? {
              id: props.modelValue,
              company_name: props.initialLabel.company_name,
              business_registration_number: props.initialLabel.business_registration_number,
              default_commission_grade: null,
          }
        : null,
);

const suggestions = ref<CompanySuggestion[]>([]);
const loading = ref(false);

const search = async (event: { query: string }) => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('q', event.query);

        const res = await fetch(`${route('companies.search')}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            suggestions.value = [];
            return;
        }
        suggestions.value = (await res.json()) as CompanySuggestion[];
    } finally {
        loading.value = false;
    }
};

const onChange = (val: CompanySuggestion | string | null) => {
    if (val && typeof val === 'object') {
        selected.value = val;
        emit('update:modelValue', val.id);
        emit('select', val);
    } else if (val === null) {
        selected.value = null;
        emit('update:modelValue', null);
        emit('clear');
    }
};

const onClear = () => {
    selected.value = null;
    emit('update:modelValue', null);
    emit('clear');
};

watch(
    () => props.modelValue,
    (next) => {
        if (next === null) {
            selected.value = null;
        }
    },
);
</script>

<template>
    <AutoComplete
        :model-value="selected"
        :suggestions="suggestions"
        :placeholder="placeholder"
        :invalid="invalid"
        :loading="loading"
        option-label="company_name"
        :force-selection="false"
        :delay="300"
        :min-length="1"
        :class="['w-full', containerClass]"
        :input-class="inputClass"
        @complete="search"
        @item-select="(e) => onChange(e.value)"
        @clear="onClear"
    >
        <template #option="{ option }">
            <div class="flex flex-col">
                <span class="font-medium">{{ option.company_name }}</span>
                <span class="text-xs text-surface-500">
                    <span v-if="option.business_registration_number">{{ option.business_registration_number }}</span>
                    <span v-if="option.partner_type" class="ml-1">
                        ·
                        <span v-if="option.partner_type === 'pharmacy'">약국</span>
                        <span v-else-if="option.partner_type === 'hospital'">병원</span>
                        <span v-else>업체</span>
                    </span>
                    <span v-if="option.default_commission_grade" class="ml-1">
                        · 등급 {{ option.default_commission_grade.toUpperCase() }}
                    </span>
                </span>
            </div>
        </template>
        <template #empty>
            <div class="px-3 py-2 text-sm text-surface-500">검색 결과가 없습니다.</div>
        </template>
    </AutoComplete>
</template>
