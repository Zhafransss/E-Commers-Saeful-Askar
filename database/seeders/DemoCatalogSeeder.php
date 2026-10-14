<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Sample catalog for local development before Jubelio is connected.
 * Values mirror the examples in docs/api/catalog.md and cart.md so pages look
 * the same with real API responses as with frontend mocks.
 * Jubelio IDs use the 900000+ range so a later real sync never collides.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $bike = Category::updateOrCreate(['jubelio_category_id' => 900003], ['name' => 'Sepeda Listrik', 'slug' => 'sepeda-listrik']);
        $battery = Category::updateOrCreate(['jubelio_category_id' => 900004], ['name' => 'Baterai', 'slug' => 'baterai']);
        Category::updateOrCreate(['jubelio_category_id' => 900009], ['name' => 'Sepeda Listrik Lipat', 'slug' => 'sepeda-listrik-lipat', 'parent_id' => $bike->id]);

        $this->product($bike, [
            'jubelio_item_group_id' => 901931,
            'name' => 'Sepeda Listrik Uwinfly C1 Pro',
            'slug' => 'sepeda-listrik-uwinfly-c1-pro',
            'brand_name' => 'Uwinfly',
            'description_html' => '<p>Motor 500W, baterai 48V 12Ah, jarak tempuh hingga 45 km.</p>',
            'min_qty' => 1,
            'max_qty' => 2,
            'package_weight' => 32000,
            'package_length' => 150,
            'package_width' => 30,
            'package_height' => 80,
            'variations' => [['label' => 'Warna', 'values' => ['Hitam', 'Putih']]],
        ], [
            ['jubelio_item_id' => 902976, 'sku' => 'UWF-C1PRO-HTM', 'name' => 'Sepeda Listrik Uwinfly C1 Pro - Hitam', 'variation_values' => [['label' => 'Warna', 'value' => 'Hitam']], 'price' => 6750000, 'available_qty' => 4, 'package_weight' => 32000],
            ['jubelio_item_id' => 902977, 'sku' => 'UWF-C1PRO-PTH', 'name' => 'Sepeda Listrik Uwinfly C1 Pro - Putih', 'variation_values' => [['label' => 'Warna', 'value' => 'Putih']], 'price' => 7250000, 'available_qty' => 0, 'package_weight' => 32000],
        ]);

        $this->product($battery, [
            'jubelio_item_group_id' => 901940,
            'name' => 'Baterai Lithium 48V',
            'slug' => 'baterai-lithium-48v',
            'brand_name' => 'Saeful Askar',
            'description_html' => '<p>Baterai lithium 48V untuk sepeda listrik, tersedia kapasitas 12Ah dan 20Ah.</p>',
            'package_weight' => 9500,
            'variations' => [['label' => 'Kapasitas', 'values' => ['12Ah', '20Ah']]],
        ], [
            ['jubelio_item_id' => 903001, 'sku' => 'BAT-48V-12AH', 'name' => 'Baterai Lithium 48V - 12Ah', 'variation_values' => [['label' => 'Kapasitas', 'value' => '12Ah']], 'price' => 2100000, 'available_qty' => 6, 'package_weight' => 6500],
            ['jubelio_item_id' => 903002, 'sku' => 'BAT-48V-20AH', 'name' => 'Baterai Lithium 48V - 20Ah', 'variation_values' => [['label' => 'Kapasitas', 'value' => '20Ah']], 'price' => 3400000, 'available_qty' => 1, 'package_weight' => 9500],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $variants
     */
    private function product(Category $category, array $attributes, array $variants): void
    {
        $product = Product::updateOrCreate(
            ['jubelio_item_group_id' => $attributes['jubelio_item_group_id']],
            $attributes + ['category_id' => $category->id, 'sell_unit' => 'Unit', 'is_active' => true, 'jubelio_last_modified' => now(), 'synced_at' => now()],
        );

        foreach ($variants as $variant) {
            $product->variants()->updateOrCreate(
                ['jubelio_item_id' => $variant['jubelio_item_id']],
                $variant + ['is_active' => true, 'stock_synced_at' => now()],
            );
        }
    }
}
