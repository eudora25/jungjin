<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 병의원 조회 (공유 마스터). (GAP-10 MT-6, super_admin)
 */
class HospitalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

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
}
