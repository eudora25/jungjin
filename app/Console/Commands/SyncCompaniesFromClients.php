<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Hospital;
use App\Models\Pharmacy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCompaniesFromClients extends Command
{
    protected $signature = 'clients:sync-companies
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}
        {--only= : pharmacy|hospital 중 하나만 동기화}';

    protected $description = '약국/병원을 companies로 통합(생성/연결)하여 실적(company_id)에서 사용 가능하게 만듭니다.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('only');

        $doPharmacy = $only === null || $only === '' || $only === 'pharmacy';
        $doHospital = $only === null || $only === '' || $only === 'hospital';

        if (! $doPharmacy && ! $doHospital) {
            $this->error('--only 는 pharmacy|hospital 중 하나여야 합니다.');

            return self::FAILURE;
        }

        $created = 0;
        $linked = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            if ($doPharmacy) {
                $this->info('Sync pharmacies → companies');
                [$c, $l, $s] = $this->syncPharmacies($dryRun);
                $created += $c;
                $linked += $l;
                $skipped += $s;
            }

            if ($doHospital) {
                $this->info('Sync hospitals → companies');
                [$c, $l, $s] = $this->syncHospitals($dryRun);
                $created += $c;
                $linked += $l;
                $skipped += $s;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->newLine();
        $this->table(
            ['created_companies', 'linked_clients', 'skipped'],
            [[$created, $linked, $skipped]],
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int,2:int} created, linked, skipped
     */
    private function syncPharmacies(bool $dryRun): array
    {
        $created = 0;
        $linked = 0;
        $skipped = 0;

        $pharmacies = Pharmacy::query()->orderBy('id')->get();
        $reservedCompanyIds = [];

        foreach ($pharmacies as $p) {
            if ($p->company_id) {
                $skipped++;

                continue;
            }

            $company = $this->findCompanyForBizNoOrName($p->business_registration_number, $p->pharmacy_name);

            // 1:1 제약(pharmacies.company_id unique) 보호:
            // 이미 다른 약국이 점유한 company는 재사용하지 않고 새 company를 만든다.
            if ($company) {
                $alreadyLinked = Pharmacy::query()->where('company_id', $company->id)->exists();
                $reserved = isset($reservedCompanyIds[$company->id]);

                if ($alreadyLinked || $reserved) {
                    $company = null;
                }
            }

            if (! $company) {
                $company = new Company;
                $company->fill([
                    'company_name' => $p->pharmacy_name,
                    'business_registration_number' => $p->business_registration_number,
                    'postcode' => $p->postcode,
                    'business_address' => $p->address,
                    'contact_person_name' => $p->contact_person_name,
                    'landline_phone' => $p->landline_phone,
                    'mobile_phone' => $p->mobile_phone,
                    'email' => $p->email,
                    'remarks' => $p->remarks,
                    'status' => $p->status ?? 'active',
                    'approval_status' => 'approved',
                    'partner_type' => 'pharmacy',
                    'company_group' => $p->pharmacy_code ? 'pharmacy:'.$p->pharmacy_code : 'pharmacy',
                ]);

                if (! $dryRun) {
                    $company->save();
                }
                $created++;
            } else {
                if ($company->partner_type === 'company') {
                    $company->partner_type = 'pharmacy';
                    if (! $dryRun) {
                        $company->save();
                    }
                }
            }

            if (! $dryRun) {
                $p->company_id = $company->id;
                $p->save();
            }
            $reservedCompanyIds[$company->id] = true;
            $linked++;
        }

        return [$created, $linked, $skipped];
    }

    /**
     * @return array{0:int,1:int,2:int} created, linked, skipped
     */
    private function syncHospitals(bool $dryRun): array
    {
        $created = 0;
        $linked = 0;
        $skipped = 0;

        $hospitals = Hospital::query()->orderBy('id')->get();
        $reservedCompanyIds = [];

        foreach ($hospitals as $h) {
            if ($h->company_id) {
                $skipped++;

                continue;
            }

            $company = $this->findCompanyForBizNoOrName($h->business_registration_number, $h->hospital_name);

            if ($company) {
                $alreadyLinked = Hospital::query()->where('company_id', $company->id)->exists();
                $reserved = isset($reservedCompanyIds[$company->id]);
                if ($alreadyLinked || $reserved) {
                    $company = null;
                }
            }

            if (! $company) {
                $company = new Company;
                $company->fill([
                    'company_name' => $h->hospital_name,
                    'business_registration_number' => $h->business_registration_number,
                    'postcode' => $h->postcode,
                    'business_address' => $h->address,
                    'contact_person_name' => $h->contact_person_name,
                    'landline_phone' => $h->phone,
                    'mobile_phone' => $h->contact_phone,
                    'email' => $h->email,
                    'remarks' => $h->remarks,
                    'status' => $h->status ?? 'active',
                    'approval_status' => 'approved',
                    'partner_type' => 'hospital',
                    'company_group' => $h->hospital_code ? 'hospital:'.$h->hospital_code : 'hospital',
                ]);

                if (! $dryRun) {
                    $company->save();
                }
                $created++;
            } else {
                if ($company->partner_type === 'company') {
                    $company->partner_type = 'hospital';
                    if (! $dryRun) {
                        $company->save();
                    }
                }
            }

            if (! $dryRun) {
                $h->company_id = $company->id;
                $h->save();
            }
            $reservedCompanyIds[$company->id] = true;
            $linked++;
        }

        return [$created, $linked, $skipped];
    }

    private function findCompanyForBizNoOrName(?string $bizNo, string $name): ?Company
    {
        $bizNo = $bizNo ? trim($bizNo) : null;
        $name = trim($name);

        if ($bizNo !== null && $bizNo !== '') {
            $byBiz = Company::query()
                ->where('business_registration_number', $bizNo)
                ->orderByDesc('id')
                ->first();
            if ($byBiz) {
                return $byBiz;
            }
        }

        return Company::query()
            ->where('company_name', $name)
            ->orderByDesc('id')
            ->first();
    }
}
