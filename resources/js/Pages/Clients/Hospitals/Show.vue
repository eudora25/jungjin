<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBusinessNumber } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';

interface Hospital {
    id: number;
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
    created_at: string;
    company?: { id: number; company_name: string } | null;
}

const props = defineProps<{
    hospital: Hospital;
    can: { update: boolean; delete: boolean };
}>();

const typeLabels: Record<string, string> = {
    general_hospital: '종합병원',
    hospital: '병원',
    clinic: '의원',
    dental: '치과',
    oriental: '한의원',
    other: '기타',
};

const confirm = useConfirm();

const confirmDelete = () => {
    confirm.require({
        message: `${props.hospital.hospital_name} 을(를) 삭제하시겠습니까?`,
        header: '병의원 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('hospitals.destroy', props.hospital.id)),
    });
};
</script>

<template>
    <Head :title="hospital.hospital_name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="max-w-4xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ hospital.hospital_name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">병의원 상세 정보</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('hospitals.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link v-if="can.update" :href="route('hospitals.edit', hospital.id)">
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
                            <dt class="text-surface-500 mb-1">병의원 코드</dt>
                            <dd>{{ hospital.hospital_code ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">상태</dt>
                            <dd>
                                <Tag :value="hospital.status === 'active' ? '활성' : '비활성'"
                                     :severity="hospital.status === 'active' ? 'success' : 'secondary'" />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">유형</dt>
                            <dd>
                                <Tag v-if="hospital.hospital_type" :value="typeLabels[hospital.hospital_type]" severity="info" />
                                <span v-else>-</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">전문 분야</dt>
                            <dd>{{ hospital.specialty ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">사업자등록번호</dt>
                            <dd>{{ formatBusinessNumber(hospital.business_registration_number) }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">원장/대표자명</dt>
                            <dd>{{ hospital.representative_name ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">주소</dt>
                            <dd>
                                <span v-if="hospital.postcode" class="text-xs text-surface-400 mr-2">[{{ hospital.postcode }}]</span>
                                {{ hospital.address ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">대표 전화</dt>
                            <dd>{{ hospital.phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">담당자</dt>
                            <dd>{{ hospital.contact_person_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">담당자 연락처</dt>
                            <dd>{{ hospital.contact_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">이메일</dt>
                            <dd>{{ hospital.email ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">비고</dt>
                            <dd class="whitespace-pre-line">{{ hospital.remarks ?? '-' }}</dd>
                        </div>
                        <div v-if="hospital.company" class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">연결된 거래처</dt>
                            <dd>
                                <Link :href="route('companies.show', hospital.company.id)" class="text-primary-600 hover:underline">
                                    {{ hospital.company.company_name }}
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
