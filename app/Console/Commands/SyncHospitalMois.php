<?php

namespace App\Console\Commands;

use App\Jobs\SyncHospitalMoisJob;
use App\Models\HospitalMoisSync;
use App\Services\Clients\HospitalMoisSyncService;
use Illuminate\Console\Command;

class SyncHospitalMois extends Command
{
    protected $signature = 'hospitals:sync-mois
        {--since= : 증분 시작 시점 override (YYYYMMDD, 미지정 시 커서/lookback)}
        {--svc=* : 대상 업종키 (clinics/hospitals/affiliated, 미지정 시 전체)}
        {--dry-run : 분류 카운트만 집계하고 DB 쓰기 없음}
        {--queue : 인라인 대신 큐로 디스패치}';

    protected $description = '행안부(MOIS) 공공데이터 API 로 병의원 변경분을 증분 동기화한다';

    public function handle(HospitalMoisSyncService $service): int
    {
        $options = array_filter([
            'trigger' => HospitalMoisSync::TRIGGER_MANUAL,
            'services' => $this->option('svc') ?: null,
            'since' => $this->option('since') ?: null,
            'dry_run' => $this->option('dry-run') ? true : null,
        ], fn ($v) => $v !== null);

        if ($this->option('queue')) {
            SyncHospitalMoisJob::dispatch($options);
            $this->info('MOIS 동기화 작업을 큐에 등록했습니다.');

            return self::SUCCESS;
        }

        $sync = $service->syncAll($options);

        $this->renderReport($sync);

        return $sync->status === HospitalMoisSync::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }

    private function renderReport(HospitalMoisSync $sync): void
    {
        $this->line("동기화 #{$sync->id} — 상태: {$sync->status}");

        $rows = [];
        foreach (($sync->report ?? []) as $svc => $r) {
            if (($r['failed'] ?? false) === true) {
                $rows[] = [$svc, '실패', '-', '-', '-', '-', $r['error'] ?? ''];

                continue;
            }
            $rows[] = [
                $svc,
                $r['fetched'] ?? 0,
                $r['inserted'] ?? 0,
                $r['updated'] ?? 0,
                $r['closed'] ?? 0,
                $r['skipped'] ?? 0,
                $r['failed'] ?? 0,
            ];
        }

        if (! empty($rows)) {
            $this->table(['업종', 'fetched', 'inserted', 'updated', 'closed', 'skipped', 'failed'], $rows);
        }
    }
}
