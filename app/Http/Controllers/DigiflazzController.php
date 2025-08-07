<?php

namespace App\Http\Controllers;

use App\Models\Prepaid;
use App\Services\DigiflazzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DigiflazzController extends Controller
{
    protected $digiflazzService;

    public function __construct(DigiflazzService $digiflazzService)
    {
        $this->digiflazzService = $digiflazzService;
    }

    public function GetProductsPrepaid(): JsonResponse
    {
        $result = $this->digiflazzService->syncPrepaidProducts();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil disinkronisasi.',
                'total_synced' => $result['total'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 500);
    }

    public function getBrands()
    {
        $brands = Prepaid::select('brand')
            ->where('category', 'Games')
            ->distinct()
            ->pluck('brand');

        $brandImages = [
            'FREE FIRE' => asset('storage/brand-icons/ff.png'),
            'MOBILE LEGENDS' => asset('storage/brand-icons/ml.webp'),
        ];

        $brandsFormatted = $brands->map(function ($brand) use ($brandImages) {
            return [
                'label' => $brand,
                'value' => $brand,
                'image' => $brandImages[$brand] ?? asset('storage/brand-icons/default.png'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $brandsFormatted,
        ]);
    }

    public function getByBrand($slug)
    {
        // Ambil semua brand yang unik
        $brands = Prepaid::select('brand')
            ->where('category', 'Games')
            ->distinct()
            ->pluck('brand');

        // Cari brand asli yang sesuai dengan slug yang diminta
        $matchedBrand = $brands->first(function ($brand) use ($slug) {
            return Str::slug($brand) === $slug;
        });

        if (!$matchedBrand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand tidak ditemukan.'
            ], 404);
        }

        $products = Prepaid::where('brand', $matchedBrand)
            ->orderBy('buyer_price')
            ->get();

        return response()->json([
            'success' => true,
            'brand' => $matchedBrand,
            'count' => $products->count(),
            'products' => $products
        ]);
    }
}