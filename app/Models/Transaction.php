<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transaction';
    protected $primaryKey = 'id';
    protected $fillable = [
        'invoice',
        'brand',
        'target_number',
        'sku',
        'price',
        'message',
        'status',
        'payment_status',
        'payment_method',
        'order_id'
    ];

    public function prepaid()
    {
        return $this->belongsTo(Prepaid::class, 'sku', 'id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'id', 'invoice');
    }
}
