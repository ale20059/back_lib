<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_product_id',
        'type',
        'quantity',
        'reason',
        'used_by',
        'destination',
        'year',
        'user_id'
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(InternalProduct::class, 'internal_product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return $this->type === 'add' ? '📥 Agregado' : '📤 Usado';
    }

    public function getTypeColorAttribute()
    {
        return $this->type === 'add' ? 'success' : 'danger';
    }

    // Scopes
    public function scopeAdd($query)
    {
        return $query->where('type', 'add');
    }

    public function scopeUse($query)
    {
        return $query->where('type', 'use');
    }

    public function scopeYear($query, $year)
    {
        return $query->where('year', $year);
    }
}
