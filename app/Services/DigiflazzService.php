<?php

namespace App\Services;

use App\Models\Prepaid;
use Illuminate\Support\Facades\Http;

class DigiflazzService
{
    public function syncPrepaidProducts(): array
    {
        $url = env('DIGIFLAZZ_URL');
        $user = env('DIGIFLAZZ_USERNAME');
        $key = env('DIGIFLAZZ_DEV_KEY');
        $markup = floatval(env('BUYER_PRICE_MARKUP', 0.05)); // 5% default

        $payload = [
            "cmd" => "prepaid",
            "username" => $user,
            "sign" => md5($user . $key . "pricelist")
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($url . '/price-list', $payload);

            $json = $response->json();

            if (!isset($json['data']) || !is_array($json['data'])) {
                return ['success' => false, 'message' => 'Data tidak valid dari API DigiFlazz'];
            }

            $prepaid = new Prepaid();
            $total = $prepaid->insert_data($json['data'], $markup);

            return ['success' => true, 'total' => $total];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}