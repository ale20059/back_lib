<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'thumbnail_url',
        'file_name',
        'file_size',
        'mime_type',
        'imageable_id',
        'imageable_type',
        'is_main',
        'order',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'file_size' => 'integer',
        'order' => 'integer',
    ];

    // Relación polimórfica inversa
    public function imageable()
    {
        return $this->morphTo();
    }

    // Accesor para saber si es una imagen válida
    public function getIsImageAttribute()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // Scope para imágenes principales
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    // Scope para imágenes secundarias
    public function scopeGallery($query)
    {
        return $query->where('is_main', false);
    }

    // Boot para orden automático
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($image) {
            if ($image->order === null) {
                $maxOrder = static::where('imageable_id', $image->imageable_id)
                    ->where('imageable_type', $image->imageable_type)
                    ->max('order');
                $image->order = ($maxOrder ?? -1) + 1;
            }
        });
    }
}
