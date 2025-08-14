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

    public function createOrder(Request $request, string $brand)
    {
        $ref_id = $this->getCode();

        $brandNormalized = strtoupper(str_replace('-', ' ', trim($brand)));

        $product = Prepaid::where('sku', $request->sku)->whereRaw('TRIM(UPPER(brand)) = ?', [trim($brandNormalized)])->first();
        $invoice = Invoice::where('invoice', $ref_id)->firstOrFail();
        $order_id = 'INV-' . uniqid();

        $products = Prepaid::where('sku', $request->sku)->firstOrFail();
        $checkSku = preg_replace('/\d+/', '', $products->sku);

        $order = Transaction::create([
            'invoice'        => $invoice->id,
            'target_number'  => $request->customer_no,
            'sku'            => $product->id,
            'price'          => $product->buyer_price,
            'status'         => 'pending',
            'payment_status' => 'unpaid',
            'order_id'       => $order_id,
        ]);

        $trx = Transaction::with('invoiceData')->where('order_id', $order_id)->first();
        $invoiceNumber = $trx->invoiceData->invoice;

        $maxAttempts = 5;
        $attempt = 0;
        $status = 'Pending';

        do {
            $attempt++;

            $topupResponse = Http::withHeaders($this->header)->post($this->url . '/transaction', [
                "username"       => $this->user,
                "buyer_sku_code" => $checkSku,
                "customer_no"    => $request->customer_no,
                "ref_id"         => $invoiceNumber,
                "sign"           => md5($this->user . $this->key . $invoiceNumber)
            ]);

            $topupData = json_decode($topupResponse->body(), true);

            if (!empty($topupData['data']['status'])) {
                $status = $topupData['data']['status'];
                if ($status !== 'Pending') {
                    break;
                }
            }

            sleep(2);
        } while ($attempt < $maxAttempts);

        $sn = $topupData['data']['sn'];
        preg_match('/User ID (\d+) Zone (\d+)/', $sn, $match1);
        preg_match('/Username ([^\/]+) \/ Region = (\w+)/', $sn, $match2);

        $detailTarget = [
            'User ID'  => $match1[1] ?? null,
            'Server'   => $match1[2] ?? null,
            'Nickname' => ($match2[1] ?? '') . ' - ' . ($match2[2] ?? ''),
        ];

        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = false;
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
            'invoice' => $invoiceNumber,
            'Detail Target' => $topupData['data']['sn'],
            'product' => [
                'item' => $product->name,
                'product' => $product->brand,
                'price' => $product->buyer_price
            ],
            'order'   => [
                'customer_no' => $order->target_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'order_id' => $order->order_id
            ],
            'payment_url' => $snapTransaction->redirect_url,
        ]);
    }
}
