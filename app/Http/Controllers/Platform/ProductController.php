<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreProductRequest;
use App\Http\Requests\Platform\UpdateProductRequest;
use App\Models\ChangeReason;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 전역 의약품(제품) CRUD. (GAP-10 후속-A §6.8, platform)
 * 모든 제약사의 제품을 횡단 조회·등록·수정·삭제한다. 등록 시 대상 제약사(tenant_id)를 명시 선택한다.
 * `/platform/products/*` 는 임퍼서네이션 중에도 TenantScope 를 우회해 전역 조회/바인딩한다.
 */
class ProductController extends Controller
{
    private function ensurePlatform(Request $request): void
    {
        abort_unless($request->user()->isPlatform(), 403);
    }

    /** 제약사 Select 옵션 (활성 입주 우선, 이름순) */
    private function tenantOptions(): array
    {
        return Tenant::query()
            ->orderByDesc('status') // active 가 inactive 보다 앞 (문자열 역순)
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (Tenant $t) => ['value' => $t->id, 'label' => $t->name, 'status' => $t->status])
            ->all();
    }

    public function index(Request $request): Response
    {
        $this->ensurePlatform($request);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $approval = $request->input('approval_status');

        $statuses = [Product::STATUS_ACTIVE, Product::STATUS_INACTIVE, Product::STATUS_DISCONTINUED];
        $approvals = [
            Product::APPROVAL_DRAFT, Product::APPROVAL_PENDING, Product::APPROVAL_REVIEWED,
            Product::APPROVAL_APPROVED, Product::APPROVAL_REJECTED,
        ];

        $products = Product::platformGlobalQuery()
            ->with('tenant:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%")
                        ->orWhere('insurance_code', 'like', "%{$search}%")
                        ->orWhere('manufacturer', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, $statuses, true), fn ($q) => $q->where('status', $status))
            ->when(in_array($approval, $approvals, true), fn ($q) => $q->where('approval_status', $approval))
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
            'filters' => ['search' => $search, 'status' => $status, 'approval_status' => $approval],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensurePlatform($request);

        return Inertia::render('Platform/Products/Create', [
            'tenants' => $this->tenantOptions(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        // platform 등록 제품은 즉시 승인 상태(플랫폼이 최종 권한 — pharma 승인 워크플로 불필요)
        $data['approval_status'] = Product::APPROVAL_APPROVED;
        $data['approved_at'] = now();
        $data['approved_by'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        unset($data['image']);

        $product = Product::create($data);

        return redirect()
            ->route('platform.products.show', $product)
            ->with('success', '제품을 등록했습니다.');
    }

    public function show(Request $request, Product $product): Response
    {
        $this->ensurePlatform($request);

        $product->loadMissing(['tenant:id,name', 'creator:id,name,email', 'updater:id,name,email']);

        return Inertia::render('Platform/Products/Show', [
            'product' => [
                'id' => $product->id,
                'tenant_name' => $product->tenant?->name,
                'insurance_code' => $product->insurance_code,
                'standard_code' => $product->standard_code,
                'barcode_gtin' => $product->barcode_gtin,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'generic_name' => $product->generic_name,
                'strength' => $product->strength,
                'unit' => $product->unit,
                'pack_size' => $product->pack_size,
                'manufacturer' => $product->manufacturer,
                'category' => $product->category,
                'drug_type' => $product->drug_type,
                'storage_condition' => $product->storage_condition,
                'nims_item_code' => $product->nims_item_code,
                'description' => $product->description,
                'price' => $product->price,
                'status' => $product->status,
                'approval_status' => $product->approval_status,
                'remarks' => $product->remarks,
                'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
                'creator_name' => $product->creator?->name,
                'updater_name' => $product->updater?->name,
                'updated_at' => $product->updated_at?->toDateTimeString(),
            ],
        ]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->ensurePlatform($request);

        $product->loadMissing('tenant:id,name');

        return Inertia::render('Platform/Products/Edit', [
            'product' => [
                'id' => $product->id,
                'tenant_name' => $product->tenant?->name,
                'insurance_code' => $product->insurance_code,
                'standard_code' => $product->standard_code,
                'barcode_gtin' => $product->barcode_gtin,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'generic_name' => $product->generic_name,
                'strength' => $product->strength,
                'unit' => $product->unit,
                'pack_size' => $product->pack_size,
                'manufacturer' => $product->manufacturer,
                'category' => $product->category,
                'drug_type' => $product->drug_type,
                'storage_condition' => $product->storage_condition,
                'nims_item_code' => $product->nims_item_code,
                'description' => $product->description,
                'price' => $product->price,
                'status' => $product->status,
                'remarks' => $product->remarks,
                'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if ($request->boolean('remove_image') && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        $changeReason = $data['change_reason'] ?? null;
        unset($data['image'], $data['remove_image'], $data['change_reason']);

        ChangeReason::with($changeReason, fn () => $product->update($data));

        return redirect()
            ->route('platform.products.show', $product)
            ->with('success', '제품 정보를 수정했습니다.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->ensurePlatform($request);

        $product->delete();

        return redirect()
            ->route('platform.products.index')
            ->with('success', '제품을 삭제했습니다.');
    }

    protected function storeImage(UploadedFile $file): string
    {
        $stored = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('products', $stored, 'public');
    }
}
