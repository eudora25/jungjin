<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 약국 조회 (공유 마스터). (GAP-10 MT-6, super_admin)
 */
class PharmacyController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

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
}
