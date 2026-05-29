<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\MasterChangeRequest;
use App\Services\MasterChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼(platform) 의 공유 마스터 변경요청 검토 — 목록/승인/반려. (GAP-10 MT-8)
 */
class MasterChangeRequestController extends Controller
{
    public function __construct(private readonly MasterChangeRequestService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatform(), 403);

        $status = $request->input('status', MasterChangeRequest::STATUS_PENDING);

        // platform 은 임퍼서네이션 미진입 시 전역(모든 제약사) 요청 조회
        $requests = MasterChangeRequest::query()
            ->with(['tenant:id,name', 'requester:id,name', 'reviewer:id,name'])
            ->when(
                in_array($status, [MasterChangeRequest::STATUS_PENDING, MasterChangeRequest::STATUS_APPROVED, MasterChangeRequest::STATUS_REJECTED], true),
                fn ($q) => $q->where('status', $status),
            )
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/MasterRequests/Index', [
            'requests' => $requests,
            'filters' => ['status' => $status],
        ]);
    }

    public function approve(Request $request, MasterChangeRequest $masterRequest): RedirectResponse
    {
        $this->authorize('review', $masterRequest);

        $this->service->approve($masterRequest, $request->user());

        return back()->with('success', '변경요청을 승인하여 마스터에 반영했습니다.');
    }

    public function reject(Request $request, MasterChangeRequest $masterRequest): RedirectResponse
    {
        $this->authorize('review', $masterRequest);

        $note = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']])['review_note'] ?? null;

        $this->service->reject($masterRequest, $request->user(), $note);

        return back()->with('success', '변경요청을 반려했습니다.');
    }
}
