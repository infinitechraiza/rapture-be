<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image', // Add this - main venue image
        'full_description',
        'price_per_day',
        'max_guests',
        'size',
        'amenities',
        'images',
        'panoramas',
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'panoramas' => 'array',
        'price_per_day' => 'decimal:2',
        'max_guests' => 'integer',
    ];
}