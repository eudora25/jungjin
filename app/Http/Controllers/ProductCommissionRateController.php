<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductCommissionRateRequest;
use App\Models\Product;
use App\Models\ProductCommissionRate;
use Illuminate\Http\RedirectResponse;

class ProductCommissionRateController extends Controller
{
    public function store(StoreProductCommissionRateRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $data['product_id'] = $product->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        ProductCommissionRate::create($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', '수수료율을 등록했습니다.');
    }

    public function update(StoreProductCommissionRateRequest $request, Product $product, ProductCommissionRate $rate): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_if($rate->product_id !== $product->id, 404);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $rate->update($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', '수수료율을 수정했습니다.');
    }

    public function destroy(Product $product, ProductCommissionRate $rate): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_if($rate->product_id !== $product->id, 404);

        $rate->delete();

        return redirect()
            ->route('products.show', $product)
            ->with('success', '수수료율을 삭제했습니다.');
    }
}
