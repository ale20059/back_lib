<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'created_by_user_id', // 👈 Debe estar aquí
        'assigned_to_user_id',
        'status',
        'destination',
        'notes',
    ];

    // Relación con el usuario creador
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
