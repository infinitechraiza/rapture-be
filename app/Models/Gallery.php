<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    use HasFactory;

    // We're using custom timestamp column names, so tell Eloquent about them
    // instead of the default created_at / updated_at.
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_updated';

    protected $fillable = [
        'title',
        'category',
        'description',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

  
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }
        return asset('storage/' . ltrim($this->image, '/'));
    }
}