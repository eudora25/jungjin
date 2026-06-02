<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHospitalRequest;
use App\Http\Requests\UpdateHospitalRequest;
use App\Models\Hospital;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HospitalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Hospital::class);

        $search = trim((string) $request->input('search'));
        $digits = preg_replace('/\D/', '', $search); // 사업자번호 검색용 (하이픈 무관)
        $status = $request->input('status');
        $type = $request->input('type');

        $hospitals = Hospital::query()
            ->when($search !== '', function ($q) use ($search, $digits) {
                $q->where(function ($q) use ($search, $digits) {
                    $q->where('hospital_name', 'like', "%{$search}%")
                        ->orWhere('hospital_code', 'like', "%{$search}%")
                        ->orWhere('contact_person_name', 'like', "%{$search}%");
                    if ($digits !== '') {
                        $q->orWhere('business_registration_number', 'like', "%{$digits}%");
                    }
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($type, Hospital::TYPES, true), fn ($q) => $q->where('hospital_type', $type))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clients/Hospitals/Index', [
            'hospitals' => $hospitals,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
            ],
            'types' => Hospital::TYPES,
            'can' => [
                'create' => $request->user()->can('create', Hospital::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Hospital::class);

        return Inertia::render('Clients/Hospitals/Create', [
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
            ->route('hospitals.show', $hospital)
            ->with('success', '병의원을 등록했습니다.');
    }

    public function show(Request $request, Hospital $hospital): Response
    {
        $this->authorize('view', $hospital);

        $hospital->loadMissing(['creator:id,name,email', 'updater:id,name,email', 'company:id,company_name']);

        return Inertia::render('Clients/Hospitals/Show', [
            'hospital' => $hospital,
            'can' => [
                'update' => $request->user()->can('update', $hospital),
                'delete' => $request->user()->can('delete', $hospital),
            ],
        ]);
    }

    public function edit(Hospital $hospital): Response
    {
        $this->authorize('update', $hospital);

        return Inertia::render('Clients/Hospitals/Edit', [
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
            ->route('hospitals.show', $hospital)
            ->with('success', '병의원 정보를 수정했습니다.');
    }

    public function destroy(Hospital $hospital): RedirectResponse
    {
        $this->authorize('delete', $hospital);

        $hospital->delete();

        return redirect()
            ->route('hospitals.index')
            ->with('success', '병의원을 삭제했습니다.');
    }
}
