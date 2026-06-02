<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBusinessNumber } from '@/utils/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDialog from 'primevue/confirmdialog';

interface Specialty { id: number; dept_code: string; dept_name: string | null; specialist_count: number | null; selective_doctor_count: number | null; }
interface Equipment { id: number; equipment_code: string; equipment_name: string | null; quantity: number | null; }
interface CodeRow { id: number; [k: string]: unknown; }
interface Facility { [k: string]: number | string | null; establishment_name: string | null; }
interface Hours {
    location_building: string | null;
    parking_capacity: number | null;
    parking_fee_required: boolean | null;
    closed_sunday: string | null;
    closed_holiday: string | null;
    er_day_available: boolean | null;
    lunch_weekday: string | null;
    reception_weekday: string | null;
    treatment_hours: Record<string, { start: string | null; end: string | null }> | null;
}

interface Hospital {
    id: number;
    hospital_code: string | null;
    ykiho: string | null;
    hospital_name: string;
    business_registration_number: string | null;
    hospital_type: string | null;
    clazz_code: string | null;
    sido_code: string | null;
    sigungu_code: string | null;
    eupmyeondong: string | null;
    specialty: string | null;
    representative_name: string | null;
    postcode: string | null;
    address: string | null;
    phone: string | null;
    opened_on: string | null;
    closed_on: string | null;
    doctor_count: number | null;
    bed_count: number | null;
    inpatient_room_count: number | null;
    latitude: string | null;
    longitude: string | null;
    homepage: string | null;
    contact_person_name: string | null;
    contact_phone: string | null;
    email: string | null;
    remarks: string | null;
    status: 'active' | 'inactive';
    specialties: Specialty[];
    equipments: Equipment[];
    facility: Facility | null;
    hours: Hours | null;
    transports: CodeRow[];
    nursing_grades: CodeRow[];
    meal_surcharges: CodeRow[];
    special_treatments: CodeRow[];
    specialized_fields: CodeRow[];
    other_staff: CodeRow[];
}

interface NumberHistory {
    id: number;
    business_registration_number: string;
    is_current: boolean;
    valid_from: string | null;
    valid_to: string | null;
    reason: string | null;
    note: string | null;
}

const props = defineProps<{ hospital: Hospital; numberHistories: NumberHistory[] }>();

const typeLabels: Record<string, string> = {
    general_hospital: '종합병원', hospital: '병원', clinic: '의원', dental: '치과', oriental: '한의원', other: '기타',
};

const hasEnrichment = computed(() => !!props.hospital.ykiho);

// 병상 상세 — 값이 있는 항목만
const BED_LABELS: Record<string, string> = {
    general_upper_beds: '일반 상급', general_normal_beds: '일반', adult_icu_beds: '성인 중환자',
    child_icu_beds: '소아 중환자', newborn_icu_beds: '신생아 중환자', delivery_room_beds: '분만실',
    operating_room_beds: '수술실', emergency_room_beds: '응급실', physical_therapy_beds: '물리치료실',
    psych_closed_upper_beds: '정신과 폐쇄 상급', psych_closed_normal_beds: '정신과 폐쇄 일반',
    psych_open_upper_beds: '정신과 개방 상급', psych_open_normal_beds: '정신과 개방 일반',
    isolation_beds: '격리병실', sterile_treatment_beds: '무균치료실',
};
const beds = computed(() => {
    const f = props.hospital.facility;
    if (!f) return [];
    return Object.entries(BED_LABELS)
        .map(([k, label]) => ({ label, value: Number(f[k] ?? 0) }))
        .filter((b) => b.value > 0);
});

const WEEK = [
    ['mon', '월'], ['tue', '화'], ['wed', '수'], ['thu', '목'], ['fri', '금'], ['sat', '토'], ['sun', '일'],
] as const;
const fmtTime = (t: string | null) => (t && t.length === 4 ? `${t.slice(0, 2)}:${t.slice(2)}` : (t ?? '-'));
const weekHours = computed(() => {
    const th = props.hospital.hours?.treatment_hours ?? {};
    return WEEK.map(([key, ko]) => ({
        ko,
        start: fmtTime(th[key]?.start ?? null),
        end: fmtTime(th[key]?.end ?? null),
        has: !!th[key],
    }));
});

const confirm = useConfirm();
const confirmDelete = () => {
    confirm.require({
        message: `${props.hospital.hospital_name} 을(를) 삭제하시겠습니까?`,
        header: '병의원 삭제',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: '삭제',
        rejectLabel: '취소',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(route('platform.hospitals.destroy', props.hospital.id)),
    });
};

// 사업자번호 변경 모달
const showChangeDialog = ref(false);
const changeForm = useForm({
    new_business_registration_number: '',
    valid_from: '',
    previous_valid_to: '',
    reason: '',
    note: '',
});

const openChangeDialog = () => {
    changeForm.reset();
    changeForm.clearErrors();
    showChangeDialog.value = true;
};

const submitChange = () =>
    changeForm.post(route('platform.hospitals.business-number.change', props.hospital.id), {
        preserveScroll: true,
        onSuccess: () => {
            showChangeDialog.value = false;
            changeForm.reset();
        },
    });
</script>

<template>
    <Head :title="hospital.hospital_name" />
    <ConfirmDialog />
    <AdminLayout>
        <div class="max-w-4xl mx-auto flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ hospital.hospital_name }}</h1>
                    <p class="text-surface-500 text-sm mt-1">병의원 상세 (공유 마스터)</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('platform.hospitals.index')">
                        <Button label="목록으로" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Link :href="route('platform.hospitals.edit', hospital.id)">
                        <Button label="수정" icon="pi pi-pencil" />
                    </Link>
                    <Button label="삭제" icon="pi pi-trash" severity="danger" outlined @click="confirmDelete" />
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
                                <Tag v-if="hospital.hospital_type" :value="typeLabels[hospital.hospital_type] ?? hospital.hospital_type" severity="info" />
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
                    </dl>
                </template>
            </Card>

            <!-- 사업자등록번호 이력 -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>사업자등록번호 이력
                            <span class="text-surface-400 text-sm font-normal">({{ numberHistories.length }})</span>
                        </span>
                        <Button label="사업자번호 변경" icon="pi pi-sync" size="small" @click="openChangeDialog" />
                    </div>
                </template>
                <template #content>
                    <p class="text-surface-500 text-sm mb-3">
                        폐업·재등록 등으로 번호가 바뀌면 변경 이력이 쌓입니다. 옛 번호로 검색해도 이 병의원이 조회됩니다.
                    </p>
                    <DataTable :value="numberHistories" striped-rows size="small">
                        <template #empty>
                            <div class="text-center py-6 text-surface-500">등록된 사업자번호 이력이 없습니다.</div>
                        </template>
                        <Column header="사업자등록번호" style="min-width: 150px">
                            <template #body="{ data }">
                                <span class="font-medium">{{ formatBusinessNumber(data.business_registration_number) }}</span>
                                <Tag v-if="data.is_current" value="현재" severity="success" class="ml-2" />
                            </template>
                        </Column>
                        <Column header="적용기간" style="min-width: 180px">
                            <template #body="{ data }">
                                {{ data.valid_from ?? '?' }} ~ {{ data.is_current ? '현재' : (data.valid_to ?? '?') }}
                            </template>
                        </Column>
                        <Column header="사유" style="min-width: 140px">
                            <template #body="{ data }">{{ data.reason ?? '-' }}</template>
                        </Column>
                        <Column header="비고" style="min-width: 120px">
                            <template #body="{ data }">{{ data.note ?? '-' }}</template>
                        </Column>
                    </DataTable>
                </template>
            </Card>

            <!-- 공공데이터 보강 -->
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <span>공공데이터 보강</span>
                        <Tag v-if="hasEnrichment" value="심평원 매칭됨" severity="success" />
                        <Tag v-else value="미매칭" severity="secondary" />
                    </div>
                </template>
                <template #content>
                    <Message v-if="!hasEnrichment" severity="secondary" :closable="false">
                        아직 심평원(HIRA) 데이터와 매칭되지 않았습니다. 플랫폼 › 병의원 보강(공공데이터)에서 병원정보(B-1)를 업로드하면 보강됩니다.
                    </Message>
                    <dl v-else class="grid grid-cols-2 md:grid-cols-4 gap-y-4 gap-x-6 text-sm">
                        <div>
                            <dt class="text-surface-500 mb-1">개설일자</dt>
                            <dd>{{ hospital.opened_on ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">의사 수</dt>
                            <dd>{{ hospital.doctor_count ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">병상 수</dt>
                            <dd>{{ hospital.bed_count ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">입원실 수</dt>
                            <dd>{{ hospital.inpatient_room_count ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">종별코드</dt>
                            <dd>{{ hospital.clazz_code ?? '-' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-surface-500 mb-1">홈페이지</dt>
                            <dd class="truncate">
                                <a v-if="hospital.homepage" :href="hospital.homepage" target="_blank" rel="noopener"
                                   class="text-primary underline">{{ hospital.homepage }}</a>
                                <span v-else>-</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-surface-500 mb-1">좌표</dt>
                            <dd>
                                <span v-if="hospital.latitude">{{ hospital.latitude }}, {{ hospital.longitude }}</span>
                                <span v-else>-</span>
                            </dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <!-- 진료과목 -->
            <Card v-if="hospital.specialties.length">
                <template #title>진료과목 <span class="text-surface-400 text-sm font-normal">({{ hospital.specialties.length }})</span></template>
                <template #content>
                    <div class="flex flex-wrap gap-2">
                        <Tag v-for="s in hospital.specialties" :key="s.id" severity="info"
                             :value="`${s.dept_name ?? s.dept_code}${s.specialist_count ? ' · 전문의 ' + s.specialist_count : ''}`" />
                    </div>
                </template>
            </Card>

            <!-- 시설·병상 -->
            <Card v-if="beds.length || hospital.facility?.establishment_name">
                <template #title>시설·병상</template>
                <template #content>
                    <p v-if="hospital.facility?.establishment_name" class="text-sm mb-3">
                        <span class="text-surface-500">설립구분</span> {{ hospital.facility.establishment_name }}
                    </p>
                    <div v-if="beds.length" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div v-for="b in beds" :key="b.label" class="border border-surface-200 dark:border-surface-700 rounded p-2 text-center">
                            <div class="text-xs text-surface-500">{{ b.label }}</div>
                            <div class="text-lg font-semibold">{{ b.value }}</div>
                        </div>
                    </div>
                    <p v-else class="text-surface-400 text-sm">등록된 병상 정보가 없습니다.</p>
                </template>
            </Card>

            <!-- 진료시간 -->
            <Card v-if="hospital.hours">
                <template #title>진료시간·편의</template>
                <template #content>
                    <div class="overflow-x-auto">
                        <table class="text-sm w-full mb-3">
                            <thead>
                                <tr class="text-surface-500">
                                    <th v-for="d in weekHours" :key="d.ko" class="px-2 py-1 font-normal">{{ d.ko }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="text-center">
                                    <td v-for="d in weekHours" :key="d.ko" class="px-2 py-1"
                                        :class="d.has ? '' : 'text-surface-300'">
                                        <template v-if="d.has">{{ d.start }}<br>~{{ d.end }}</template>
                                        <template v-else>휴진</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <dl class="grid grid-cols-2 md:grid-cols-4 gap-y-3 gap-x-6 text-sm">
                        <div><dt class="text-surface-500 mb-1">점심시간</dt><dd>{{ hospital.hours.lunch_weekday ?? '-' }}</dd></div>
                        <div><dt class="text-surface-500 mb-1">접수시간(평일)</dt><dd>{{ hospital.hours.reception_weekday ?? '-' }}</dd></div>
                        <div><dt class="text-surface-500 mb-1">주차</dt><dd>{{ hospital.hours.parking_capacity != null ? hospital.hours.parking_capacity + '대' : '-' }}<span v-if="hospital.hours.parking_fee_required != null"> ({{ hospital.hours.parking_fee_required ? '유료' : '무료' }})</span></dd></div>
                        <div><dt class="text-surface-500 mb-1">응급실(주간)</dt><dd>{{ hospital.hours.er_day_available == null ? '-' : (hospital.hours.er_day_available ? '운영' : '미운영') }}</dd></div>
                    </dl>
                </template>
            </Card>

            <!-- 의료장비 -->
            <Card v-if="hospital.equipments.length">
                <template #title>의료장비 <span class="text-surface-400 text-sm font-normal">({{ hospital.equipments.length }})</span></template>
                <template #content>
                    <DataTable :value="hospital.equipments" striped-rows size="small">
                        <Column header="장비명" field="equipment_name" />
                        <Column header="대수" style="width: 100px">
                            <template #body="{ data }">{{ data.quantity ?? '-' }}</template>
                        </Column>
                    </DataTable>
                </template>
            </Card>

            <!-- 기타 보강 (전문병원·특수진료·간호등급·기타인력) -->
            <Card v-if="hospital.specialized_fields.length || hospital.special_treatments.length || hospital.nursing_grades.length || hospital.other_staff.length">
                <template #title>기타 정보</template>
                <template #content>
                    <div class="flex flex-col gap-3 text-sm">
                        <div v-if="hospital.specialized_fields.length">
                            <span class="text-surface-500 mr-2">전문병원 지정분야</span>
                            <Tag v-for="f in hospital.specialized_fields" :key="f.id" class="mr-1" severity="warn" :value="(f.field_name as string) ?? (f.field_code as string)" />
                        </div>
                        <div v-if="hospital.special_treatments.length">
                            <span class="text-surface-500 mr-2">특수진료</span>
                            <Tag v-for="t in hospital.special_treatments" :key="t.id" class="mr-1" severity="secondary" :value="(t.search_name as string) ?? (t.search_code as string)" />
                        </div>
                        <div v-if="hospital.nursing_grades.length">
                            <span class="text-surface-500 mr-2">간호등급</span>
                            <span v-for="n in hospital.nursing_grades" :key="n.id" class="mr-3">{{ n.insurance_type_name }} {{ n.nursing_grade }}등급</span>
                        </div>
                        <div v-if="hospital.other_staff.length">
                            <span class="text-surface-500 mr-2">기타인력</span>
                            <span v-for="o in hospital.other_staff" :key="o.id" class="mr-3">{{ o.staff_name }} {{ o.staff_count }}</span>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="showChangeDialog" modal header="사업자등록번호 변경" :style="{ width: '32rem' }">
            <form class="flex flex-col gap-3" @submit.prevent="submitChange">
                <p class="text-sm text-surface-500">
                    현재 번호: <span class="font-medium">{{ hospital.business_registration_number ? formatBusinessNumber(hospital.business_registration_number) : '미등록' }}</span>
                </p>
                <div>
                    <label class="block text-sm mb-1">새 사업자등록번호 <span class="text-red-500">*</span></label>
                    <InputText v-model="changeForm.new_business_registration_number" class="w-full" placeholder="예: 234-56-78901" />
                    <Message v-if="changeForm.errors.new_business_registration_number" severity="error" size="small" variant="simple">{{ changeForm.errors.new_business_registration_number }}</Message>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">새 번호 적용 시작일</label>
                        <InputText v-model="changeForm.valid_from" type="date" class="w-full" />
                        <Message v-if="changeForm.errors.valid_from" severity="error" size="small" variant="simple">{{ changeForm.errors.valid_from }}</Message>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">이전 번호 종료일(폐업일)</label>
                        <InputText v-model="changeForm.previous_valid_to" type="date" class="w-full" />
                        <Message v-if="changeForm.errors.previous_valid_to" severity="error" size="small" variant="simple">{{ changeForm.errors.previous_valid_to }}</Message>
                    </div>
                </div>
                <div>
                    <label class="block text-sm mb-1">변경 사유</label>
                    <InputText v-model="changeForm.reason" class="w-full" placeholder="예: 폐업 후 재등록" />
                    <Message v-if="changeForm.errors.reason" severity="error" size="small" variant="simple">{{ changeForm.errors.reason }}</Message>
                </div>
                <div>
                    <label class="block text-sm mb-1">비고</label>
                    <Textarea v-model="changeForm.note" class="w-full" rows="2" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="취소" severity="secondary" outlined type="button" @click="showChangeDialog = false" />
                    <Button label="변경 기록" icon="pi pi-check" type="submit" :loading="changeForm.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
