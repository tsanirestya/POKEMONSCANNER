<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScanSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_scan_masuk_menambah_stok(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);

        $response = $this->actingAs($operator)->postJson('/scan/submit', [
            'barcode' => $product->barcode,
            'tipe' => 'in',
            'scan_uuid' => (string) Str::uuid(),
        ]);

        $response->assertOk()->assertJson(['status' => 'success', 'stok' => 1]);
        $this->assertEquals(1, $product->fresh()->stok_sekarang);
    }

    public function test_submit_scan_barcode_tak_dikenal_ditolak(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $response = $this->actingAs($operator)->postJson('/scan/submit', [
            'barcode' => 'TIDAK-ADA',
            'tipe' => 'in',
            'scan_uuid' => (string) Str::uuid(),
        ]);

        $response->assertOk()->assertJson(['status' => 'rejected']);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_submit_scan_uuid_kembar_idempotent(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'stok_sekarang' => 0,
        ]);
        $uuid = (string) Str::uuid();

        $this->actingAs($operator)->postJson('/scan/submit', [
            'barcode' => $product->barcode,
            'tipe' => 'in',
            'scan_uuid' => $uuid,
        ]);

        $response = $this->actingAs($operator)->postJson('/scan/submit', [
            'barcode' => $product->barcode,
            'tipe' => 'in',
            'scan_uuid' => $uuid,
        ]);

        $response->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertEquals(1, $product->fresh()->stok_sekarang);
    }

    public function test_guest_ditolak_dari_scan_submit(): void
    {
        $this->postJson('/scan/submit', [
            'barcode' => 'X',
            'tipe' => 'in',
            'scan_uuid' => (string) Str::uuid(),
        ])->assertUnauthorized();
    }

    public function test_master_cache_mengembalikan_daftar_produk(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        Product::factory()->create([
            'vendor_id' => Vendor::factory(),
            'barcode' => '1234567890123',
            'nama_produk' => 'Booster Pack',
            'stok_sekarang' => 10,
        ]);

        $response = $this->actingAs($operator)->getJson('/scan/master-cache');

        $response->assertOk();
        $response->assertJsonFragment([
            'barcode' => '1234567890123',
            'nama_produk' => 'Booster Pack',
            'stok_sekarang' => 10,
        ]);
    }
}
