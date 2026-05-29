<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportProducts extends Command
{
    protected $signature = 'legacy:import-products
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}';

    protected $description = 'jungjin_legacy.products → products 이관 (id upsert). is_deleted=0만 대상';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy')
            ->table('products')
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->get();

        $this->info(sprintf('Legacy products: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($legacy as $row) {
            if (trim((string) $row->insurance_code) === '' || trim((string) $row->product_code) === '') {
                $this->warn(sprintf('  skip: missing insurance/product code (legacy.id=%d)', $row->id));
                $skipped++;

                continue;
            }

            $attributes = [
                'insurance_code' => $row->insurance_code,
                'product_code' => $row->product_code,
                'product_name' => $row->product_name ?? '(이름 없음)',
                'manufacturer' => $row->manufacturer,
                'category' => $row->category,
                'description' => $row->description,
                'image_path' => $row->image_path,
                'price' => $row->price,
                'status' => in_array($row->status, ['active', 'inactive', 'discontinued'], true) ? $row->status : 'active',
                'remarks' => $row->remarks,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at ?? $row->created_at,
                'deleted_at' => $row->deleted_at,
            ];

            $exists = DB::table('products')->where('id', $row->id)->exists();

            if ($exists) {
                if (! $dryRun) {
                    DB::table('products')->where('id', $row->id)->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('products')->insert(array_merge(['id' => $row->id], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('products')->count()]],
        );

        return self::SUCCESS;
    }
}
