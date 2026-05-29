<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
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

    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $search = trim((string) $request->input('search', ''));

        $hospitals = Hospital::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('hospital_name', 'like', "%{$search}%")
                        ->orWhere('hospital_code', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('hospital_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Hospital $h) => [
                'id' => $h->id,
                'hospital_name' => $h->hospital_name,
                'hospital_code' => $h->hospital_code,
                'hospital_type' => $h->hospital_type,
                'business_registration_number' => $h->business_registration_number,
                'status' => $h->status,
            ]);

        return Inertia::render('Platform/Hospitals/Index', [
            'hospitals' => $hospitals,
            'filters' => ['search' => $search],
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

        return redirect()
            ->route('platform.hospitals.show', $hospital)
            ->with('success', '병의원을 등록했습니다.');
    }

    public function show(Request $request, Hospital $hospital): Response
    {
        $this->ensureSuperAdmin($request);

        $hospital->loadMissing(['creator:id,name,email', 'updater:id,name,email']);

        return Inertia::render('Platform/Hospitals/Show', [
            'hospital' => $hospital,
        ]);
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
