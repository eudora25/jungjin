<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyProductOverrideRequest;
use App\Http\Requests\UpdateCompanyProductOverrideRequest;
use App\Models\CompanyProductOverride;
use App\Models\Product;
use App\Services\Products\CompanyProductOverrideService;
use Illuminate\Http\RedirectResponse;

class CompanyProductOverrideController extends Controller
{
    public function __construct(private readonly CompanyProductOverrideService $service)
    {
    }

    public function store(StoreCompanyProductOverrideRequest $request, Product $product): RedirectResponse
    {
        $this->service->register(
            $product,
            $request->validated(),
            $request->user()?->id,
        );

        return back()->with('success', '거래처 예외를 등록했습니다.');
    }

    public function update(UpdateCompanyProductOverrideRequest $request, Product $product, CompanyProductOverride $override): RedirectResponse
    {
        if ($override->product_id !== $product->id) {
            abort(404);
        }

        $this->service->update(
            $override,
            $request->validated(),
            $request->user()?->id,
        );

        return back()->with('success', '거래처 예외를 수정했습니다.');
    }

    public function destroy(Product $product, CompanyProductOverride $override): RedirectResponse
    {
        $this->authorize('delete', $override);

        if ($override->product_id !== $product->id) {
            abort(404);
        }

        $this->service->delete($override);

        return back()->with('success', '거래처 예외를 삭제했습니다.');
    }
}
