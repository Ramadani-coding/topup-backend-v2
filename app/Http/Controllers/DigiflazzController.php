<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Prepaid;
use App\Models\Transaction;
use App\Services\DigiflazzService;
use App\Traits\CodeGenerate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Notification;
use Midtrans\Snap;

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
            ->distinct()
            ->pluck('brand');

        $brandImages = [
            'FREE FIRE' => asset('storage/brand-icons/ff.png'),
            'MOBILE LEGENDS' => asset('storage/brand-icons/ml.webp'),
        ];

        $brandsFormatted = $brands->map(function ($brand) use ($brandImages) {
            return [
                'label' => $brand,
                'link' => 'http://127.0.0.1:8000/api/buy/' . Str::slug($brand),
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



    public function createDraftOrder(Request $request)
    {
        $ref_id = $this->getCode();

        $product = Prepaid::where('sku', $request->sku)->firstOrFail();

        $invoice = Invoice::where('invoice', $ref_id)->firstOrFail();

        $order = Transaction::create([
            'invoice'        => $invoice->id,
            'target_number'  => $request->customer_no,
            'sku'            => $product->id,
            'price'          => $product->buyer_price,
            'status'         => 'pending_confirmation',
            'payment_status' => 'unpaid',
            'order_id'       => 'INV-' . uniqid(),
        ]);



        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = true;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $params = [
            'transaction_details' => [
                'order_id'     => $order->order_id,
                'gross_amount' => $order->price,
            ],
            'customer_details' => [
                'first_name' => auth()->user()->name ?? 'Guest',
                'email'      => auth()->user()->email ?? 'noemail@example.com',
            ],
            'item_details' => [
                [
                    'id'       => $product->sku,
                    'price'    => $product->buyer_price,
                    'quantity' => 1,
                    'name'     => $product->name,
                ],
            ]
        ];

        // $snapToken = Snap::getSnapToken($params);
        $snapTransaction = \Midtrans\Snap::createTransaction($params);

        return response()->json([
            'order'   => $order,
            'product' => [
                'item' => $product->name,
                'product' => $product->brand,
                'price' => $product->buyer_price
            ],
            'payment_url' => $snapTransaction->redirect_url
        ]);
    }
}
