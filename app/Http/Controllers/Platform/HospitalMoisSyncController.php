<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\SyncHospitalMoisJob;
use App\Models\HospitalMoisCursor;
use App\Models\HospitalMoisSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 병의원 행안부(MOIS) API 증분 동기화 이력·수동 트리거 (platform 전용).
 * 설계: docs/modules/client/HOSPITAL_LOCALDATA_API_SYNC.md §4-6
 */
class HospitalMoisSyncController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatform(), 403);

        $cursors = HospitalMoisCursor::pluck('last_synced_at', 'api_id');

        $syncs = HospitalMoisSync::query()
            ->with('creator:id,name')
            ->latest('id')
            ->limit(30)
            ->get();

        $services = collect(config('mois.services', []))
            ->map(function ($svc, $key) use ($cursors, $syncs) {
                // 마지막 "실행": 해당 업종을 포함(report 에 키 존재)한 가장 최근 동기화
                $lastRun = $syncs->first(fn ($s) => is_array($s->report) && array_key_exists($key, $s->report));

                return [
                    'key' => $key,
                    'id' => $svc['id'],
                    'label' => $svc['label'] ?? $key,
                    'last_synced_at' => $cursors[$svc['id']] ?? null, // 최신 데이터 시점(게시 기준 워터마크)
                    'last_run_at' => $lastRun?->finished_at, // 마지막 실행 완료 시각
                    'last_run_status' => $lastRun?->status,
                ];
            })
            ->values();

        return Inertia::render('Platform/Hospitals/MoisSync', [
            'syncs' => $syncs,
            'services' => $services,
            'enabled' => (bool) config('mois.enabled'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isPlatform(), 403);

        $validated = $request->validate([
            'services' => ['nullable', 'array'],
            'services.*' => ['string', Rule::in(array_keys(config('mois.services', [])))],
            'dry_run' => ['boolean'],
        ]);

        $serviceKeys = $validated['services'] ?? array_keys(config('mois.services', []));
        $dryRun = ($validated['dry_run'] ?? false) === true;

        // 진행상태를 폴링할 수 있도록 이력행(pending)을 먼저 만들고, 그 id 를 잡에 넘긴다.
        $sync = HospitalMoisSync::create([
            'created_by' => $request->user()->id,
            'trigger' => HospitalMoisSync::TRIGGER_MANUAL,
            'params' => array_filter([
                'services' => $serviceKeys,
                'dry_run' => $dryRun ?: null,
            ]),
            'status' => HospitalMoisSync::STATUS_PENDING,
        ]);

        SyncHospitalMoisJob::dispatch(array_filter([
            'trigger' => HospitalMoisSync::TRIGGER_MANUAL,
            'user_id' => $request->user()->id,
            'services' => $validated['services'] ?? null,
            'dry_run' => $dryRun ?: null,
            'sync_id' => $sync->id,
        ], fn ($v) => $v !== null));

        if ($request->wantsJson()) {
            return response()->json(['id' => $sync->id, 'status' => $sync->status]);
        }

        return redirect()
            ->route('platform.hospitals.mois-sync.index')
            ->with('success', 'MOIS 동기화를 시작했습니다 — 잠시 후 새로고침하여 결과를 확인하세요.');
    }

    /**
     * 진행상태 폴링용 — 모달이 동기화 1건의 완료/실패를 감지해 닫을 수 있게 한다.
     */
    public function status(Request $request, HospitalMoisSync $sync): JsonResponse
    {
        abort_unless($request->user()->isPlatform(), 403);

        return response()->json([
            'id' => $sync->id,
            'status' => $sync->status,
            'report' => $sync->report,
            'error' => $sync->error,
            'finished_at' => $sync->finished_at,
        ]);
    }
}
