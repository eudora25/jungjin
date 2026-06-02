<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHospitalPublicDataImportRequest;
use App\Jobs\ProcessHospitalPublicDataImport;
use App\Models\Hospital;
use App\Models\HospitalPublicDataImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 병의원 심평원(HIRA) Excel 보강 (super_admin 전용, 큐 처리).
 * 설계: docs/modules/client/HOSPITAL_PUBLIC_DATA.md
 */
class HospitalPublicDataController extends Controller
{
    private const STORAGE_DIR = 'imports/hospital-public-data';

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatform(), 403);

        $imports = HospitalPublicDataImport::query()
            ->with('creator:id,name')
            ->latest()
            ->limit(30)
            ->get();

        return Inertia::render('Platform/Hospitals/PublicData', [
            'imports' => $imports,
            'kinds' => HospitalPublicDataImport::KINDS,
            'stats' => [
                'hospitals' => Hospital::count(),
                'matched' => Hospital::whereNotNull('ykiho')->count(),
            ],
        ]);
    }

    public function store(StoreHospitalPublicDataImportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $upload = $request->file('file');

        $stored = $upload->storeAs(self::STORAGE_DIR, Str::uuid().'.xlsx', 'local');

        $import = HospitalPublicDataImport::create([
            'created_by' => $request->user()->id,
            'kind' => $data['kind'],
            'original_filename' => $upload->getClientOriginalName(),
            'stored_path' => $stored,
            'status' => HospitalPublicDataImport::STATUS_PENDING,
        ]);

        ProcessHospitalPublicDataImport::dispatch($import->id);

        return redirect()
            ->route('platform.hospitals.public-data.index')
            ->with('success', "[{$import->original_filename}] 업로드 완료 — 백그라운드 적재를 시작했습니다. 잠시 후 새로고침하여 상태를 확인하세요.");
    }
}
