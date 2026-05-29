<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportHospitalCompanyAssignments extends Command
{
    protected $signature = 'legacy:import-hospital-company-assignments
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.hospital_company_assignments → hospital_company_assignments 이관 (hospital_id+company_id upsert)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')->table('hospital_company_assignments')->orderBy('id')->get();
        $this->info(sprintf('Legacy hospital_company_assignments: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $knownHospitals = DB::table('hospitals')->pluck('id')->flip();
        $knownCompanies = DB::table('companies')->pluck('id')->flip();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacy as $row) {
            $hid = (int) $row->hospital_id;
            $cid = $row->company_id !== null ? (int) $row->company_id : null;

            if ($cid === null || ! isset($knownHospitals[$hid]) || ! isset($knownCompanies[$cid])) {
                $this->warn(sprintf('  skip: unknown ref (legacy.id=%d, hospital_id=%d, company_id=%s)', $row->id, $hid, $cid === null ? 'NULL' : (string) $cid));
                $skipped++;

                continue;
            }

            $grade = in_array($row->commission_grade, ['A', 'B', 'C', 'D', 'E'], true) ? $row->commission_grade : 'E';

            $attributes = [
                'commission_grade' => $grade,
                'status' => 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            $exists = DB::table('hospital_company_assignments')
                ->where('hospital_id', $hid)
                ->where('company_id', $cid)
                ->exists();

            if ($exists) {
                if (! $dryRun) {
                    DB::table('hospital_company_assignments')
                        ->where('hospital_id', $hid)
                        ->where('company_id', $cid)
                        ->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('hospital_company_assignments')->insert(array_merge([
                        'hospital_id' => $hid,
                        'company_id' => $cid,
                    ], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('hospital_company_assignments')->count()]],
        );

        return self::SUCCESS;
    }
}
