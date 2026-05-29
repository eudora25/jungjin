<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductPriceRequest;
use App\Http\Requests\UpdateProductPriceRequest;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Products\ProductPriceService;
use Illuminate\Http\RedirectResponse;

class ProductPriceController extends Controller
{
    public function __construct(private readonly ProductPriceService $service) {}

    public function store(StoreProductPriceRequest $request, Product $product): RedirectResponse
    {
        $this->service->register(
            $product,
            $request->validated(),
            $request->user()?->id,
        );

        return back()->with('success', '가격 이력을 등록했습니다.');
    }

    public function update(UpdateProductPriceRequest $request, Product $product, ProductPrice $price): RedirectResponse
    {
        if ($price->product_id !== $product->id) {
            abort(404);
        }

        $this->service->update(
            $price,
            $request->validated(),
            $request->user()?->id,
        );

        return back()->with('success', '가격 이력을 수정했습니다.');
    }

    public function destroy(Product $product, ProductPrice $price): RedirectResponse
    {
        $this->authorize('delete', $price);

        if ($price->product_id !== $product->id) {
            abort(404);
        }

        $this->service->delete($price);

        return back()->with('success', '가격 이력을 삭제했습니다.');
    }
}
