<?php

namespace App\Jobs;

use App\Services\Clients\HospitalMoisSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 행안부(MOIS) API 병의원 증분 동기화를 백그라운드에서 실행한다.
 * 실제 처리·이력 기록은 HospitalMoisSyncService::syncAll 에 위임한다.
 *
 * @param  array<string,mixed>  $options  user_id/trigger/services/since/dry_run
 */
class SyncHospitalMoisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** 다업종 전수 순회 대비 타임아웃 1시간 */
    public int $timeout = 3600;

    public function __construct(public array $options = []) {}

    public function handle(HospitalMoisSyncService $service): void
    {
        $service->syncAll($this->options);
    }
}
