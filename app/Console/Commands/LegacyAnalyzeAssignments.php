<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyAnalyzeAssignments extends Command
{
    protected $signature = 'legacy:analyze-assignments
        {--limit=20 : 샘플 출력 행 수}
        {--explain-client-pharmacy : client_pharmacy_assignments의 client_id가 무엇인지(clients/users/hospitals) 이름까지 조회}';

    protected $description = '레거시 배정/연결 테이블(client_pharmacy_assignments, hospital_*_assignments)의 FK 대상 후보를 분석합니다.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $explainClientPharmacy = (bool) $this->option('explain-client-pharmacy');

        $legacy = DB::connection('legacy');

        $this->info('Analyzing legacy assignments…');

        // Build id sets (small in legacy DB; still keep them as flipped arrays).
        $clientIds = $legacy->table('clients')->pluck('id')->map(fn ($v) => (int) $v)->flip();
        $userIds = $legacy->table('users')->pluck('id')->map(fn ($v) => (int) $v)->flip();
        $hospitalIds = $legacy->table('hospitals')->pluck('id')->map(fn ($v) => (int) $v)->flip();
        $pharmacyIds = $legacy->table('pharmacies')->pluck('id')->map(fn ($v) => (int) $v)->flip();

        $this->line(sprintf('legacy.clients ids: %d', $clientIds->count()));
        $this->line(sprintf('legacy.users ids: %d', $userIds->count()));
        $this->line(sprintf('legacy.hospitals ids: %d', $hospitalIds->count()));
        $this->line(sprintf('legacy.pharmacies ids: %d', $pharmacyIds->count()));
        $this->newLine();

        // --- client_pharmacy_assignments ---
        $cpaExists = $legacy->getSchemaBuilder()->hasTable('client_pharmacy_assignments');
        if ($cpaExists) {
            $rows = $legacy->table('client_pharmacy_assignments')->get();
            $this->info(sprintf('client_pharmacy_assignments: %d rows', $rows->count()));

            $matchClient = 0;
            $matchUser = 0;
            $matchHospital = 0;
            $matchPharmacy = 0;
            $unknownClient = [];
            $unknownPharmacy = [];

            foreach ($rows as $r) {
                $cid = is_numeric($r->client_id) ? (int) $r->client_id : null;
                $pid = is_numeric($r->pharmacy_id) ? (int) $r->pharmacy_id : null;

                if ($cid !== null) {
                    if (isset($clientIds[$cid])) {
                        $matchClient++;
                    }
                    if (isset($userIds[$cid])) {
                        $matchUser++;
                    }
                    if (isset($hospitalIds[$cid])) {
                        $matchHospital++;
                    }
                    if (! isset($clientIds[$cid]) && ! isset($userIds[$cid]) && ! isset($hospitalIds[$cid])) {
                        $unknownClient[] = ['id' => $r->id, 'client_id' => $cid, 'pharmacy_id' => $pid, 'status' => $r->status];
                    }
                }

                if ($pid !== null) {
                    if (isset($pharmacyIds[$pid])) {
                        $matchPharmacy++;
                    } else {
                        $unknownPharmacy[] = ['id' => $r->id, 'client_id' => $cid, 'pharmacy_id' => $pid, 'status' => $r->status];
                    }
                }
            }

            $this->table(
                ['metric', 'count'],
                [
                    ['client_id ∈ legacy.clients.id', $matchClient],
                    ['client_id ∈ legacy.users.id', $matchUser],
                    ['client_id ∈ legacy.hospitals.id', $matchHospital],
                    ['pharmacy_id ∈ legacy.pharmacies.id', $matchPharmacy],
                    ['client_id unknown', count($unknownClient)],
                    ['pharmacy_id unknown', count($unknownPharmacy)],
                ]
            );

            if ($explainClientPharmacy && $rows->count() > 0) {
                $this->newLine();
                $this->info('Explain client_pharmacy_assignments (sample):');

                $sample = $rows->take($limit);
                $explainRows = [];

                foreach ($sample as $r) {
                    $cid = is_numeric($r->client_id) ? (int) $r->client_id : null;
                    $pid = is_numeric($r->pharmacy_id) ? (int) $r->pharmacy_id : null;

                    $clientName = $cid === null
                        ? null
                        : $legacy->table('clients')->where('id', $cid)->value('client_name');
                    $userEmail = $cid === null
                        ? null
                        : $legacy->table('users')->where('id', $cid)->value('email');
                    $hospitalName = $cid === null
                        ? null
                        : $legacy->table('hospitals')->where('id', $cid)->value('hospital_name');
                    $pharmacyName = $pid === null
                        ? null
                        : $legacy->table('pharmacies')->where('id', $pid)->value('pharmacy_name');

                    $explainRows[] = [
                        'id' => (string) $r->id,
                        'client_id' => $cid,
                        'pharmacy_id' => $pid,
                        'status' => (string) $r->status,
                        'clients.client_name' => $clientName,
                        'users.email' => $userEmail,
                        'hospitals.hospital_name' => $hospitalName,
                        'pharmacies.pharmacy_name' => $pharmacyName,
                    ];
                }

                $this->table(array_keys($explainRows[0]), $explainRows);
                $this->newLine();
            }

            if (count($unknownClient) > 0) {
                $this->newLine();
                $this->warn('Sample unknown client_id:');
                $this->table(array_keys($unknownClient[0]), array_slice($unknownClient, 0, $limit));
            }

            if (count($unknownPharmacy) > 0) {
                $this->newLine();
                $this->warn('Sample unknown pharmacy_id:');
                $this->table(array_keys($unknownPharmacy[0]), array_slice($unknownPharmacy, 0, $limit));
            }

            $this->newLine();
        } else {
            $this->warn('client_pharmacy_assignments table not found.');
            $this->newLine();
        }

        // --- hospital_pharmacy_assignments ---
        $hpaExists = $legacy->getSchemaBuilder()->hasTable('hospital_pharmacy_assignments');
        if ($hpaExists) {
            $rows = $legacy->table('hospital_pharmacy_assignments')->get();
            $this->info(sprintf('hospital_pharmacy_assignments: %d rows', $rows->count()));

            $matchHospital = 0;
            $matchPharmacy = 0;
            $unknown = [];

            foreach ($rows as $r) {
                $hid = is_numeric($r->hospital_id) ? (int) $r->hospital_id : null;
                $pid = is_numeric($r->pharmacy_id) ? (int) $r->pharmacy_id : null;

                if ($hid !== null && isset($hospitalIds[$hid])) {
                    $matchHospital++;
                }
                if ($pid !== null && isset($pharmacyIds[$pid])) {
                    $matchPharmacy++;
                }
                if (($hid !== null && ! isset($hospitalIds[$hid])) || ($pid !== null && ! isset($pharmacyIds[$pid]))) {
                    $unknown[] = ['id' => $r->id, 'hospital_id' => $hid, 'pharmacy_id' => $pid, 'status' => $r->status];
                }
            }

            $this->table(
                ['metric', 'count'],
                [
                    ['hospital_id ∈ legacy.hospitals.id', $matchHospital],
                    ['pharmacy_id ∈ legacy.pharmacies.id', $matchPharmacy],
                    ['unknown refs', count($unknown)],
                ]
            );

            if (count($unknown) > 0) {
                $this->newLine();
                $this->warn('Sample unknown refs (hospital_pharmacy_assignments):');
                $this->table(array_keys($unknown[0]), array_slice($unknown, 0, $limit));
            }

            $this->newLine();
        } else {
            $this->warn('hospital_pharmacy_assignments table not found.');
            $this->newLine();
        }

        // --- hospital_company_assignments ---
        $hcaExists = $legacy->getSchemaBuilder()->hasTable('hospital_company_assignments');
        if ($hcaExists) {
            $companyIds = $legacy->table('companies')->pluck('id')->map(fn ($v) => (int) $v)->flip();

            $rows = $legacy->table('hospital_company_assignments')->get();
            $this->info(sprintf('hospital_company_assignments: %d rows', $rows->count()));

            $matchHospital = 0;
            $matchCompany = 0;
            $unknown = [];

            foreach ($rows as $r) {
                $hid = is_numeric($r->hospital_id) ? (int) $r->hospital_id : null;
                $coid = is_numeric($r->company_id) ? (int) $r->company_id : null;

                if ($hid !== null && isset($hospitalIds[$hid])) {
                    $matchHospital++;
                }
                if ($coid !== null && isset($companyIds[$coid])) {
                    $matchCompany++;
                }
                if (($hid !== null && ! isset($hospitalIds[$hid])) || ($coid !== null && ! isset($companyIds[$coid]))) {
                    $unknown[] = [
                        'id' => $r->id,
                        'hospital_id' => $hid,
                        'company_id' => $coid,
                        'commission_grade' => $r->commission_grade,
                    ];
                }
            }

            $this->table(
                ['metric', 'count'],
                [
                    ['hospital_id ∈ legacy.hospitals.id', $matchHospital],
                    ['company_id ∈ legacy.companies.id', $matchCompany],
                    ['unknown refs', count($unknown)],
                ]
            );

            if (count($unknown) > 0) {
                $this->newLine();
                $this->warn('Sample unknown refs (hospital_company_assignments):');
                $this->table(array_keys($unknown[0]), array_slice($unknown, 0, $limit));
            }
        } else {
            $this->warn('hospital_company_assignments table not found.');
        }

        return self::SUCCESS;
    }
}
