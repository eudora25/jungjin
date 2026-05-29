<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductFileRequest;
use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductFileController extends Controller
{
    public function store(StoreProductFileRequest $request, Product $product): RedirectResponse
    {
        $upload = $request->file('file');

        $extension = strtolower((string) $upload->getClientOriginalExtension());
        $stored = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $upload->storeAs("products/{$product->id}/files", $stored, 'public');

        ProductFile::create([
            'product_id' => $product->id,
            'file_type' => $request->validated('file_type'),
            'original_name' => $upload->getClientOriginalName(),
            'stored_name' => $stored,
            'path' => $path,
            'size' => $upload->getSize(),
            'mime_type' => $upload->getClientMimeType(),
            'extension' => $extension ?: null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', '첨부 파일을 업로드했습니다.');
    }

    public function destroy(Product $product, ProductFile $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        if ($file->product_id !== $product->id) {
            abort(404);
        }

        if ($file->path && Storage::disk('public')->exists($file->path)) {
            Storage::disk('public')->delete($file->path);
        }

        $file->delete();

        return back()->with('success', '첨부 파일을 삭제했습니다.');
    }

    public function download(Product $product, ProductFile $file): StreamedResponse
    {
        $this->authorize('view', $file);

        if ($file->product_id !== $product->id) {
            abort(404);
        }

        if (! $file->path || ! Storage::disk('public')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk('public')->download($file->path, $file->original_name);
    }
}
