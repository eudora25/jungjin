<script setup lang="ts">
import AppLayout from '@/layout/AppLayout.vue';
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';

const page = usePage();
const impersonating = computed(
    () => (page.props?.impersonating as { id: number; name: string } | null) ?? null,
);

const exitImpersonation = () => router.post(route('platform.exit'));
</script>

<template>
    <AppLayout>
        <div
            v-if="impersonating"
            class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-purple-300 bg-purple-50 px-4 py-2 text-sm dark:border-purple-700 dark:bg-purple-950/40"
        >
            <div class="flex items-center gap-2 text-purple-800 dark:text-purple-200">
                <i class="pi pi-eye" />
                <span>플랫폼 운영자로 <strong>{{ impersonating.name }}</strong> 제약사 화면을 보는 중입니다.</span>
            </div>
            <Button label="진입 종료" icon="pi pi-sign-out" size="small" severity="help" outlined @click="exitImpersonation" />
        </div>
        <slot />
    </AppLayout>
</template>
