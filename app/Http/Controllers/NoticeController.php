<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Models\Notice;
use App\Models\NoticeFile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NoticeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Notice::class);

        $search = trim((string) $request->input('search'));

        $notices = Notice::query()
            ->with('author:id,name,email')
            ->withCount('files')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->pinnedFirst()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Notices/Index', [
            'notices' => $notices,
            'filters' => ['search' => $search],
            'can' => [
                'create' => $request->user()->can('create', Notice::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Notice::class);

        return Inertia::render('Notices/Create');
    }

    public function store(StoreNoticeRequest $request): RedirectResponse
    {
        $notice = DB::transaction(function () use ($request) {
            $notice = Notice::create([
                'title' => $request->string('title'),
                'content' => $request->input('content'),
                'is_pinned' => $request->boolean('is_pinned'),
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $this->storeAttachment($notice, $file, $request->user()->id);
            }

            return $notice;
        });

        return redirect()
            ->route('notices.show', $notice)
            ->with('success', '공지를 등록했습니다.');
    }

    public function show(Request $request, Notice $notice): Response
    {
        $this->authorize('view', $notice);

        $notice->increment('view_count');
        $notice->loadMissing(['author:id,name,email', 'files']);

        $notice->readers()->syncWithoutDetaching([
            $request->user()->id => ['read_at' => now()],
        ]);

        $readStats = null;
        if ($request->user()->isPharma()) {
            $totalUsers = User::where('is_active', true)->count();
            $readers = $notice->readers()
                ->where('users.is_active', true)
                ->orderByPivot('read_at', 'desc')
                ->get(['users.id', 'users.name', 'notice_reads.read_at']);

            $readStats = [
                'total' => $totalUsers,
                'read_count' => $readers->count(),
                'readers' => $readers->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'read_at' => $u->pivot->read_at,
                ]),
            ];
        }

        return Inertia::render('Notices/Show', [
            'notice' => $notice,
            'readStats' => $readStats,
            'can' => [
                'update' => $request->user()->can('update', $notice),
                'delete' => $request->user()->can('delete', $notice),
            ],
        ]);
    }

    public function edit(Notice $notice): Response
    {
        $this->authorize('update', $notice);

        $notice->loadMissing('files');

        return Inertia::render('Notices/Edit', [
            'notice' => $notice,
        ]);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): RedirectResponse
    {
        DB::transaction(function () use ($request, $notice) {
            $notice->update([
                'title' => $request->string('title'),
                'content' => $request->input('content'),
                'is_pinned' => $request->boolean('is_pinned'),
            ]);

            foreach ($request->input('removed_file_ids', []) as $fileId) {
                $file = $notice->files()->whereKey($fileId)->first();
                if ($file) {
                    Storage::disk('local')->delete($file->path);
                    $file->delete();
                }
            }

            foreach ($request->file('attachments', []) as $file) {
                $this->storeAttachment($notice, $file, $request->user()->id);
            }
        });

        return redirect()
            ->route('notices.show', $notice)
            ->with('success', '공지를 수정했습니다.');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $this->authorize('delete', $notice);

        $notice->delete();

        return redirect()
            ->route('notices.index')
            ->with('success', '공지를 삭제했습니다.');
    }

    protected function storeAttachment(Notice $notice, UploadedFile $file, int $userId): NoticeFile
    {
        $stored = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("notices/{$notice->id}", $stored, 'local');

        return $notice->files()->create([
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $stored,
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'extension' => strtolower($file->getClientOriginalExtension()),
            'uploaded_by' => $userId,
        ]);
    }
}
