<?php

namespace App\Console\Commands;

use App\Services\Clients\HiraDetailImportService;
use App\Services\Clients\HiraInstitutionImportService;
use App\Services\Clients\HospitalImportService;
use Illuminate\Console\Command;

/**
 * 병의원 공공데이터 적재 — 인허가 CSV(A) 베이스 + 심평원 Excel(B) 보강.
 * 설계: docs/modules/client/HOSPITAL_PUBLIC_DATA.md
 */
class ImportHospitalPublicData extends Command
{
    protected $signature = 'hospitals:import-public-data
        {--dir= : 샘플 데이터 디렉터리 (기본: docs/data/samples)}
        {--step=all : a|b1|details|all 중 실행할 단계}
        {--only= : details 단계에서 특정 유형만 (쉼표 구분: specialties,equipments…)}';

    protected $description = '병의원 공공데이터 적재 (인허가 CSV + 심평원 Excel 정규화)';

    /** A: 인허가 CSV 파일들 (약국 제외) */
    private const CSV_FILES = ['건강_병원.csv', '건강_의원.csv', '건강_부속의료기관.csv'];

    /** B-1: 심평원 병원정보 */
    private const HIRA_DIR = '전국 병의원 및 약국 현황 2026.3 2';

    private const HIRA_INSTITUTION = '1.병원정보서비스(2026.3.).xlsx';

    /** B-3~12: 상세 유형 => 파일명 */
    private const HIRA_DETAILS = [
        'facilities' => '3.의료기관별상세정보서비스_01_시설정보(2026.3.).xlsx',
        'hours' => '4.의료기관별상세정보서비스_02_세부정보(2026.3.).xlsx',
        'specialties' => '5.의료기관별상세정보서비스_03_진료과목정보(2026.3.).xlsx',
        'transports' => '6.의료기관별상세정보서비스_04_교통정보(2026.3.).xlsx',
        'equipments' => '7.의료기관별상세정보서비스_05_의료장비정보(2026.3.).xlsx',
        'meal_surcharges' => '8.의료기관별상세정보서비스_06_식대가산정보(2026.3.).xlsx',
        'nursing_grades' => '9.의료기관별상세정보서비스_07_간호등급정보(2026.3.).xlsx',
        'special_treatments' => '10.의료기관별상세정보서비스_08_특수진료정보서비스(2026.3.).xlsx',
        'specialized_fields' => '11.의료기관별상세정보서비스_09_전문병원지정분야(2026.3.).xlsx',
        'other_staff' => '12.의료기관별상세정보서비스_10_기타인력정보(2026.3.).xlsx',
    ];

    public function handle(
        HospitalImportService $csv,
        HiraInstitutionImportService $institution,
        HiraDetailImportService $detail,
    ): int {
        $dir = rtrim($this->option('dir') ?: base_path('docs/data/samples'), '/');
        $step = $this->option('step');

        if (! is_dir($dir)) {
            $this->error("디렉터리를 찾을 수 없습니다: {$dir}");

            return self::FAILURE;
        }

        if (in_array($step, ['a', 'all'], true)) {
            $this->importCsv($csv, $dir);
        }

        if (in_array($step, ['b1', 'all'], true)) {
            $this->importInstitution($institution, $dir);
        }

        if (in_array($step, ['details', 'all'], true)) {
            $this->importDetails($detail, $dir);
        }

        $this->newLine();
        $this->info('완료.');

        return self::SUCCESS;
    }

    private function importCsv(HospitalImportService $csv, string $dir): void
    {
        $this->line('▶ A) 인허가 CSV 적재');
        foreach (self::CSV_FILES as $file) {
            $path = "{$dir}/{$file}";
            if (! is_file($path)) {
                $this->warn("  - 건너뜀(없음): {$file}");

                continue;
            }
            $res = $csv->importFile($path);
            if (! ($res['committed'] ?? false)) {
                $this->warn("  - {$file}: 검증 실패로 미적용 (".count($res['errors'] ?? []).' 오류, 행 오류 '.($res['summary']['error'] ?? 0).')');

                continue;
            }
            $this->info("  - {$file}: 적용 {$res['row_count']}행 (생성 {$res['summary']['create']} / 갱신 {$res['summary']['update']})");
        }
    }

    private function importInstitution(HiraInstitutionImportService $institution, string $dir): void
    {
        $this->line('▶ B-1) 심평원 병원정보 → ykiho 매칭');
        $path = "{$dir}/".self::HIRA_DIR.'/'.self::HIRA_INSTITUTION;
        if (! is_file($path)) {
            $this->warn('  - 건너뜀(없음): '.self::HIRA_INSTITUTION);

            return;
        }
        $r = $institution->import($path);
        $this->info(sprintf(
            '  - 총 %d / 매칭 %d / 미매칭 %d (그중 동명 모호 %d) — 매칭률 %.1f%%',
            $r['total'], $r['matched'], $r['unmatched'], $r['ambiguous'], $r['match_rate'],
        ));
        if (! empty($r['unmatched_samples'])) {
            $this->line('  - 미매칭 예시: '.implode(', ', array_map(fn ($s) => $s['name'], array_slice($r['unmatched_samples'], 0, 5))));
        }
    }

    private function importDetails(HiraDetailImportService $detail, string $dir): void
    {
        $this->line('▶ B-3~12) 심평원 상세 정규화 적재');
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        foreach (self::HIRA_DETAILS as $type => $file) {
            if (! empty($only) && ! in_array($type, $only, true)) {
                continue;
            }
            $path = "{$dir}/".self::HIRA_DIR."/{$file}";
            if (! is_file($path)) {
                $this->warn("  - 건너뜀(없음): {$type}");

                continue;
            }
            $r = $detail->import($type, $path);
            $this->info(sprintf(
                '  - %-18s 총 %d / 연결 %d / 스킵 %d / 적재 %d',
                $type, $r['total'], $r['resolved'], $r['skipped_unmatched'], $r['written'],
            ));
        }
    }
}
