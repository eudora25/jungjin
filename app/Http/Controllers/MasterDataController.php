<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\Pharmacy;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 기준정보 마스터 허브 (GAP-9) — 병의원·약국·의약품을 거래처와 독립된
 * 마스터로 한곳에서 진입하기 위한 admin 랜딩 화면.
 */
class MasterDataController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('MasterData/Index', [
            'counts' => [
                'products' => Product::query()->count(),
                'pharmacies' => Pharmacy::query()->count(),
                'hospitals' => Hospital::query()->count(),
            ],
        ]);
    }
}
