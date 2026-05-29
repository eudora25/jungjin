<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePharmacyRequest;
use App\Http\Requests\UpdatePharmacyRequest;
use App\Models\Pharmacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Pharmacy::class);

        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        $pharmacies = Pharmacy::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('pharmacy_name', 'like', "%{$search}%")
                        ->orWhere('pharmacy_code', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%")
                        ->orWhere('contact_person_name', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clients/Pharmacies/Index', [
            'pharmacies' => $pharmacies,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'can' => [
                'create' => $request->user()->can('create', Pharmacy::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Pharmacy::class);

        return Inertia::render('Clients/Pharmacies/Create');
    }

    public function store(StorePharmacyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $pharmacy = Pharmacy::create($data);

        return redirect()
            ->route('pharmacies.show', $pharmacy)
            ->with('success', '약국을 등록했습니다.');
    }

    public function show(Request $request, Pharmacy $pharmacy): Response
    {
        $this->authorize('view', $pharmacy);

        $pharmacy->loadMissing(['creator:id,name,email', 'updater:id,name,email', 'company:id,company_name']);

        return Inertia::render('Clients/Pharmacies/Show', [
            'pharmacy' => $pharmacy,
            'can' => [
                'update' => $request->user()->can('update', $pharmacy),
                'delete' => $request->user()->can('delete', $pharmacy),
            ],
        ]);
    }

    public function edit(Pharmacy $pharmacy): Response
    {
        $this->authorize('update', $pharmacy);

        return Inertia::render('Clients/Pharmacies/Edit', [
            'pharmacy' => $pharmacy,
        ]);
    }

    public function update(UpdatePharmacyRequest $request, Pharmacy $pharmacy): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $pharmacy->update($data);

        return redirect()
            ->route('pharmacies.show', $pharmacy)
            ->with('success', '약국 정보를 수정했습니다.');
    }

    public function destroy(Pharmacy $pharmacy): RedirectResponse
    {
        $this->authorize('delete', $pharmacy);

        $pharmacy->delete();

        return redirect()
            ->route('pharmacies.index')
            ->with('success', '약국을 삭제했습니다.');
    }
}
