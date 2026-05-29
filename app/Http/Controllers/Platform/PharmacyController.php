<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
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
        abort_unless($request->user()->isSuperAdmin(), 403);
    }

    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $search = trim((string) $request->input('search', ''));

        $pharmacies = Pharmacy::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('pharmacy_name', 'like', "%{$search}%")
                        ->orWhere('pharmacy_code', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('pharmacy_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Pharmacy $p) => [
                'id' => $p->id,
                'pharmacy_name' => $p->pharmacy_name,
                'pharmacy_code' => $p->pharmacy_code,
                'business_registration_number' => $p->business_registration_number,
                'representative_name' => $p->representative_name,
                'status' => $p->status,
            ]);

        return Inertia::render('Platform/Pharmacies/Index', [
            'pharmacies' => $pharmacies,
            'filters' => ['search' => $search],
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

        return redirect()
            ->route('platform.pharmacies.show', $pharmacy)
            ->with('success', '약국을 등록했습니다.');
    }

    public function show(Request $request, Pharmacy $pharmacy): Response
    {
        $this->ensureSuperAdmin($request);

        $pharmacy->loadMissing(['creator:id,name,email', 'updater:id,name,email']);

        return Inertia::render('Platform/Pharmacies/Show', [
            'pharmacy' => $pharmacy,
        ]);
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
