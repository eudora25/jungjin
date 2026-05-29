<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticeFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoticeFileController extends Controller
{
    public function download(Notice $notice, NoticeFile $file): StreamedResponse
    {
        $this->authorize('view', $notice);

        if ($file->notice_id !== $notice->id) {
            abort(404);
        }

        if (! $file->path || ! Storage::disk('local')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
