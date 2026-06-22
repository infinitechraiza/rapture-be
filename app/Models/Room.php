<?php
// app/Models/Room.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'full_description',
        'price',
        'capacity',
        'size',
        'bed_type',
        'amenities',
        'image',
        'images',
        'panoramas',
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'panoramas' => 'array',
        'price' => 'decimal:2',
        'capacity' => 'integer',
    ];
}