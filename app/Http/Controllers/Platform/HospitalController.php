<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ChangeBusinessNumberRequest;
use App\Http\Requests\Platform\StoreHospitalRequest;
use App\Http\Requests\Platform\UpdateHospitalRequest;
use App\Models\Hospital;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 병의원(공유 마스터) 전역 CRUD. (GAP-10 MT-6, super_admin)
 */
class HospitalController extends Controller
{
    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->isPlatform(), 403);
    }

    /** 주소에서 지역(시도) 파생 — 앞 1토큰. (예: "서울특별시 종로구 …" → "서울특별시") */
    private function regionFromAddress(?string $address): ?string
    {
        if ($address === null || trim($address) === '') {
            return null;
        }

        $parts = preg_split('/\s+/', trim($address));

        return $parts[0] ?? null;
    }

    /** 지역(시도) 필터 옵션 — 주소 접두 매칭용 표준 17개 시도 */
    private const SIDO_OPTIONS = [
        '서울특별시', '부산광역시', '대구광역시', '인천광역시', '광주광역시', '대전광역시', '울산광역시',
        '세종특별자치시', '경기도', '강원특별자치도', '충청북도', '충청남도', '전북특별자치도', '전라남도',
        '경상북도', '경상남도', '제주특별자치도',
    ];

    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $search = trim((string) $request->input('search', ''));
        $digits = preg_replace('/\D/', '', $search); // 사업자번호 검색용 (하이픈 무관)
        $region = trim((string) $request->input('region', ''));
        $type = trim((string) $request->input('type', ''));

        if (! in_array($region, self::SIDO_OPTIONS, true)) {
            $region = '';
        }
        if (! in_array($type, Hospital::TYPES, true)) {
            $type = '';
        }

        $hospitals = Hospital::query()
            ->when($search !== '', function ($q) use ($search, $digits) {
                $q->where(function ($q) use ($search, $digits) {
                    $q->where('hospital_name', 'like', "%{$search}%")
                        ->orWhere('hospital_code', 'like', "%{$search}%");
                    if ($digits !== '') {
                        // 사업자번호는 숫자만 저장 — 입력 하이픈을 제거하고 매칭 (과거 이력 포함)
                        $q->orWhere('business_registration_number', 'like', "%{$digits}%")
                            ->orWhereHas('numberHistories', fn ($h) => $h->where('business_registration_number', 'like', "%{$digits}%"));
                    }
                });
            })
            // 과거 번호로 매칭된 경우 목록에 '과거 번호' 배지로 표시하기 위해 매칭 이력만 로드
            ->when($digits !== '', fn ($q) => $q->with(['numberHistories' => fn ($h) => $h
                ->where('is_current', false)
                ->where('business_registration_number', 'like', "%{$digits}%")]))
            ->when($region !== '', fn ($q) => $q->where('address', 'like', "{$region}%"))
            ->when($type !== '', fn ($q) => $q->where('hospital_type', $type))
            ->orderBy('hospital_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Hospital $h) => [
                'id' => $h->id,
                'region' => $this->regionFromAddress($h->address),
                'hospital_type' => $h->hospital_type,
                'hospital_name' => $h->hospital_name,
                'phone' => $h->phone,
                'business_registration_number' => $h->business_registration_number,
                'address' => $h->address,
                'specialty' => $h->specialty,
                'opened_on' => $h->opened_on?->format('Y-m-d'),
                'status' => $h->status,
                'matched_old_numbers' => $digits !== ''
                    ? $h->numberHistories->pluck('business_registration_number')->values()->all()
                    : [],
            ]);

        return Inertia::render('Platform/Hospitals/Index', [
            'hospitals' => $hospitals,
            'filters' => ['search' => $search, 'region' => $region, 'type' => $type],
            'regionOptions' => self::SIDO_OPTIONS,
            'typeOptions' => Hospital::TYPES,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Hospitals/Create', [
            'types' => Hospital::TYPES,
        ]);
    }

    public function store(StoreHospitalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $hospital = Hospital::create($data);
        $hospital->seedBusinessNumberHistory($request->user()->id);

        return redirect()
            ->route('platform.hospitals.show', $hospital)
            ->with('success', '병의원을 등록했습니다.');
    }

    /** 사업자등록번호 변경 — 과거 번호 마감 + 새 번호 현재로, 이력 기록 */
    public function changeBusinessNumber(ChangeBusinessNumberRequest $request, Hospital $hospital): RedirectResponse
    {
        $data = $request->validated();

        $hospital->changeBusinessNumber($data['new_business_registration_number'], [
            'valid_from' => $data['valid_from'] ?? null,
            'previous_valid_to' => $data['previous_valid_to'] ?? null,
            'reason' => $data['reason'] ?? null,
            'note' => $data['note'] ?? null,
        ], $request->user()->id);

        return redirect()
            ->route('platform.hospitals.show', $hospital)
            ->with('success', '사업자등록번호를 변경하고 이력에 기록했습니다.');
    }

    public function show(Request $request, Hospital $hospital): Response
    {
        $this->ensureSuperAdmin($request);

        $hospital->loadMissing([
            'creator:id,name,email',
            'updater:id,name,email',
            'specialties' => fn ($q) => $q->orderBy('dept_code'),
            'equipments' => fn ($q) => $q->orderBy('equipment_code'),
            'facility',
            'hours',
            'transports',
            'nursingGrades',
            'mealSurcharges',
            'specialTreatments',
            'specializedFields',
            'otherStaff',
        ]);

        return Inertia::render('Platform/Hospitals/Show', [
            'hospital' => $hospital,
            'numberHistories' => $this->numberHistoryPayload($hospital),
        ]);
    }

    /** 사업자번호 이력 페이로드 (현재 → 과거 순) */
    private function numberHistoryPayload(Hospital $hospital): array
    {
        return $hospital->numberHistories()
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'business_registration_number' => $h->business_registration_number,
                'is_current' => $h->is_current,
                'valid_from' => $h->valid_from?->toDateString(),
                'valid_to' => $h->valid_to?->toDateString(),
                'reason' => $h->reason,
                'note' => $h->note,
            ])
            ->all();
    }

    public function edit(Request $request, Hospital $hospital): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Hospitals/Edit', [
            'hospital' => $hospital,
            'types' => Hospital::TYPES,
        ]);
    }

    public function update(UpdateHospitalRequest $request, Hospital $hospital): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $hospital->update($data);

        return redirect()
            ->route('platform.hospitals.show', $hospital)
            ->with('success', '병의원 정보를 수정했습니다.');
    }

    public function destroy(Request $request, Hospital $hospital): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $hospital->delete();

        return redirect()
            ->route('platform.hospitals.index')
            ->with('success', '병의원을 삭제했습니다.');
    }
}
