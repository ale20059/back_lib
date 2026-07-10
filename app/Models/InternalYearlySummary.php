<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalYearlySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_product_id',
        'year',
        'total_added',
        'total_used',
        'starting_stock',
        'ending_stock'
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(InternalProduct::class, 'internal_product_id');
    }
}
