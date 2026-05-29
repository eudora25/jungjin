<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyImportNotices extends Command
{
    protected $signature = 'legacy:import-notices
        {--dry-run : 실제 DB에 쓰지 않고 결과만 출력}
        {--with-files : notice_files 이관도 함께 수행}';

    protected $description = 'jungjin_legacy.notices → notices (+ 선택적으로 notice_files) 이관. 동일 id로 upsert';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withFiles = (bool) $this->option('with-files');

        $legacy = DB::connection('legacy')
            ->table('notices')
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->get();

        $this->info(sprintf('Legacy notices: %d 건%s', $legacy->count(), $dryRun ? ' (dry-run)' : ''));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $knownUserIds = DB::table('users')->pluck('id')->flip();

        foreach ($legacy as $row) {
            if (empty(trim((string) $row->title))) {
                $this->warn(sprintf('  skip: empty title (legacy.id=%d)', $row->id));
                $skipped++;

                continue;
            }

            $createdBy = $row->created_by;
            if ($createdBy !== null && ! isset($knownUserIds[$createdBy])) {
                $createdBy = null;
            }

            $attributes = [
                'title' => $row->title,
                'content' => $row->content,
                'is_pinned' => (bool) $row->is_pinned,
                'view_count' => (int) ($row->view_count ?? 0),
                'created_by' => $createdBy,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at ?? $row->created_at,
                'deleted_at' => $row->deleted_at,
            ];

            $exists = DB::table('notices')->where('id', $row->id)->exists();

            if ($exists) {
                if (! $dryRun) {
                    DB::table('notices')->where('id', $row->id)->update($attributes);
                }
                $updated++;
            } else {
                if (! $dryRun) {
                    DB::table('notices')->insert(array_merge(['id' => $row->id], $attributes));
                }
                $created++;
            }
        }

        $this->newLine();
        $this->table(
            ['created', 'updated', 'skipped', 'total_notices_now'],
            [[$created, $updated, $skipped, $dryRun ? '—' : DB::table('notices')->count()]]
        );

        if ($withFiles) {
            $this->importFiles($dryRun, $knownUserIds);
        }

        return self::SUCCESS;
    }

    protected function importFiles(bool $dryRun, $knownUserIds): void
    {
        $files = DB::connection('legacy')
            ->table('notice_files')
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->get();

        $this->newLine();
        $this->info(sprintf('Legacy notice_files: %d 건', $files->count()));

        $validNoticeIds = DB::table('notices')->pluck('id')->flip();
        $created = 0;
        $skipped = 0;

        foreach ($files as $row) {
            if (! isset($validNoticeIds[$row->notice_id])) {
                $this->warn(sprintf('  skip: orphan notice_file (id=%d, notice_id=%s)', $row->id, $row->notice_id));
                $skipped++;

                continue;
            }

            $uploadedBy = isset($knownUserIds[$row->uploaded_by]) ? $row->uploaded_by : null;

            $attributes = [
                'id' => $row->id,
                'notice_id' => $row->notice_id,
                'original_name' => $row->original_name,
                'stored_name' => $row->file_name,
                'path' => $row->file_path,
                'size' => (int) $row->file_size,
                'mime_type' => $row->file_type,
                'extension' => $row->file_extension,
                'uploaded_by' => $uploadedBy,
                'created_at' => $row->uploaded_at,
                'updated_at' => $row->uploaded_at,
                'deleted_at' => $row->deleted_at,
            ];

            if (! DB::table('notice_files')->where('id', $row->id)->exists()) {
                if (! $dryRun) {
                    DB::table('notice_files')->insert($attributes);
                }
                $created++;
            }
        }

        $this->table(
            ['created', 'skipped', 'total_files_now'],
            [[$created, $skipped, $dryRun ? '—' : DB::table('notice_files')->count()]]
        );
    }
}
