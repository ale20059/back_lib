<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'unit',
        'current_stock',
        'minimum_stock',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function movements()
    {
        return $this->hasMany(InternalMovement::class);
    }

    public function yearlySummaries()
    {
        return $this->hasMany(InternalYearlySummary::class);
    }

    // Scope para productos activos
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope para productos con stock bajo
    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'minimum_stock');
    }

    // Accesor para saber si necesita reabastecimiento
    public function getNeedsRestockAttribute()
    {
        return $this->current_stock <= $this->minimum_stock;
    }
}
