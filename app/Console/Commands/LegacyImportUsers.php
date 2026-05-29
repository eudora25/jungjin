<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyImportUsers extends Command
{
    protected $signature = 'legacy:import-users
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.users → users 로 계정 이관 (email upsert). 기존 계정은 유지';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')
            ->table('users')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get();

        $this->info(sprintf('Legacy users: %d 건 발견%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacy as $row) {
            $email = trim((string) $row->email);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn(sprintf('  skip: invalid email (legacy.id=%d, email=%s)', $row->id, $email));
                $skipped++;

                continue;
            }

            $attributes = [
                'name' => Str::before($email, '@'),
                'password' => $row->encrypted_password,
                'role' => $row->is_super_admin ? 'pharma' : 'cso',
                'last_sign_in_at' => $row->last_sign_in_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            $exists = DB::table('users')->where('email', $email)->exists();

            if ($exists) {
                if ($dryRun) {
                    $this->line(sprintf('  [dry] update: %s', $email));
                } else {
                    DB::table('users')->where('email', $email)->update($attributes);
                }
                $updated++;
            } else {
                if ($dryRun) {
                    $this->line(sprintf('  [dry] create: %s (%s)', $email, $attributes['role']));
                } else {
                    DB::table('users')->insert(array_merge(['email' => $email], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_users_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('users')->count()]]
        );

        return self::SUCCESS;
    }
}
