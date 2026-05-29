<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="로그인" />

    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen px-4 py-10">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    class="mx-auto mb-4 inline-flex items-center justify-center rounded-2xl bg-primary-500 text-white shadow-lg"
                    style="width: 64px; height: 64px"
                >
                    <i class="pi pi-chart-line" style="font-size: 1.8rem" />
                </div>
                <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0">정진팜 실적관리</h1>
                <p class="text-surface-500 dark:text-surface-400 mt-2">계정 정보를 입력해 로그인하세요.</p>
            </div>

            <div class="bg-surface-0 dark:bg-surface-900 border border-surface rounded-2xl p-8 shadow-sm">
                <Message v-if="status" severity="success" class="mb-4">{{ status }}</Message>

                <form @submit.prevent="submit" class="flex flex-col gap-5">
                    <div class="flex flex-col gap-2">
                        <label for="email" class="text-sm font-medium text-surface-700 dark:text-surface-200">이메일</label>
                        <InputText
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="name@jungjin.co.kr"
                            :invalid="!!form.errors.email"
                            class="w-full"
                        />
                        <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="text-sm font-medium text-surface-700 dark:text-surface-200">비밀번호</label>
                        <Password
                            input-id="password"
                            v-model="form.password"
                            required
                            :feedback="false"
                            toggle-mask
                            autocomplete="current-password"
                            placeholder="비밀번호 입력"
                            :invalid="!!form.errors.password"
                            input-class="w-full"
                            class="w-full"
                            fluid
                        />
                        <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="form.remember" :binary="true" inputId="remember" />
                            <label for="remember" class="text-sm text-surface-700 dark:text-surface-200">로그인 유지</label>
                        </div>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-primary hover:underline"
                        >
                            비밀번호 찾기
                        </Link>
                    </div>

                    <Button
                        type="submit"
                        label="로그인"
                        icon="pi pi-sign-in"
                        :loading="form.processing"
                        class="w-full"
                    />
                </form>
            </div>

            <p class="text-center text-sm text-surface-500 dark:text-surface-400 mt-6">
                계정이 없으신가요?
                <Link :href="route('register')" class="text-primary hover:underline ml-1">계정 등록 요청</Link>
            </p>
        </div>
    </div>
</template>
