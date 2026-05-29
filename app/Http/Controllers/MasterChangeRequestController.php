<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasterChangeRequestRequest;
use App\Models\MasterChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 제약사(pharma) 의 공유 마스터(약국·병의원) 변경요청 제출/조회. (GAP-10 MT-8)
 */
class MasterChangeRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MasterChangeRequest::class);

        // BelongsToTenant 스코프로 pharma 는 자사 요청만 조회됨
        $requests = MasterChangeRequest::query()
            ->with('reviewer:id,name')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('MasterChangeRequests/Index', [
            'requests' => $requests,
        ]);
    }

    public function store(StoreMasterChangeRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // tenant_id 는 BelongsToTenant 가 현재 테넌트로 자동 주입
        MasterChangeRequest::create([
            'requested_by' => $request->user()->id,
            'target_type' => $data['target_type'],
            'target_id' => $data['target_id'] ?? null,
            'request_type' => $data['request_type'],
            'payload' => $data['payload'],
            'status' => MasterChangeRequest::STATUS_PENDING,
        ]);

        return back()->with('success', '변경요청을 제출했습니다. 운영자 승인 후 반영됩니다.');
    }
}
