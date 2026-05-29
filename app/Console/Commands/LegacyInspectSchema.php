<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyInspectSchema extends Command
{
    protected $signature = 'legacy:inspect-schema
        {--connection=legacy : legacy DB connection name}
        {--output= : output path (default: storage/app/legacy/inspect_*.json)}
        {--tables=* : limit to specific tables (repeatable)}
        {--no-indexes : skip index inspection}';

    protected $description = '레거시 DB(기본: legacy 커넥션)의 테이블/컬럼/인덱스 메타데이터를 덤프합니다.';

    public function handle(): int
    {
        $connectionName = (string) $this->option('connection');
        $tablesFilter = (array) $this->option('tables');
        $skipIndexes = (bool) $this->option('no-indexes');

        try {
            $conn = DB::connection($connectionName);
            $dbName = $conn->getDatabaseName();

            $this->info(sprintf('Inspect legacy schema: connection=%s db=%s', $connectionName, $dbName));

            $tablesQuery = $conn->table('information_schema.tables')
                ->select([
                    'table_name',
                    'table_type',
                    'engine',
                    'table_rows',
                    'create_time',
                    'update_time',
                    'table_collation',
                    'table_comment',
                ])
                ->where('table_schema', $dbName)
                ->orderBy('table_name');

            if (count($tablesFilter) > 0) {
                $tablesQuery->whereIn('table_name', $tablesFilter);
            }

            $tables = $tablesQuery->get();

            $tableNames = $tables->pluck('table_name')->values()->all();

            $columns = $conn->table('information_schema.columns')
                ->select([
                    'table_name',
                    'ordinal_position',
                    'column_name',
                    'column_type',
                    'is_nullable',
                    'column_default',
                    'column_key',
                    'extra',
                    'character_set_name',
                    'collation_name',
                    'column_comment',
                ])
                ->where('table_schema', $dbName)
                ->when(count($tableNames) > 0, fn ($q) => $q->whereIn('table_name', $tableNames))
                ->orderBy('table_name')
                ->orderBy('ordinal_position')
                ->get();

            $indexes = collect();

            if (! $skipIndexes) {
                $indexes = $conn->table('information_schema.statistics')
                    ->select([
                        'table_name',
                        'index_name',
                        'non_unique',
                        'seq_in_index',
                        'column_name',
                        'collation',
                        'sub_part',
                        'nullable',
                        'index_type',
                    ])
                    ->where('table_schema', $dbName)
                    ->when(count($tableNames) > 0, fn ($q) => $q->whereIn('table_name', $tableNames))
                    ->orderBy('table_name')
                    ->orderBy('index_name')
                    ->orderBy('seq_in_index')
                    ->get();
            }

            $payload = [
                'inspected_at' => now()->toDateTimeString(),
                'connection' => $connectionName,
                'database' => $dbName,
                'tables' => $tables,
                'columns' => $columns,
                'indexes' => $skipIndexes ? [] : $indexes,
            ];

            $defaultOutDir = storage_path('app/legacy');
            $defaultOutPath = $defaultOutDir.'/inspect_'.$dbName.'_'.now()->format('Ymd_His').'.json';
            $outPath = (string) ($this->option('output') ?: $defaultOutPath);

            File::ensureDirectoryExists(dirname($outPath));
            File::put($outPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->newLine();
            $this->info('Wrote: '.$outPath);
            $this->line(sprintf(
                'Tables: %d, Columns: %d%s',
                $tables->count(),
                $columns->count(),
                $skipIndexes ? '' : sprintf(', Index rows: %d', $indexes->count())
            ));

            return self::SUCCESS;
        } catch (QueryException $e) {
            $this->error('Legacy DB 연결에 실패했습니다.');
            $this->line('권장 실행: Sail 컨테이너 안에서 실행');
            $this->line('  - ./vendor/bin/sail artisan legacy:inspect-schema');
            $this->line('또는, 로컬에서 실행 시 LEGACY_DB_HOST/PORT 를 호스트 기준으로 설정하세요.');
            $this->line(sprintf('  - 현재: LEGACY_DB_HOST=%s', env('LEGACY_DB_HOST', '(not set)')));
            $this->newLine();
            $this->line($e->getMessage());

            return self::FAILURE;
        }
    }
}
