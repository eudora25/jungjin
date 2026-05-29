<?php

namespace App\Services;

use App\Models\MasterChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 공유 마스터(약국·병의원) 변경요청 승인/반려 처리. (GAP-10 MT-8)
 * 승인 시 payload 를 실제 Pharmacy/Hospital 에 create/update 반영.
 */
class MasterChangeRequestService
{
    /** 승인 → 실제 마스터에 반영 */
    public function approve(MasterChangeRequest $request, User $reviewer): MasterChangeRequest
    {
        abort_unless($request->isPending(), 422, '이미 처리된 요청입니다.');

        return DB::transaction(function () use ($request, $reviewer) {
            $modelClass = $request->targetModelClass();
            $fillable = (new $modelClass)->getFillable();
            $data = collect($request->payload)->only($fillable)->toArray();
            $data['updated_by'] = $reviewer->id;

            if ($request->request_type === MasterChangeRequest::TYPE_CREATE) {
                $data['created_by'] = $reviewer->id;
                $model = $modelClass::create($data);
            } else {
                $model = $modelClass::findOrFail($request->target_id);
                $model->update($data);
            }

            $request->update([
                'status' => MasterChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'applied_target_id' => $model->id,
            ]);

            return $request;
        });
    }

    /** 반려 */
    public function reject(MasterChangeRequest $request, User $reviewer, ?string $note): MasterChangeRequest
    {
        abort_unless($request->isPending(), 422, '이미 처리된 요청입니다.');

        $request->update([
            'status' => MasterChangeRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $request;
    }
}
