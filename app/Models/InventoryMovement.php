<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'unit_cost',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accesor para el tipo formateado
    public function getTypeFormattedAttribute()
    {
        return $this->type === 'in' ? '📥 Entrada' : '📤 Salida';
    }

    // Accesor para el color según el tipo
    public function getTypeColorAttribute()
    {
        return $this->type === 'in' ? 'success' : 'danger';
    }

    // Scope para solo entradas
    public function scopeIn($query)
    {
        return $query->where('type', 'in');
    }

    // Scope para solo salidas
    public function scopeOut($query)
    {
        return $query->where('type', 'out');
    }
}
