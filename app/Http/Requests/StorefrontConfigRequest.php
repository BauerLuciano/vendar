<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme.primary_color' => 'nullable|string|max:20',
            'theme.secondary_color' => 'nullable|string|max:20',
            'theme.background_color' => 'nullable|string|max:20',
            'theme.text_color' => 'nullable|string|max:20',
            'theme.header_background' => 'nullable|string|max:20',
            'theme.header_text' => 'nullable|string|max:20',
            'theme.footer_background' => 'nullable|string|max:20',
            'theme.footer_text' => 'nullable|string|max:20',
            'theme.border_radius' => 'nullable|string|max:20',
            'theme.font_family' => 'nullable|string|max:100',

            'sections.hero.enabled' => 'boolean',
            'sections.hero.title' => 'nullable|string|max:255',
            'sections.hero.subtitle' => 'nullable|string|max:500',
            'sections.hero.background_color' => 'nullable|string|max:20',
            'sections.hero.text_color' => 'nullable|string|max:20',
            'sections.hero.button_text' => 'nullable|string|max:100',
            'sections.hero.button_url' => 'nullable|string|max:255',
            'sections.hero.button_color' => 'nullable|string|max:20',
            'sections.hero.image_url' => 'nullable|string|max:500',

            'sections.categories.enabled' => 'boolean',
            'sections.categories.title' => 'nullable|string|max:255',
            'sections.categories.layout' => 'nullable|string|in:grid,list',
            'sections.categories.columns' => 'nullable|integer|min:1|max:6',
            'sections.categories.max_items' => 'nullable|integer|min:1|max:50',

            'sections.products.show_prices' => 'boolean',
            'sections.products.show_discounts' => 'boolean',
            'sections.products.show_add_to_cart' => 'boolean',

            'sections.promotions.enabled' => 'boolean',
            'sections.promotions.title' => 'nullable|string|max:255',
            'sections.promotions.layout' => 'nullable|string|in:grid,carousel',
            'sections.promotions.max_items' => 'nullable|integer|min:1|max:50',
            'sections.promotions.autoplay' => 'boolean',
            'sections.promotions.interval' => 'nullable|integer|min:1000|max:30000',

            'sections.footer.show_contact' => 'boolean',
            'sections.footer.show_social' => 'boolean',
            'sections.footer.show_payment_methods' => 'boolean',
            'sections.footer.text' => 'nullable|string|max:500',

            'sections.whatsapp.enabled' => 'boolean',
            'sections.whatsapp.phone' => 'nullable|string|max:30',
            'sections.whatsapp.greeting' => 'nullable|string|max:200',

            'sections.products.enabled' => 'boolean',
            'sections.products.title' => 'nullable|string|max:255',
            'sections.products.layout' => 'nullable|string|in:grid,list',
            'sections.products.columns' => 'nullable|integer|min:1|max:6',
            'sections.products.max_items' => 'nullable|integer|min:1|max:100',

            'sections.footer.enabled' => 'boolean',

            'seo.meta_title' => 'nullable|string|max:255',
            'seo.meta_description' => 'nullable|string|max:500',
            'seo.meta_keywords' => 'nullable|string|max:500',
            'seo.og_image' => 'nullable|string|max:500',
            'seo.og_title' => 'nullable|string|max:255',
            'seo.og_description' => 'nullable|string|max:500',

            'content.featured_text' => 'nullable|string|max:500',
            'content.custom_css' => 'nullable|string|max:5000',
            'content.custom_js' => 'nullable|string|max:5000',
        ];
    }
}
