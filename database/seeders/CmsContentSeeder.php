<?php

namespace Database\Seeders;

use App\Models\CmsContent;
use Illuminate\Database\Seeder;

class CmsContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contents = [
            // Home Page - Hero Section
            [
                'page' => 'home',
                'section' => 'hero',
                'key' => 'title',
                'value' => 'Curated Tech for Modern Living.',
                'type' => 'text',
                'label' => 'Hero Title',
            ],
            [
                'page' => 'home',
                'section' => 'hero',
                'key' => 'subtitle',
                'value' => 'Discover our latest collection of premium devices, engineered for performance and designed to inspire.',
                'type' => 'textarea',
                'label' => 'Hero Subtitle',
            ],
            [
                'page' => 'home',
                'section' => 'hero',
                'key' => 'badge',
                'value' => 'SEASON ESSENTIALS',
                'type' => 'text',
                'label' => 'Hero Badge',
            ],
            [
                'page' => 'home',
                'section' => 'hero',
                'key' => 'image',
                'value' => 'settings/hero_banner.png',
                'type' => 'image',
                'label' => 'Hero Image',
            ],

            // Home Page - Promotions/Collections (The Audio Collection)
            [
                'page' => 'home',
                'section' => 'audio_collection',
                'key' => 'title',
                'value' => 'The Audio Collection',
                'type' => 'text',
                'label' => 'Audio Collection Title',
            ],
            [
                'page' => 'home',
                'section' => 'audio_collection',
                'key' => 'description',
                'value' => 'Immersive soundscapes',
                'type' => 'text',
                'label' => 'Audio Collection Description',
            ],

            // Home Page - New Arrivals Section
            [
                'page' => 'home',
                'section' => 'new_arrival',
                'key' => 'title',
                'value' => 'New Arrivals',
                'type' => 'text',
                'label' => 'New Arrival Title',
            ],
            [
                'page' => 'home',
                'section' => 'new_arrival',
                'key' => 'subtitle',
                'value' => 'Check out our latest products',
                'type' => 'text',
                'label' => 'New Arrival Subtitle',
            ],

            // Upgrade Your Tech Game section (from 2nd image)
            [
                'page' => 'home',
                'section' => 'tech_upgrade',
                'key' => 'title',
                'value' => 'Upgrade Your Tech Game.',
                'type' => 'text',
                'label' => 'Tech Upgrade Title',
            ],
        ];

        foreach ($contents as $content) {
            CmsContent::updateOrCreate(
                [
                    'page' => $content['page'],
                    'section' => $content['section'],
                    'key' => $content['key']
                ],
                [
                    'value' => $content['value'],
                    'type' => $content['type'],
                    'label' => $content['label']
                ]
            );
        }
    }
}
