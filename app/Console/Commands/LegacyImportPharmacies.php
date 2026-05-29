<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportPharmacies extends Command
{
    protected $signature = 'legacy:import-pharmacies
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.pharmacies → pharmacies 이관 (id upsert)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')
            ->table('pharmacies')
            ->orderBy('id')
            ->get();

        $this->info(sprintf('Legacy pharmacies: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $seenBizNos = [];
        $seenCodes = [];

        foreach ($legacy as $row) {
            $name = trim((string) $row->pharmacy_name);

            if ($name === '') {
                $this->warn(sprintf('  skip: empty pharmacy_name (legacy.id=%d)', $row->id));
                $skipped++;

                continue;
            }

            $bizNo = $row->business_registration_number
                ? mb_substr((string) $row->business_registration_number, 0, 20)
                : null;
            $code = $row->pharmacy_code ? mb_substr((string) $row->pharmacy_code, 0, 50) : null;

            if ($bizNo && isset($seenBizNos[$bizNo])) {
                $this->warn(sprintf('  skip: duplicate legacy business_registration_number=%s (legacy.id=%d)', $bizNo, $row->id));
                $skipped++;

                continue;
            }

            if ($code && isset($seenCodes[$code])) {
                $this->warn(sprintf('  skip: duplicate legacy pharmacy_code=%s (legacy.id=%d)', $code, $row->id));
                $skipped++;

                continue;
            }

            $attributes = [
                'pharmacy_code' => $code,
                'pharmacy_name' => mb_substr($name, 0, 255),
                'business_registration_number' => $bizNo,
                'representative_name' => null,
                'postcode' => null,
                'address' => $row->address,
                'landline_phone' => $row->phone ? mb_substr((string) $row->phone, 0, 20) : null,
                'mobile_phone' => null,
                'contact_person_name' => $row->contact_person ? mb_substr((string) $row->contact_person, 0, 100) : null,
                'contact_phone' => null,
                'email' => $row->email,
                'remarks' => $row->remarks,
                'status' => in_array($row->status, ['active', 'inactive'], true) ? $row->status : 'active',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => null,
            ];

            $targetId = DB::table('pharmacies')
                ->where('id', $row->id)
                ->value('id');

            if (! $targetId && $bizNo) {
                $targetId = DB::table('pharmacies')
                    ->where('business_registration_number', $bizNo)
                    ->value('id');
            }

            if (! $targetId && $code) {
                $targetId = DB::table('pharmacies')
                    ->where('pharmacy_code', $code)
                    ->value('id');
            }

            if ($targetId) {
                if (! $dryRun) {
                    DB::table('pharmacies')->where('id', $targetId)->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('pharmacies')->insert(array_merge(['id' => $row->id], $attributes));
                }
                $created++;
            }

            if ($bizNo) {
                $seenBizNos[$bizNo] = true;
            }
            if ($code) {
                $seenCodes[$code] = true;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('pharmacies')->count()]],
        );

        return self::SUCCESS;
    }
}
