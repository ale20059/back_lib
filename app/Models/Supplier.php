<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_name',
        'email',
        'phone',
        'address',
        'notes',
    ];

    // Relaciones
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Relación polimórfica con imágenes
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // Obtener la imagen principal del proveedor
    public function getMainImageAttribute()
    {
        return $this->images()->where('is_main', true)->first();
    }

    // Accesor para nombre completo del proveedor
    public function getDisplayNameAttribute()
    {
        return $this->company_name
            ? "{$this->name} ({$this->company_name})"
            : $this->name;
    }
}
