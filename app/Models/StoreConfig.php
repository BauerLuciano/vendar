<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreConfig extends Model
{
    protected $table = 'store_configs';

    protected $fillable = [
        'comercio_id',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    public static function defaultConfig(): array
    {
        return [
            'theme' => [
                'primary_color' => '#00adef',
                'secondary_color' => '#f7941e',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'header_background' => '#FFFFFF',
                'header_text' => '#1F2937',
                'footer_background' => '#1F2937',
                'footer_text' => '#F9FAFB',
                'border_radius' => '0.5rem',
                'font_family' => 'Inter, system-ui, sans-serif',
            ],
            'sections' => [
                'hero' => [
                    'order' => 10,
                    'enabled' => true,
                    'title' => 'Bienvenido a nuestra tienda',
                    'subtitle' => 'Los mejores productos al mejor precio',
                    'background_color' => '#F3F4F6',
                    'text_color' => '#1F2937',
                    'button_text' => 'Ver productos',
                    'button_url' => '#productos',
                    'button_color' => '#3B82F6',
                    'image_url' => null,
                ],
                'categories' => [
                    'order' => 20,
                    'enabled' => true,
                    'title' => 'Categorías',
                    'layout' => 'grid',
                    'columns' => 4,
                    'max_items' => 8,
                ],
                'products' => [
                    'order' => 30,
                    'enabled' => true,
                    'title' => 'Productos destacados',
                    'layout' => 'grid',
                    'columns' => 4,
                    'max_items' => 12,
                    'show_prices' => true,
                    'show_discounts' => true,
                    'show_add_to_cart' => true,
                ],
                'promotions' => [
                    'order' => 40,
                    'enabled' => true,
                    'title' => 'Promociones',
                    'layout' => 'carousel',
                    'max_items' => 10,
                    'autoplay' => true,
                    'interval' => 5000,
                ],
                'footer' => [
                    'order' => 50,
                    'enabled' => true,
                    'show_contact' => true,
                    'show_social' => true,
                    'show_payment_methods' => true,
                    'text' => '© 2026 Tienda - Todos los derechos reservados',
                ],
                'whatsapp' => [
                    'order' => 60,
                    'enabled' => true,
                    'phone' => null,
                    'greeting' => '¡Hola! Quiero hacer un pedido',
                ],
            ],
            'content' => [
                'banners' => [],
                'featured_text' => null,
                'custom_css' => null,
                'custom_js' => null,
            ],
            'seo' => [
                'meta_title' => null,
                'meta_description' => null,
                'meta_keywords' => null,
                'og_image' => null,
                'og_title' => null,
                'og_description' => null,
            ],
        ];
    }
}
