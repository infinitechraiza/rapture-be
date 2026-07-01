<?php

namespace Database\Seeders;

use App\Models\AboutSection;
use App\Models\AboutValue;
use Illuminate\Database\Seeder;

class AboutSeeder extends Seeder
{
    public function run(): void
    {
        $section = AboutSection::create([
            'eyebrow' => 'Our Story',
            'title' => 'A Safe Space',
            'title_highlight' => 'For All Colors',
            'description_primary' => "RAPTURE was born from a simple belief: everyone deserves a place where they can be fully, unapologetically themselves. We're more than a bar — we're a home for Quezon City's vibrant LGBTQ+ community.",
            'description_secondary' => 'From our comedians who light up the stage to our baristas who craft your favorite morning brew — every person at RAPTURE is family.',
            'image_url' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=700&q=80',
            'image_caption_title' => 'Since 2019',
            'image_caption_subtitle' => 'TOMAS MORATO, QC',
            'badge_emoji' => '🏳️‍🌈',
            'badge_title' => 'PRIDE ALWAYS',
            'badge_subtitle' => 'Quezon City',
            'stat_value' => '5,000+',
            'stat_label' => 'Community Members',
            'is_active' => true,
        ]);

        $values = [
            [
                'icon' => 'ShieldCheck',
                'title' => 'Safe Space Policy',
                'description' => 'Zero tolerance for discrimination. Everyone is welcome and protected.',
            ],
            [
                'icon' => 'Mic2',
                'title' => 'Live Entertainment',
                'description' => 'Drag shows, DJ sets, comedy & live music every single week.',
            ],
            [
                'icon' => 'Coffee',
                'title' => 'Café by Day',
                'description' => 'Coffee, brunch & bites from 10AM for the daytime crowd.',
            ],
            [
                'icon' => 'Heart',
                'title' => 'Community First',
                'description' => '10% of profits go to LGBTQ+ advocacy organizations.',
            ],
        ];

        foreach ($values as $i => $value) {
            AboutValue::create([
                'about_section_id' => $section->id,
                'icon' => $value['icon'],
                'title' => $value['title'],
                'description' => $value['description'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}