<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoice';
    protected $primaryKey = 'id';
    protected $fillable = [
        'invoice',
        'date_generate',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'invoice', 'id');
    }
}