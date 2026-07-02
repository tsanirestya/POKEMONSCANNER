<?php

namespace Tests\Feature;

use App\Imports\ProductsImport;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductsImportTest extends TestCase
{
    use RefreshDatabase;

    /** @return Collection<int, Collection<string, string>> */
    private function rows(): Collection
    {
        return collect([
            collect(['no' => '1', 'brand' => 'BrandA', 'barcode' => '1234567890123', 'product_name' => 'Produk A']),
        ]);
    }

    public function test_import_berulang_tidak_mengubah_stok_yang_sudah_bergerak(): void
    {
        // NFR-REL-04: import berulang aman terhadap stok (idempotent).
        $import = new ProductsImport;
        $import->collection($this->rows());

        $this->assertEquals(1, $import->created);
        $this->assertEquals(0, $import->updated);

        $product = Product::where('barcode', '1234567890123')->firstOrFail();
        $this->assertEquals(0, $product->stok_sekarang);

        // Stok bergerak lewat jalur lain (bukan import).
        $product->update(['stok_sekarang' => 42]);

        // Import ulang file sama.
        $import2 = new ProductsImport;
        $import2->collection($this->rows());

        $this->assertEquals(0, $import2->created);
        $this->assertEquals(1, $import2->updated);

        $this->assertEquals(42, $product->fresh()->stok_sekarang);
    }
}
