<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 전역 의약품(제품) 조회. (GAP-10 MT-6, super_admin)
 * 모든 제약사의 제품을 횡단 조회한다. (CRUD 는 후속)
 */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatform(), 403);

        $search = trim((string) $request->input('search', ''));

        $products = Product::query()
            ->with('tenant:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%")
                        ->orWhere('insurance_code', 'like', "%{$search}%")
                        ->orWhere('manufacturer', 'like', "%{$search}%");
                });
            })
            ->orderBy('product_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'product_name' => $p->product_name,
                'product_code' => $p->product_code,
                'insurance_code' => $p->insurance_code,
                'manufacturer' => $p->manufacturer,
                'status' => $p->status,
                'approval_status' => $p->approval_status,
                'tenant_name' => $p->tenant?->name,
            ]);

        return Inertia::render('Platform/Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }
}
