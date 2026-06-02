<?php

namespace App\Jobs;

use App\Models\HospitalPublicDataImport;
use App\Services\Clients\HiraDetailImportService;
use App\Services\Clients\HiraInstitutionImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * 업로드된 심평원(HIRA) Excel 한 건을 백그라운드에서 적재한다.
 * institution(B-1) 이면 ykiho 매칭, 그 외는 상세 정규화 적재.
 */
class ProcessHospitalPublicDataImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** 대용량 파싱 대비 타임아웃 1시간 */
    public int $timeout = 3600;

    public function __construct(public int $importId) {}

    public function handle(
        HiraInstitutionImportService $institution,
        HiraDetailImportService $detail,
    ): void {
        $import = HospitalPublicDataImport::find($this->importId);
        if ($import === null) {
            return;
        }

        $import->update([
            'status' => HospitalPublicDataImport::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $absolutePath = Storage::disk('local')->path($import->stored_path);

        try {
            $report = $import->kind === HospitalPublicDataImport::KIND_INSTITUTION
                ? $institution->import($absolutePath)
                : $detail->import($import->kind, $absolutePath);

            $import->update([
                'status' => HospitalPublicDataImport::STATUS_COMPLETED,
                'report' => $report,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $import->update([
                'status' => HospitalPublicDataImport::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            Storage::disk('local')->delete($import->stored_path);
        }
    }
}
