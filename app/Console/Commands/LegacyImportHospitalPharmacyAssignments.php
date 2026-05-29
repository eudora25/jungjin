<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportHospitalPharmacyAssignments extends Command
{
    protected $signature = 'legacy:import-hospital-pharmacy-assignments
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.hospital_pharmacy_assignments → hospital_pharmacy_assignments 이관 (hospital_id+pharmacy_id upsert)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')->table('hospital_pharmacy_assignments')->orderBy('id')->get();
        $this->info(sprintf('Legacy hospital_pharmacy_assignments: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $knownHospitals = DB::table('hospitals')->pluck('id')->flip();
        $knownPharmacies = DB::table('pharmacies')->pluck('id')->flip();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacy as $row) {
            $hid = (int) $row->hospital_id;
            $pid = (int) $row->pharmacy_id;

            if (! isset($knownHospitals[$hid]) || ! isset($knownPharmacies[$pid])) {
                $this->warn(sprintf('  skip: unknown ref (legacy.id=%d, hospital_id=%d, pharmacy_id=%d)', $row->id, $hid, $pid));
                $skipped++;

                continue;
            }

            $attributes = [
                'status' => in_array($row->status, ['active', 'inactive'], true) ? $row->status : 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            $exists = DB::table('hospital_pharmacy_assignments')
                ->where('hospital_id', $hid)
                ->where('pharmacy_id', $pid)
                ->exists();

            if ($exists) {
                if (! $dryRun) {
                    DB::table('hospital_pharmacy_assignments')
                        ->where('hospital_id', $hid)
                        ->where('pharmacy_id', $pid)
                        ->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('hospital_pharmacy_assignments')->insert(array_merge([
                        'hospital_id' => $hid,
                        'pharmacy_id' => $pid,
                    ], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('hospital_pharmacy_assignments')->count()]],
        );

        return self::SUCCESS;
    }
}
