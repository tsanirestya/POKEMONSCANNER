<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManualInputFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_input_masuk_manual_tanpa_alasan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test('admin.manual-input')
            ->set('product_id', $product->id)
            ->set('tipe', 'in')
            ->set('qty', 5)
            ->call('save');

        $this->assertEquals(5, $product->fresh()->stok_sekarang);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'tipe' => 'in',
            'qty' => 5,
            'metode' => 'manual',
            'alasan' => null,
            'user_id' => $admin->id,
        ]);
    }

    public function test_input_keluar_manual_tanpa_alasan_ditolak(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 10,
        ]);

        Livewire::actingAs($admin)
            ->test('admin.manual-input')
            ->set('product_id', $product->id)
            ->set('tipe', 'out')
            ->set('qty', 3)
            ->call('save')
            ->assertHasErrors(['alasan' => 'required']);

        $this->assertEquals(10, $product->fresh()->stok_sekarang);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_input_keluar_manual_dengan_alasan_mengurangi_stok(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 10,
        ]);

        Livewire::actingAs($admin)
            ->test('admin.manual-input')
            ->set('product_id', $product->id)
            ->set('tipe', 'out')
            ->set('qty', 3)
            ->set('alasan', 'Rusak saat display')
            ->call('save');

        $this->assertEquals(7, $product->fresh()->stok_sekarang);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'tipe' => 'out',
            'qty' => 3,
            'metode' => 'manual',
            'alasan' => 'Rusak saat display',
        ]);
    }

    public function test_operator_tidak_bisa_akses_dashboard(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $this->actingAs($operator)->get('/dashboard')->assertForbidden();
    }
}
