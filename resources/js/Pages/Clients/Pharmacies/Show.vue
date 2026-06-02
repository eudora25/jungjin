<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBusinessNumber } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
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
    created_at: string;
    creator?: { name: string } | null;
    updater?: { name: string } | null;
    company?: { id: number; company_name: string } | null;
}

const props = defineProps<{
    pharmacy: Pharmacy;
    can: { update: boolean; delete: boolean };
}>();

const confirm = useConfirm();

const confirmDelete = () => {
    confirm.require({
        message: `${props.pharmacy.pharmacy_name} 을(를) 삭제하시겠습니까?`,
        header: '약국 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('pharmacies.destroy', props.pharmacy.id)),
    });
};
</script>

<template>
    <Head :title="pharmacy.pharmacy_name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="max-w-4xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ pharmacy.pharmacy_name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">약국 상세 정보</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('pharmacies.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.update" :href="route('pharmacies.edit', pharmacy.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                    <Button v-if="can.delete" label="삭제" icon="pi pi-trash" severity="danger" outlined
                            @click="confirmDelete" />
                </div>
            </div>

            <Card>
                <template #content>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                        <div>
                            <dt class="text-surface-500 mb-1">약국 코드</dt>
                            <dd>{{ pharmacy.pharmacy_code ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">상태</dt>
                            <dd>
                                <Tag :value="pharmacy.status === 'active' ? '활성' : '비활성'"
                                     :severity="pharmacy.status === 'active' ? 'success' : 'secondary'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">사업자등록번호</dt>
                            <dd>{{ formatBusinessNumber(pharmacy.business_registration_number) }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">대표자명</dt>
                            <dd>{{ pharmacy.representative_name ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">주소</dt>
                            <dd>
                                <span v-if="pharmacy.postcode" class="text-xs text-surface-400 mr-2">[{{ pharmacy.postcode }}]</span>
                                {{ pharmacy.address ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">대표 전화</dt>
                            <dd>{{ pharmacy.landline_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">대표자 휴대폰</dt>
                            <dd>{{ pharmacy.mobile_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">담당자</dt>
                            <dd>{{ pharmacy.contact_person_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">담당자 연락처</dt>
                            <dd>{{ pharmacy.contact_phone ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">이메일</dt>
                            <dd>{{ pharmacy.email ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">비고</dt>
                            <dd class="whitespace-pre-line">{{ pharmacy.remarks ?? '-' }}</dd>
                        </div>
                        <div v-if="pharmacy.company" class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">연결된 거래처</dt>
                            <dd>
                                <Link :href="route('companies.show', pharmacy.company.id)" class="text-primary-600 hover:underline">
                                    {{ pharmacy.company.company_name }}
                                </Link>
                                <span class="text-xs text-surface-400 ml-2">· 통합 거래처 연동 시에만 표시되는 선택 링크</span>
                            </dd>
                        </div>
                    </dl>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
