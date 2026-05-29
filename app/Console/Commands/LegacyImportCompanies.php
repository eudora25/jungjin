<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportCompanies extends Command
{
    protected $signature = 'legacy:import-companies
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.companies → companies 이관 (id upsert). is_deleted=0만 대상';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')
            ->table('companies')
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->get();

        $this->info(sprintf('Legacy companies: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $knownUserIds = DB::table('users')->pluck('id')->flip();

        foreach ($legacy as $row) {
            if (trim((string) $row->company_name) === '') {
                $this->warn(sprintf('  skip: empty company_name (legacy.id=%d)', $row->id));
                $skipped++;
                continue;
            }

            $createdBy = is_numeric($row->created_by) && isset($knownUserIds[(int) $row->created_by])
                ? (int) $row->created_by
                : null;

            $attributes = [
                'company_name' => mb_substr((string) $row->company_name, 0, 255),
                'business_registration_number' => $row->business_registration_number,
                'representative_name' => $row->representative_name,
                'company_group' => $row->company_group,
                'default_commission_grade' => $row->default_commission_grade,
                'postcode' => $row->postcode,
                'business_address' => $row->business_address,
                'contact_person_name' => $row->contact_person_name,
                'landline_phone' => $row->landline_phone,
                'mobile_phone' => $row->mobile_phone,
                'mobile_phone_2' => $row->mobile_phone_2,
                'email' => $row->email,
                'receive_email' => $row->receive_email,
                'assigned_pharmacist_contact' => $row->assigned_pharmacist_contact,
                'remarks' => $row->remarks,
                'status' => $row->status ?: 'active',
                'approval_status' => $row->approval_status ?: 'pending',
                'approved_at' => $row->approval_status === 'approved' ? $row->approved_at : null,
                'approved_by' => null,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at ?? $row->created_at,
                'deleted_at' => $row->deleted_at,
            ];

            $exists = DB::table('companies')->where('id', $row->id)->exists();

            if ($exists) {
                if (! $dryRun) {
                    DB::table('companies')->where('id', $row->id)->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('companies')->insert(array_merge(['id' => $row->id], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('companies')->count()]],
        );

        return self::SUCCESS;
    }
}
