<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportClientPharmacyAssignmentsAsHospitalPharmacy extends Command
{
    protected $signature = 'legacy:import-client-pharmacy-as-hospital-pharmacy
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.client_pharmacy_assignments를 hospital_pharmacy_assignments로 흡수(=client_id를 hospital_id로 해석)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')->table('client_pharmacy_assignments')->orderBy('id')->get();
        $this->info(sprintf('Legacy client_pharmacy_assignments: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $knownHospitals = DB::table('hospitals')->pluck('id')->flip();
        $knownPharmacies = DB::table('pharmacies')->pluck('id')->flip();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacy as $row) {
            $hid = is_numeric($row->client_id) ? (int) $row->client_id : null;
            $pid = is_numeric($row->pharmacy_id) ? (int) $row->pharmacy_id : null;

            if ($hid === null || $pid === null) {
                $this->warn(sprintf('  skip: null ref (legacy.id=%s, client_id=%s, pharmacy_id=%s)', (string) $row->id, (string) $row->client_id, (string) $row->pharmacy_id));
                $skipped++;
                continue;
            }

            if (! isset($knownHospitals[$hid]) || ! isset($knownPharmacies[$pid])) {
                $this->warn(sprintf('  skip: unknown ref (legacy.id=%s, hospital_id=%d, pharmacy_id=%d)', (string) $row->id, $hid, $pid));
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

