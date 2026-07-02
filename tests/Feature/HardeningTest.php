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

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_tidak_bisa_panggil_action_admin_livewire_langsung(): void
    {
        // NFR-SEC-01: otorisasi server-side, bukan hanya sembunyikan menu.
        // Route middleware hanya menjaga initial page load; component Livewire
        // admin-only harus menolak juga saat action dipanggil langsung (mis. lewat /livewire/update).
        $operator = User::factory()->create(['role' => 'operator']);

        Livewire::actingAs($operator)->test('admin.vendors')->assertForbidden();
    }

    public function test_operator_tidak_bisa_akses_manual_input_livewire_langsung(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        Livewire::actingAs($operator)->test('admin.manual-input')->assertForbidden();
    }

    public function test_scan_component_menolak_guest(): void
    {
        Livewire::test('scan')->assertForbidden();
    }

    public function test_reconcile_command_mendeteksi_selisih_dan_melapor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'tipe' => 'in',
            'qty' => 3,
            'metode' => 'manual',
            'user_id' => $admin->id,
        ]);

        // Simulasikan cache menyimpang dari ledger (mis. akibat bug/race condition lama).
        $product->update(['stok_sekarang' => 99]);

        $this->artisan('stock:reconcile')
            ->expectsOutputToContain('1 produk menyimpang')
            ->assertExitCode(0);

        $this->assertEquals(99, $product->fresh()->stok_sekarang);
    }

    public function test_reconcile_command_dengan_fix_memperbaiki_cache(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'tipe' => 'in',
            'qty' => 3,
            'metode' => 'manual',
            'user_id' => $admin->id,
        ]);

        $product->update(['stok_sekarang' => 99]);

        $this->artisan('stock:reconcile', ['--fix' => true])
            ->assertExitCode(0);

        $this->assertEquals(3, $product->fresh()->stok_sekarang);
    }

    public function test_scan_duplikat_scan_uuid_tidak_double_count(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);
        $uuid = (string) Str::uuid();

        $payload = [
            'barcode' => $product->barcode,
            'tipe' => 'in',
            'scan_uuid' => $uuid,
        ];

        $this->actingAs($operator)->postJson('/scan/submit', $payload)
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->actingAs($operator)->postJson('/scan/submit', $payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertEquals(1, $product->fresh()->stok_sekarang);
        $this->assertEquals(1, StockMovement::where('scan_uuid', $uuid)->count());
    }

    public function test_scan_submit_menolak_guest(): void
    {
        $this->postJson('/scan/submit', [
            'barcode' => '123',
            'tipe' => 'in',
            'scan_uuid' => (string) Str::uuid(),
        ])->assertUnauthorized();
    }
}
