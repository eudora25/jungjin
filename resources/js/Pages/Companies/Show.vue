<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Card from 'primevue/card';
import ConfirmDialog from 'primevue/confirmdialog';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';

interface Company {
    id: number;
    company_name: string;
    business_registration_number: string | null;
    representative_name: string | null;
    company_group: string | null;
    default_commission_grade: string | null;
    postcode: string | null;
    business_address: string | null;
    contact_person_name: string | null;
    landline_phone: string | null;
    mobile_phone: string | null;
    mobile_phone_2: string | null;
    email: string | null;
    receive_email: string | null;
    assigned_pharmacist_contact: string | null;
    remarks: string | null;
    status: string;
    approval_status: string;
    approved_at: string | null;
    created_at: string;
    updated_at: string;
    creator: { id: number; name: string } | null;
    updater: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
}

interface RecentSettlementRow {
    id: number;
    settlement_no: string;
    period_month: string;
    status: string;
    line_count: number;
    total_quantity: number;
    total_subtotal: string | number;
    total_commission: string | number;
    calculated_at: string | null;
}

interface SalesAssignment {
    id: number;
    user_id: number;
    user_name: string | null;
    user_email: string | null;
    user_active: boolean;
    assigned_at: string | null;
    assigner_name: string | null;
}

interface SalesUserOption {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    company: Company;
    recentSettlements: RecentSettlementRow[];
    salesAssignments: SalesAssignment[];
    availableSalesUsers: SalesUserOption[];
    can: { update: boolean; delete: boolean; manageSalesAssignments: boolean };
}>();

const confirm = useConfirm();

const approvalLabel = (s: string) => ({ pending: '대기', approved: '승인', rejected: '반려' }[s] ?? s);
const approvalSeverity = (s: string) =>
    ({ pending: 'warn', approved: 'success', rejected: 'danger' }[s] ?? 'secondary');

const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('ko-KR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso)) : '-';

const money = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === '') return '-';
    const n = typeof v === 'number' ? v : parseFloat(String(v));
    if (Number.isNaN(n)) return '-';
    return `${n.toLocaleString('ko-KR', { maximumFractionDigits: 2 })}원`;
};

const statusLabel = (s: string) => ({ draft: '초안', confirmed: '확정', paid: '지급완료', cancelled: '취소' }[s] ?? s);
const statusSeverity = (s: string) =>
    ({ draft: 'secondary', confirmed: 'info', paid: 'success', cancelled: 'contrast' }[s] ?? 'secondary');

const confirmDelete = () => {
    confirm.require({
        message: '이 업체를 삭제하시겠습니까? (soft delete로 복원 가능)',
        header: '업체 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('companies.destroy', props.company.id)),
    });
};

// ── GAP-4: 담당 영업사원 배정 ─────────────────────────────────────────────────

const assignDialogVisible = ref(false);

const assignForm = useForm({
    user_id: null as number | null,
});

const openAssignDialog = () => {
    assignForm.reset();
    assignDialogVisible.value = true;
};

const submitAssign = () => {
    assignForm.post(route('companies.sales-assignments.store', props.company.id), {
        preserveScroll: true,
        onSuccess: () => {
            assignDialogVisible.value = false;
            assignForm.reset();
        },
    });
};

const confirmRevoke = (assignment: SalesAssignment) => {
    confirm.require({
        message: `${assignment.user_name} 영업사원의 담당 배정을 해제하시겠습니까?`,
        header: '담당 배정 해제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '해제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () =>
            router.delete(
                route('companies.sales-assignments.destroy', [props.company.id, assignment.id]),
                { preserveScroll: true },
            ),
    });
};

const formatAssignedAt = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('ko-KR', { dateStyle: 'medium' }).format(new Date(iso)) : '-';
</script>

<template>
    <Head :title="company.company_name" />

    <ConfirmDialog />

    <AdminLayout>
        <div class="flex flex-col gap-4 max-w-5xl">
            <Link :href="route('companies.index')" class="text-sm text-surface-500 hover:text-primary">
                <i class="pi pi-arrow-left mr-1" />업체 목록
            </Link>

            <Card>
                <template #content>
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex-1">
                            <div class="flex items-center flex-wrap gap-2 mb-2">
                                <Tag :value="approvalLabel(company.approval_status)" :severity="approvalSeverity(company.approval_status)" />
                                <Tag
                                    :value="company.status === 'active' ? '활성' : '비활성'"
                                    :severity="company.status === 'active' ? 'success' : 'secondary'"
                                />
                                <Tag v-if="company.default_commission_grade" :value="`등급 ${company.default_commission_grade}`" severity="info" />
                                <Tag v-if="company.company_group" :value="company.company_group" severity="secondary" />
                            </div>
                            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ company.company_name }}</h1>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link v-if="can.update" :href="route('companies.edit', company.id)">
                                <Button label="수정" icon="pi pi-pencil" severity="secondary" outlined />
                            </Link>
                            <Button
                                v-if="can.delete"
                                label="삭제"
                                icon="pi pi-trash"
                                severity="danger"
                                outlined
                                @click="confirmDelete"
                            />
                        </div>
                    </div>

                    <hr class="border-surface-200 dark:border-surface-700 my-4" />

                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt class="text-surface-500 mb-1">사업자등록번호</dt>
                            <dd>{{ company.business_registration_number ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">대표자</dt>
                            <dd>{{ company.representative_name ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">사업장 주소</dt>
                            <dd>
                                <span v-if="company.postcode" class="text-surface-400 mr-2">[{{ company.postcode }}]</span>
                                {{ company.business_address ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">담당자</dt>
                            <dd>{{ company.contact_person_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">대표 전화</dt>
                            <dd>{{ company.landline_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">휴대폰</dt>
                            <dd>
                                {{ company.mobile_phone ?? '-' }}
                                <span v-if="company.mobile_phone_2" class="text-surface-400 ml-2">/ {{ company.mobile_phone_2 }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">이메일</dt>
                            <dd>{{ company.email ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">수신 이메일</dt>
                            <dd>{{ company.receive_email ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">담당 제약사 관리자</dt>
                            <dd>{{ company.assigned_pharmacist_contact ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-surface-500 mb-1">비고</dt>
                            <dd class="whitespace-pre-wrap">{{ company.remarks || '-' }}</dd>
                        </div>
                    </dl>

                    <hr class="border-surface-200 dark:border-surface-700 my-4" />

                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 text-xs text-surface-500">
                        <div>
                            <dt class="mb-1">등록</dt>
                            <dd>{{ formatDate(company.created_at) }} · {{ company.creator?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1">최종 수정</dt>
                            <dd>{{ formatDate(company.updated_at) }} · {{ company.updater?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1">승인</dt>
                            <dd>{{ formatDate(company.approved_at) }} · {{ company.approver?.name ?? '-' }}</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-lg font-semibold">담당 영업사원</h2>
                            <p class="text-sm text-surface-500 mt-1">
                                이 거래처를 담당하는 영업사원입니다. (총 {{ salesAssignments.length }}명)
                            </p>
                        </div>
                        <Button
                            v-if="can.manageSalesAssignments"
                            label="영업사원 배정"
                            icon="pi pi-user-plus"
                            :disabled="availableSalesUsers.length === 0"
                            @click="openAssignDialog"
                        />
                    </div>

                    <DataTable :value="salesAssignments" :striped-rows="true" data-key="id">
                        <Column header="영업사원" class="min-w-[120px]">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ data.user_name ?? '-' }}</span>
                                    <Tag v-if="!data.user_active" value="비활성" severity="secondary" />
                                </div>
                            </template>
                        </Column>
                        <Column header="이메일" class="min-w-[180px]">
                            <template #body="{ data }">
                                <span class="text-sm text-surface-500">{{ data.user_email ?? '-' }}</span>
                            </template>
                        </Column>
                        <Column header="배정일" class="min-w-[120px]">
                            <template #body="{ data }">{{ formatAssignedAt(data.assigned_at) }}</template>
                        </Column>
                        <Column header="배정자" class="min-w-[100px]">
                            <template #body="{ data }">
                                <span class="text-sm text-surface-500">{{ data.assigner_name ?? '-' }}</span>
                            </template>
                        </Column>
                        <Column header="" class="w-20">
                            <template #body="{ data }">
                                <Button
                                    v-if="can.manageSalesAssignments"
                                    icon="pi pi-times"
                                    size="small"
                                    severity="danger"
                                    text
                                    @click="confirmRevoke(data)"
                                />
                            </template>
                        </Column>
                        <template #empty>
                            <div class="py-6 text-center text-surface-500 text-sm">아직 담당 영업사원이 배정되지 않았습니다.</div>
                        </template>
                    </DataTable>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-lg font-semibold">최근 정산 (최근 6개월)</h2>
                            <p class="text-sm text-surface-500 mt-1">이 거래처의 최근 정산 내역을 요약해서 보여줍니다.</p>
                        </div>
                    </div>

                    <DataTable :value="recentSettlements ?? []" :striped-rows="true" data-key="id">
                        <Column header="정산월" class="min-w-[100px]" field="period_month" />
                        <Column header="정산번호" class="min-w-[140px]">
                            <template #body="{ data }">
                                <Link :href="route('settlements.show', data.id)" class="text-primary-600 hover:underline font-medium">
                                    {{ data.settlement_no }}
                                </Link>
                            </template>
                        </Column>
                        <Column header="상태" class="min-w-[90px]">
                            <template #body="{ data }">
                                <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                            </template>
                        </Column>
                        <Column header="라인" class="text-right">
                            <template #body="{ data }">{{ (data.line_count ?? 0).toLocaleString('ko-KR') }}</template>
                        </Column>
                        <Column header="매출 합계" class="text-right">
                            <template #body="{ data }">{{ money(data.total_subtotal) }}</template>
                        </Column>
                        <Column header="수수료 합계" class="text-right">
                            <template #body="{ data }">{{ money(data.total_commission) }}</template>
                        </Column>
                        <Column header="계산일시" class="min-w-[160px]">
                            <template #body="{ data }">{{ formatDate(data.calculated_at) }}</template>
                        </Column>
                        <template #empty>
                            <div class="py-6 text-center text-surface-500 text-sm">정산 데이터가 없습니다.</div>
                        </template>
                    </DataTable>
                </template>
            </Card>
        </div>

        <!-- GAP-4: 담당 영업사원 배정 모달 -->
        <Dialog
            v-model:visible="assignDialogVisible"
            header="담당 영업사원 배정"
            modal
            :style="{ width: '420px' }"
        >
            <form @submit.prevent="submitAssign" class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">영업사원 *</label>
                    <Select
                        v-model="assignForm.user_id"
                        :options="availableSalesUsers"
                        option-label="name"
                        option-value="id"
                        placeholder="영업사원 선택"
                        :invalid="!!assignForm.errors.user_id"
                    />
                    <small v-if="assignForm.errors.user_id" class="text-red-500">
                        {{ assignForm.errors.user_id }}
                    </small>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        label="취소"
                        severity="secondary"
                        outlined
                        type="button"
                        @click="assignDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="배정"
                        icon="pi pi-check"
                        :loading="assignForm.processing"
                        :disabled="!assignForm.user_id"
                    />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
