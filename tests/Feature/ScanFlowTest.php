<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ScanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_masuk_menambah_stok_dan_movement(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);

        $uuid = (string) Str::uuid();

        Livewire::actingAs($operator)
            ->test('scan')
            ->call('scan', $product->barcode, 'in', $uuid);

        $this->assertEquals(1, $product->fresh()->stok_sekarang);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'tipe' => 'in',
            'qty' => 1,
            'metode' => 'scan',
            'scan_uuid' => $uuid,
            'user_id' => $operator->id,
        ]);
    }

    public function test_scan_keluar_mengurangi_stok(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 5,
        ]);

        Livewire::actingAs($operator)
            ->test('scan')
            ->call('scan', $product->barcode, 'out', (string) Str::uuid());

        $this->assertEquals(4, $product->fresh()->stok_sekarang);
    }

    public function test_barcode_tak_dikenal_ditolak_dan_tidak_membuat_movement(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        Livewire::actingAs($operator)
            ->test('scan')
            ->call('scan', 'BARCODE-TIDAK-ADA', 'in', (string) Str::uuid())
            ->assertDispatched('scan-rejected');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_scan_uuid_kembar_idempotent_tidak_double_count(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);

        $uuid = (string) Str::uuid();

        Livewire::actingAs($operator)->test('scan')->call('scan', $product->barcode, 'in', $uuid);

        Livewire::actingAs($operator)
            ->test('scan')
            ->call('scan', $product->barcode, 'in', $uuid)
            ->assertDispatched('scan-duplicate-server');

        $this->assertEquals(1, $product->fresh()->stok_sekarang);
        $this->assertEquals(1, StockMovement::where('scan_uuid', $uuid)->count());
    }
}
