<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscontinueProductRequest;
use App\Http\Requests\RejectProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\ChangeReason;
use App\Models\CompanyProductOverride;
use App\Models\Performance;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = trim((string) $request->input('search'));
        $category = $request->input('category');
        $status = $request->input('status');
        $approvalStatus = $request->input('approval_status');

        $products = Product::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%")
                        ->orWhere('insurance_code', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%")
                        ->orWhere('standard_code', 'like', "%{$search}%")
                        ->orWhere('barcode_gtin', 'like', "%{$search}%")
                        ->orWhere('generic_name', 'like', "%{$search}%")
                        ->orWhere('manufacturer', 'like', "%{$search}%");
                });
            })
            ->when($category !== null && $category !== '', fn ($q) => $q->where('category', $category))
            ->when(in_array($status, [
                Product::STATUS_ACTIVE,
                Product::STATUS_INACTIVE,
                Product::STATUS_DISCONTINUED,
            ], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($approvalStatus, [
                Product::APPROVAL_DRAFT,
                Product::APPROVAL_PENDING,
                Product::APPROVAL_REVIEWED,
                Product::APPROVAL_APPROVED,
                Product::APPROVAL_REJECTED,
            ], true), fn ($q) => $q->where('approval_status', $approvalStatus))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $categories = Product::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'approval_status' => $approvalStatus,
            ],
            'categories' => $categories,
            'can' => [
                'create' => $request->user()->can('create', Product::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Create');
    }

    /**
     * 제품 검색 (대체품 선택, 자동완성 등에서 사용)
     * GET /products/search?q=...&exclude=123
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $q = trim((string) $request->input('q'));
        $exclude = (int) $request->input('exclude');

        $items = Product::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($qb) use ($q) {
                    $qb->where('product_name', 'like', "%{$q}%")
                        ->orWhere('insurance_code', 'like', "%{$q}%")
                        ->orWhere('product_code', 'like', "%{$q}%")
                        ->orWhere('standard_code', 'like', "%{$q}%")
                        ->orWhere('barcode_gtin', 'like', "%{$q}%")
                        ->orWhere('generic_name', 'like', "%{$q}%");
                });
            })
            ->when($exclude > 0, fn ($qb) => $qb->where('id', '!=', $exclude))
            ->orderBy('product_name')
            ->limit(15)
            ->get(['id', 'product_name', 'insurance_code', 'product_code', 'manufacturer']);

        return response()->json($items);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        // 신규 등록 시 승인 단계 강제 초기화 (요청 입력 무시)
        $data['approval_status'] = Product::APPROVAL_DRAFT;
        $data['reviewed_at'] = null;
        $data['reviewed_by'] = null;
        $data['approved_at'] = null;
        $data['approved_by'] = null;

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        unset($data['image']);

        $product = Product::create($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', '제품을 등록했습니다.');
    }

    public function show(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        $product->loadMissing([
            'creator:id,name,email',
            'updater:id,name,email',
            'reviewer:id,name,email',
            'approver:id,name,email',
            'replacement:id,product_name,insurance_code,product_code',
            'commissionRates',
            'files',
            'files.uploader:id,name,email',
            'prices' => fn ($q) => $q->orderBy('price_type')->orderByDesc('effective_from'),
            'prices.creator:id,name,email',
            'prices.updater:id,name,email',
            'companyOverrides' => fn ($q) => $q->orderByDesc('effective_from'),
            'companyOverrides.company:id,company_name,business_registration_number,default_commission_grade',
            'companyOverrides.creator:id,name,email',
            'companyOverrides.updater:id,name,email',
        ]);

        $user = $request->user();

        $latest = $product->latestPricesByType();

        // 변경 이력: 제품 자신 + 자식(가격/파일/override) 의 activity_log 통합 (최근 100건)
        $childIds = [
            'price' => $product->prices->pluck('id')->all(),
            'file' => $product->files->pluck('id')->all(),
            'override' => $product->companyOverrides->pluck('id')->all(),
        ];

        $activities = Activity::query()
            ->where(function ($q) use ($product, $childIds) {
                $q->where(function ($qq) use ($product) {
                    $qq->where('subject_type', Product::class)
                        ->where('subject_id', $product->id);
                })
                    ->orWhere(function ($qq) use ($childIds) {
                        if (! empty($childIds['price'])) {
                            $qq->orWhere(function ($x) use ($childIds) {
                                $x->where('subject_type', ProductPrice::class)
                                    ->whereIn('subject_id', $childIds['price']);
                            });
                        }
                        if (! empty($childIds['file'])) {
                            $qq->orWhere(function ($x) use ($childIds) {
                                $x->where('subject_type', ProductFile::class)
                                    ->whereIn('subject_id', $childIds['file']);
                            });
                        }
                        if (! empty($childIds['override'])) {
                            $qq->orWhere(function ($x) use ($childIds) {
                                $x->where('subject_type', CompanyProductOverride::class)
                                    ->whereIn('subject_id', $childIds['override']);
                            });
                        }
                    });
            })
            ->with('causer:id,name,email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'log_name' => $a->log_name,
                'description' => $a->description,
                'event' => $a->event,
                'subject_type' => class_basename($a->subject_type),
                'subject_id' => $a->subject_id,
                'causer' => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
                'properties' => $a->properties,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        // 최근 실적 미니 패널 (P4-S5 잔여분)
        $recentPerformances = Performance::query()
            ->where('product_id', $product->id)
            ->with('company:id,company_name,default_commission_grade')
            ->orderByDesc('performance_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Performance $p) => [
                'id' => $p->id,
                'performance_no' => $p->performance_no,
                'performance_date' => $p->performance_date?->toDateString(),
                'company' => $p->company ? [
                    'id' => $p->company->id,
                    'company_name' => $p->company->company_name,
                    'default_commission_grade' => $p->company->default_commission_grade,
                ] : null,
                'quantity' => $p->quantity,
                'unit_price' => $p->unit_price,
                'subtotal' => $p->subtotal,
                'commission_rate' => $p->commission_rate,
                'commission_amount' => $p->commission_amount,
                'status' => $p->status,
            ]);

        return Inertia::render('Products/Show', [
            'product' => array_merge($product->toArray(), [
                'image_url' => $product->imageUrl(),
                'current_prices' => [
                    'insurance' => $latest['insurance']?->amount,
                    'cost' => $latest['cost']?->amount,
                    'sale' => $latest['sale']?->amount,
                ],
            ]),
            'recentPerformances' => $recentPerformances,
            'activities' => $activities,
            'can' => [
                'update' => $user->can('update', $product),
                'delete' => $user->can('delete', $product),
                'submit' => $user->can('submit', $product),
                'review' => $user->can('review', $product),
                'approve' => $user->can('approve', $product),
                'reject' => $user->can('reject', $product),
                'discontinue' => $user->can('discontinue', $product),
                'managePrices' => $user->can('create', ProductPrice::class),
                'manageOverrides' => $user->can('create', CompanyProductOverride::class),
            ],
        ]);
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Products/Edit', [
            'product' => array_merge($product->toArray(), [
                'image_url' => $product->imageUrl(),
            ]),
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
            ->route('products.show', $product)
            ->with('success', '제품 정보를 수정했습니다.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', '제품을 삭제했습니다.');
    }

    /**
     * draft|rejected → pending : 검수 요청
     */
    public function submit(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('submit', $product);

        ChangeReason::with('검수 요청', function () use ($product, $request) {
            $product->update([
                'approval_status' => Product::APPROVAL_PENDING,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'updated_by' => $request->user()->id,
            ]);
        });

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->event('submit')
            ->withProperties(['action' => 'submit'])
            ->log('product.submit');

        return back()->with('success', '검수 요청을 보냈습니다.');
    }

    /**
     * pending → reviewed : 검수 완료
     */
    public function review(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('review', $product);

        ChangeReason::with('검수 완료', function () use ($product, $request) {
            $product->update([
                'approval_status' => Product::APPROVAL_REVIEWED,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->event('review')
            ->withProperties(['action' => 'review'])
            ->log('product.review');

        return back()->with('success', '검수를 완료했습니다.');
    }

    /**
     * reviewed → approved : 최종 승인
     */
    public function approve(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('approve', $product);

        ChangeReason::with('최종 승인', function () use ($product, $request) {
            $product->update([
                'approval_status' => Product::APPROVAL_APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->event('approve')
            ->withProperties(['action' => 'approve'])
            ->log('product.approve');

        return back()->with('success', '제품을 승인했습니다.');
    }

    /**
     * pending|reviewed → rejected : 반려 (사유 필수, audit log에 영구 저장)
     */
    public function reject(RejectProductRequest $request, Product $product): RedirectResponse
    {
        $reason = (string) $request->validated('reason');

        ChangeReason::with($reason, function () use ($product, $request) {
            $product->update([
                'approval_status' => Product::APPROVAL_REJECTED,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'updated_by' => $request->user()->id,
            ]);
        });

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->event('reject')
            ->withProperties(['action' => 'reject', 'reason' => $reason])
            ->log('product.reject');

        return back()->with('success', '제품을 반려했습니다.');
    }

    /**
     * status → discontinued : 단종 (대체품/사유는 audit log에 영구 저장)
     */
    public function discontinue(DiscontinueProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $reason = $validated['reason'] ?? null;
        $replacementId = $validated['replacement_product_id'] ?? null;

        $payload = [
            'status' => Product::STATUS_DISCONTINUED,
            'updated_by' => $request->user()->id,
        ];
        if (array_key_exists('replacement_product_id', $validated)) {
            $payload['replacement_product_id'] = $replacementId;
        }

        ChangeReason::with($reason, fn () => $product->update($payload));

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->event('discontinue')
            ->withProperties(array_filter([
                'action' => 'discontinue',
                'reason' => $reason,
                'replacement_product_id' => $replacementId,
            ], fn ($v) => $v !== null))
            ->log('product.discontinue');

        return back()->with('success', '제품을 단종 처리했습니다.');
    }

    protected function storeImage(UploadedFile $file): string
    {
        $stored = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('products', $stored, 'public');
    }
}
