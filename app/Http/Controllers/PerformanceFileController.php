<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePerformanceFileRequest;
use App\Models\Performance;
use App\Models\PerformanceFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerformanceFileController extends Controller
{
    public function store(StorePerformanceFileRequest $request, Performance $performance): RedirectResponse
    {
        $this->authorize('uploadFile', $performance);

        if ($performance->files()->count() >= 5) {
            return back()->withErrors(['file' => '증빙 파일은 최대 5개까지 첨부할 수 있습니다.']);
        }

        $upload = $request->file('file');
        $extension = strtolower((string) $upload->getClientOriginalExtension());
        $stored = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $upload->storeAs("performances/{$performance->id}/files", $stored, 'local');

        $performance->files()->create([
            'original_name' => $upload->getClientOriginalName(),
            'stored_name' => $stored,
            'path' => $path,
            'size' => $upload->getSize(),
            'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension ?: null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', '증빙 파일을 첨부했습니다.');
    }

    public function destroy(Performance $performance, PerformanceFile $file): RedirectResponse
    {
        $this->authorize('uploadFile', $performance);

        if ($file->performance_id !== $performance->id) {
            abort(404);
        }

        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', '증빙 파일을 삭제했습니다.');
    }

    public function download(Performance $performance, PerformanceFile $file): StreamedResponse
    {
        $this->authorize('view', $performance);

        if ($file->performance_id !== $performance->id) {
            abort(404);
        }

        if (! $file->path || ! Storage::disk('local')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
