<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ChangeBusinessNumberRequest;
use App\Http\Requests\Platform\StorePharmacyRequest;
use App\Http\Requests\Platform\UpdatePharmacyRequest;
use App\Models\Pharmacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 약국(공유 마스터) 전역 CRUD. (GAP-10 MT-6, super_admin)
 */
class PharmacyController extends Controller
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
        $region = trim((string) $request->input('region', ''));

        if (! in_array($region, self::SIDO_OPTIONS, true)) {
            $region = '';
        }

        $pharmacies = Pharmacy::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('pharmacy_name', 'like', "%{$search}%")
                        ->orWhere('pharmacy_code', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%")
                        // 과거(폐업) 사업자번호로도 검색되도록 이력까지 매칭
                        ->orWhereHas('numberHistories', fn ($h) => $h->where('business_registration_number', 'like', "%{$search}%"));
                });
            })
            // 과거 번호로 매칭된 경우 목록에 '과거 번호' 배지로 표시하기 위해 매칭 이력만 로드
            ->when($search !== '', fn ($q) => $q->with(['numberHistories' => fn ($h) => $h
                ->where('is_current', false)
                ->where('business_registration_number', 'like', "%{$search}%")]))
            ->when($region !== '', fn ($q) => $q->where('address', 'like', "{$region}%"))
            ->orderBy('pharmacy_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Pharmacy $p) => [
                'id' => $p->id,
                'region' => $this->regionFromAddress($p->address),
                'pharmacy_name' => $p->pharmacy_name,
                'pharmacy_code' => $p->pharmacy_code,
                'representative_name' => $p->representative_name,
                'phone' => $p->landline_phone ?: $p->mobile_phone,
                'business_registration_number' => $p->business_registration_number,
                'address' => $p->address,
                'status' => $p->status,
                'matched_old_numbers' => $search !== ''
                    ? $p->numberHistories->pluck('business_registration_number')->values()->all()
                    : [],
            ]);

        return Inertia::render('Platform/Pharmacies/Index', [
            'pharmacies' => $pharmacies,
            'filters' => ['search' => $search, 'region' => $region],
            'regionOptions' => self::SIDO_OPTIONS,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Pharmacies/Create');
    }

    public function store(StorePharmacyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $pharmacy = Pharmacy::create($data);
        $pharmacy->seedBusinessNumberHistory($request->user()->id);

        return redirect()
            ->route('platform.pharmacies.show', $pharmacy)
            ->with('success', '약국을 등록했습니다.');
    }

    /** 사업자등록번호 변경 — 과거 번호 마감 + 새 번호 현재로, 이력 기록 */
    public function changeBusinessNumber(ChangeBusinessNumberRequest $request, Pharmacy $pharmacy): RedirectResponse
    {
        $data = $request->validated();

        $pharmacy->changeBusinessNumber($data['new_business_registration_number'], [
            'valid_from' => $data['valid_from'] ?? null,
            'previous_valid_to' => $data['previous_valid_to'] ?? null,
            'reason' => $data['reason'] ?? null,
            'note' => $data['note'] ?? null,
        ], $request->user()->id);

        return redirect()
            ->route('platform.pharmacies.show', $pharmacy)
            ->with('success', '사업자등록번호를 변경하고 이력에 기록했습니다.');
    }

    public function show(Request $request, Pharmacy $pharmacy): Response
    {
        $this->ensureSuperAdmin($request);

        $pharmacy->loadMissing(['creator:id,name,email', 'updater:id,name,email']);

        return Inertia::render('Platform/Pharmacies/Show', [
            'pharmacy' => $pharmacy,
            'numberHistories' => $this->numberHistoryPayload($pharmacy),
        ]);
    }

    /** 사업자번호 이력 페이로드 (현재 → 과거 순) */
    private function numberHistoryPayload(Pharmacy $pharmacy): array
    {
        return $pharmacy->numberHistories()
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

    public function edit(Request $request, Pharmacy $pharmacy): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Pharmacies/Edit', [
            'pharmacy' => $pharmacy,
        ]);
    }

    public function update(UpdatePharmacyRequest $request, Pharmacy $pharmacy): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $pharmacy->update($data);

        return redirect()
            ->route('platform.pharmacies.show', $pharmacy)
            ->with('success', '약국 정보를 수정했습니다.');
    }

    public function destroy(Request $request, Pharmacy $pharmacy): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $pharmacy->delete();

        return redirect()
            ->route('platform.pharmacies.index')
            ->with('success', '약국을 삭제했습니다.');
    }
}
