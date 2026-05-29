<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportHospitals extends Command
{
    protected $signature = 'legacy:import-hospitals
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.hospitals → hospitals 이관 (id upsert)';

    private const TYPE_MAP = [
        '종합병원' => 'general_hospital',
        '병원' => 'hospital',
        '의원' => 'clinic',
        '치과' => 'dental',
        '한의원' => 'oriental',
        '기타' => 'other',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')
            ->table('hospitals')
            ->orderBy('id')
            ->get();

        $this->info(sprintf('Legacy hospitals: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacy as $row) {
            $name = trim((string) $row->hospital_name);

            if ($name === '') {
                $this->warn(sprintf('  skip: empty hospital_name (legacy.id=%d)', $row->id));
                $skipped++;

                continue;
            }

            $type = $row->hospital_type ? (self::TYPE_MAP[(string) $row->hospital_type] ?? null) : null;

            $remarksParts = [];
            if ($row->remarks) {
                $remarksParts[] = (string) $row->remarks;
            }
            if ($row->settlement_remarks) {
                $remarksParts[] = '[정산비고] '.(string) $row->settlement_remarks;
            }
            if ($row->bcode) {
                $remarksParts[] = '[bcode] '.(string) $row->bcode;
            }

            $attributes = [
                'hospital_code' => $row->hospital_code ? mb_substr((string) $row->hospital_code, 0, 50) : null,
                'hospital_name' => mb_substr($name, 0, 255),
                'business_registration_number' => $row->business_registration_number ? mb_substr((string) $row->business_registration_number, 0, 20) : null,
                'hospital_type' => $type,
                'specialty' => $row->specialty ? mb_substr((string) $row->specialty, 0, 100) : null,
                'representative_name' => $row->director_name ? mb_substr((string) $row->director_name, 0, 100) : null,
                'postcode' => null,
                'address' => $row->address,
                'phone' => $row->phone ? mb_substr((string) $row->phone, 0, 20) : null,
                'contact_person_name' => $row->contact_person ? mb_substr((string) $row->contact_person, 0, 100) : null,
                'contact_phone' => null,
                'email' => $row->email,
                'remarks' => count($remarksParts) > 0 ? implode("\n", $remarksParts) : null,
                'status' => in_array($row->status, ['active', 'inactive'], true) ? $row->status : 'active',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => null,
            ];

            $exists = DB::table('hospitals')->where('id', $row->id)->exists();

            if ($exists) {
                if (! $dryRun) {
                    DB::table('hospitals')->where('id', $row->id)->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('hospitals')->insert(array_merge(['id' => $row->id], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('hospitals')->count()]],
        );

        return self::SUCCESS;
    }
}
