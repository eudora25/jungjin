<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LegacyAnalyzeClients extends Command
{
    protected $signature = 'legacy:analyze-clients
        {--limit=20 : 샘플 출력 행 수}';

    protected $description = '레거시 clients 테이블을 분석해 users 매핑 가능성을 리포트합니다.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $legacy = DB::connection('legacy');

        $clientsCount = (int) $legacy->table('clients')->count();
        $this->info(sprintf('legacy.clients: %d', $clientsCount));

        $clientsWithEmail = (int) $legacy->table('clients')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();
        $this->line(sprintf('  with email: %d', $clientsWithEmail));

        $legacyUsersByEmail = $legacy->table('users')
            ->select(['id', 'email', 'is_super_admin'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->keyBy(fn ($u) => mb_strtolower(trim((string) $u->email)));

        $clientEmails = $legacy->table('clients')
            ->select(['id', 'client_name', 'email'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'client_name' => (string) $c->client_name,
                'email' => mb_strtolower(trim((string) $c->email)),
            ]);

        $matched = 0;
        $unmatched = [];

        foreach ($clientEmails as $c) {
            if ($legacyUsersByEmail->has($c['email'])) {
                $matched++;
            } else {
                $unmatched[] = $c;
            }
        }

        $this->line(sprintf('  email matches legacy.users: %d', $matched));
        $this->line(sprintf('  email NOT matched: %d', count($unmatched)));

        $this->newLine();

        if (count($unmatched) > 0) {
            $this->warn(sprintf('Sample unmatched (%d):', min($limit, count($unmatched))));
            $this->table(
                ['client_id', 'client_name', 'email'],
                Collection::make($unmatched)->take($limit)->values()->all()
            );
        } else {
            $this->info('All client emails matched legacy.users emails.');
        }

        $this->newLine();
        $this->line('다음 액션(권장):');
        $this->line('- client_id가 users.id인지 확인하려면: client_pharmacy_assignments.client_id 값이 legacy.users.id에 존재하는지 교집합을 계산');

        return self::SUCCESS;
    }
}
