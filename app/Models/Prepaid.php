<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prepaid extends Model
{
    use HasFactory;

    protected $table = 'prepaid';
    protected $primaryKey = 'id';
    protected $fillable = [
        'name',
        'description',
        'category',
        'brand',
        'seller_name',
        'seller_price',
        'buyer_price',
        'sku',
        'unlimited',
        'stock',
        'multi',
    ];

    public function insert_data(array $data, float $markup = 0.05): int
    {
        $insertData = [];

        foreach ($data as $result) {
            $price = floatval($result['price']);
            $buyerPrice = $price + ($price * $markup);

            $insertData[] = [
                'name' => $result['product_name'],
                'description' => $result['desc'] ?? '',
                'category' => $result['category'] ?? '',
                'brand' => $result['brand'] ?? '',
                'seller_name' => $result['seller_name'] ?? '',
                'seller_price' => $price,
                'buyer_price' => round($buyerPrice),
                'sku' => $result['buyer_sku_code'],
                'unlimited' => filter_var($result['unlimited_stock'], FILTER_VALIDATE_BOOLEAN) ? 'ya' : 'tidak',
                'stock' => intval($result['stock']),
                'multi' => filter_var($result['multi'], FILTER_VALIDATE_BOOLEAN) ? 'ya' : 'tidak',
            ];
        }

        if (!empty($insertData)) {
            self::upsert(
                $insertData,
                ["sku"],
                [
                    'name',
                    'description',
                    'category',
                    'brand',
                    'seller_name',
                    'seller_price',
                    'buyer_price',
                    'unlimited',
                    'stock',
                    'multi',
                ]
            );
        }

        return count($insertData);
    }
}