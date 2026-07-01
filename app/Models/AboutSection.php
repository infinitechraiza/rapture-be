<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AboutSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'eyebrow',
        'title',
        'title_highlight',
        'description_primary',
        'description_secondary',
        'image_url',
        'image_caption_title',
        'image_caption_subtitle',
        'badge_emoji',
        'badge_title',
        'badge_subtitle',
        'stat_value',
        'stat_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(AboutValue::class)->orderBy('sort_order');
    }
}