<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\DigiflazzService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Traits\CodeGenerate;

class MidtransController extends Controller
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

    public function handleWebhook(Request $request)
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $notif = new \Midtrans\Notification();

        $transaction = $notif->transaction_status;
        $order_id    = $notif->order_id;

        $trx = Transaction::with('prepaid')->where('order_id', $order_id)->first();

        if (!$trx) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ($transaction == 'settlement') {
            $trx->update([
                'payment_status' => 'paid',
                'status' => 'success',
                'payment_method' => $notif->payment_type
            ]);

            $ref_id = $this->getCode();

            $buyer_sku_code = $trx->prepaid->sku;
            $customer_no = $trx->target_number;

            $response = Http::withHeaders($this->header)->post($this->url . '/transaction', [
                "username"       => $this->user,
                "buyer_sku_code" => $buyer_sku_code,
                "customer_no"    => $customer_no,
                "ref_id"         => $ref_id,
                "sign"           => md5($this->user . $this->key . $ref_id)
            ]);

            $data = json_decode($response->body(), true);

            return response()->json($data);
        }
    }
}
