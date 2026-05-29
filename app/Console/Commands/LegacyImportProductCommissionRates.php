<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportProductCommissionRates extends Command
{
    protected $signature = 'legacy:import-product-commissions
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.product_commission_rates → product_commission_rates 이관 (id upsert).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')
            ->table('product_commission_rates')
            ->orderBy('id')
            ->get();

        $this->info(sprintf('Legacy commission rates: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $validProductIds = DB::table('products')->pluck('id')->flip();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacy as $row) {
            if (! isset($validProductIds[$row->product_id])) {
                $this->warn(sprintf('  skip: orphan product_id=%d (legacy.id=%d)', $row->product_id, $row->id));
                $skipped++;

                continue;
            }

            $attributes = [
                'product_id' => $row->product_id,
                'base_month' => $row->base_month,
                'commission_rate_a' => $row->commission_rate_a,
                'commission_rate_b' => $row->commission_rate_b,
                'commission_rate_c' => $row->commission_rate_c,
                'commission_rate_d' => $row->commission_rate_d,
                'commission_rate_e' => $row->commission_rate_e,
                'effective_from' => $row->effective_from,
                'effective_to' => $row->effective_to,
                'status' => in_array($row->status, ['active', 'inactive'], true) ? $row->status : 'active',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at ?? $row->created_at,
            ];

            $exists = DB::table('product_commission_rates')->where('id', $row->id)->exists();

            if ($exists) {
                if (! $dryRun) {
                    DB::table('product_commission_rates')->where('id', $row->id)->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('product_commission_rates')->insert(array_merge(['id' => $row->id], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('product_commission_rates')->count()]],
        );

        return self::SUCCESS;
    }
}
