<?php

namespace App\Http\Controllers;

use App\Models\Prepaid;
use App\Services\DigiflazzService;
use App\Traits\CodeGenerate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;


class DigiflazzController extends Controller
{
    use CodeGenerate;

    protected $digiflazzService;
    protected $header = null;
    protected $url = null;
    protected $user = null;
    protected $key = null;
    protected $model = null;
    protected $model_pasca = null;
    protected $model_transaction = null;

    public function __construct(DigiflazzService $digiflazzService)
    {
        $this->digiflazzService = $digiflazzService;

        $this->header = array(
            'Content-Type:application/json'
        );

        $this->url = env('DIGIFLAZZ_URL');
        $this->user = env('DIGIFLAZZ_USERNAME');
        $this->key = env('DIGIFLAZ_MODE') == 'development' ? env('DIGIFLAZZ_DEV_KEY') : env('DIGIFLAZZ_PROD_KEY');
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

    public function topup(Request $request)
    {
        $ref_id = $this->getCode();

        $response = Http::withHeaders($this->header)->post($this->url . '/transaction', [
            "username" => $this->user,
            "buyer_sku_code" => $request->sku,
            "customer_no" => $request->customer_no,
            "ref_id" =>  $ref_id,
            "sign" => md5($this->user . $this->key . $ref_id)
        ]);

        $data = json_decode($response->body(), true);

        return response()->json($data);
    }
}