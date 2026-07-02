<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'barcode' => fake()->unique()->ean13(),
            'nama_produk' => fake()->words(3, true),
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ];
    }
}
