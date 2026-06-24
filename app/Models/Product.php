<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'barcode',
        'supplier_id',
        'category_id',
        'purchase_price',
        'selling_price',
        'stock',
        'location',
        'description',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    // Relaciones
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function details()
    {
        return $this->hasMany(ProductDetail::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Relación polimórfica con imágenes
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // Obtener la imagen principal del producto
    public function getMainImageAttribute()
    {
        return $this->images()->where('is_main', true)->first();
    }

    // Obtener todas las imágenes del producto (excepto principal)
    public function getGalleryImagesAttribute()
    {
        return $this->images()->where('is_main', false)->orderBy('order')->get();
    }

    // Accesor para saber si el producto tiene bajo stock
    public function getIsLowStockAttribute()
    {
        return $this->stock <= 5; // Puedes ajustar el número
    }

    // Accesor para ganancia calculada
    public function getProfitAttribute()
    {
        return $this->selling_price - $this->purchase_price;
    }

    // Accesor para margen de ganancia
    public function getProfitMarginAttribute()
    {
        if ($this->purchase_price == 0) return 0;
        return round(($this->profit / $this->purchase_price) * 100, 2);
    }

    // Scope para buscar por código de barras
    public function scopeByBarcode($query, $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    // Scope para productos activos
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
