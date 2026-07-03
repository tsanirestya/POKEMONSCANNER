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

class ResetDataTest extends TestCase
{
    use RefreshDatabase;

    private function seedMovements(User $user): Product
    {
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 2,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'tipe' => 'in',
            'qty' => 3,
            'metode' => 'scan',
            'scan_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'tipe' => 'out',
            'qty' => 1,
            'metode' => 'manual',
            'alasan' => 'testing',
            'user_id' => $user->id,
        ]);

        return $product;
    }

    public function test_reset_menghapus_movement_dan_nolkan_stok_tanpa_sentuh_master(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->seedMovements($admin);

        Livewire::actingAs($admin)->test('admin.reset-data')
            ->set('konfirmasi', 'RESET')
            ->call('resetData')
            ->assertHasNoErrors();

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, $product->refresh()->stok_sekarang);
        $this->assertSame(1, Product::count());
        $this->assertSame(1, Vendor::count());
        $this->assertSame(1, User::count());
    }

    public function test_tanpa_konfirmasi_benar_tidak_menghapus_apapun(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedMovements($admin);

        Livewire::actingAs($admin)->test('admin.reset-data')
            ->set('konfirmasi', 'salah')
            ->call('resetData')
            ->assertHasErrors('konfirmasi');

        $this->assertSame(2, StockMovement::count());
    }

    public function test_operator_tidak_bisa_akses_reset(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        Livewire::actingAs($operator)->test('admin.reset-data')->assertForbidden();
    }
}
