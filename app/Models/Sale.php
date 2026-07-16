<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'user_id',
        'subtotal',
        'tax',
        'total',
        'payment_method',

        'grado',
        'estudiante',
        'talla',
        'boleta',
        'quien_entrego'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Accesor para método de pago formateado
    public function getPaymentMethodFormattedAttribute()
    {
        $methods = [
            'cash' => '💰 Efectivo',
            'card' => '💳 Tarjeta',
            'transfer' => '🏦 Transferencia',
        ];

        return $methods[$this->payment_method] ?? $this->payment_method;
    }

    // Accesor para el color del método de pago
    public function getPaymentMethodColorAttribute()
    {
        $colors = [
            'cash' => 'success',
            'card' => 'info',
            'transfer' => 'primary',
        ];

        return $colors[$this->payment_method] ?? 'secondary';
    }

    // Boot para generar invoice_number automáticamente
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->invoice_number)) {
                $latest = static::latest('id')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $sale->invoice_number = 'INV-' . str_pad($nextId, 8, '0', STR_PAD_LEFT);
            }
        });
    }
}
