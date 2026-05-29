<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanySalesAssignmentRequest;
use App\Models\ChangeReason;
use App\Models\Company;
use App\Models\CompanySalesAssignment;
use Illuminate\Http\RedirectResponse;

/**
 * 영업사원-거래처 담당 배정 (GAP-4).
 *
 * 거래처 상세 화면에서 admin이 담당 영업사원을 지정/해제한다.
 * 라우트는 `companies` resource 의 nested action으로 구성.
 */
class CompanySalesAssignmentController extends Controller
{
    public function store(StoreCompanySalesAssignmentRequest $request, Company $company): RedirectResponse
    {
        ChangeReason::with('담당 영업사원 배정', function () use ($request, $company) {
            CompanySalesAssignment::create([
                'company_id' => $company->id,
                'user_id' => $request->integer('user_id'),
                'assigned_at' => now(),
                'assigned_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '담당 영업사원을 배정했습니다.');
    }

    public function destroy(Company $company, CompanySalesAssignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        if ($assignment->company_id !== $company->id) {
            abort(404);
        }

        ChangeReason::with('담당 영업사원 해제', function () use ($assignment) {
            $assignment->delete();
        });

        return back()->with('success', '담당 영업사원 배정을 해제했습니다.');
    }
}
