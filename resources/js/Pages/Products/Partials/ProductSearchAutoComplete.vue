<script setup lang="ts">
import { ref, watch } from 'vue';
import AutoComplete from 'primevue/autocomplete';

interface ProductSuggestion {
    id: number;
    product_name: string;
    insurance_code: string;
    product_code: string;
    manufacturer: string | null;
}

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        excludeId?: number | null;
        placeholder?: string;
        invalid?: boolean;
        containerClass?: string;
        inputClass?: string;
        /**
         * 미리 알고 있는 초기 라벨 정보 (편집 시 productId만 알고 화면에 즉시 라벨 노출용)
         */
        initialLabel?: { product_name: string; insurance_code: string } | null;
    }>(),
    {
        excludeId: null,
        placeholder: '제품명·보험코드·제품코드 검색',
        invalid: false,
        containerClass: '',
        inputClass: '',
        initialLabel: null,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', value: ProductSuggestion): void;
    (e: 'clear'): void;
}>();

const selected = ref<ProductSuggestion | null>(
    props.modelValue && props.initialLabel
        ? {
              id: props.modelValue,
              product_name: props.initialLabel.product_name,
              insurance_code: props.initialLabel.insurance_code,
              product_code: '',
              manufacturer: null,
          }
        : null,
);

const suggestions = ref<ProductSuggestion[]>([]);
const loading = ref(false);

const search = async (event: { query: string }) => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('q', event.query);
        if (props.excludeId) params.append('exclude', String(props.excludeId));

        const res = await fetch(`${route('products.search')}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            suggestions.value = [];
            return;
        }
        suggestions.value = (await res.json()) as ProductSuggestion[];
    } finally {
        loading.value = false;
    }
};

const onChange = (val: ProductSuggestion | string | null) => {
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
        option-label="product_name"
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
                <span class="font-medium">{{ option.product_name }}</span>
                <span class="text-xs text-surface-500">
                    {{ option.insurance_code }} · {{ option.product_code }}
                    <span v-if="option.manufacturer">· {{ option.manufacturer }}</span>
                </span>
            </div>
        </template>
        <template #empty>
            <div class="px-3 py-2 text-sm text-surface-500">검색 결과가 없습니다.</div>
        </template>
    </AutoComplete>
</template>
