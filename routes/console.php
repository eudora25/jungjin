<?php

use App\Jobs\SyncHospitalMoisJob;
use App\Models\HospitalMoisSync;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inspire')->daily();

// 행안부(MOIS) 병의원 증분 동기화 — 일 1회 04:30 (HIRA 와 시각 분리). config 플래그로 활성, 기본 비활성.
Schedule::job(new SyncHospitalMoisJob(['trigger' => HospitalMoisSync::TRIGGER_SCHEDULE]))
    ->dailyAt('04:30')
    ->name('hospitals-mois-sync')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('mois.enabled'));
