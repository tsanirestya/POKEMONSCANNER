<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingCartTest extends TestCase
{
    use RefreshDatabase;

    protected function spg(): User
    {
        return User::factory()->create(['role' => 'spg']);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(['vendor_id' => Vendor::factory()], $attributes));
    }

    // --- Guard komponen (pola Fase 9: route middleware tidak menjaga /livewire/update) ---

    public function test_komponen_booking_menolak_guest(): void
    {
        Livewire::test('booking')->assertForbidden();
    }

    public function test_semua_role_bisa_render_komponen_booking(): void
    {
        foreach (['admin', 'operator', 'spg'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            Livewire::actingAs($user)->test('booking')->assertOk();
        }
    }

    // --- Tambah item via scan (lookup-only) ---

    public function test_scan_barcode_dikenal_menambah_keranjang(): void
    {
        $product = $this->product(['barcode' => '4901234567894', 'stok_sekarang' => 7]);

        $component = Livewire::actingAs($this->spg())->test('booking')
            ->call('addByBarcode', '4901234567894')
            ->assertReturned([
                'status' => 'added',
                'namaProduk' => $product->nama_produk,
                'qty' => 1,
            ]);

        $this->assertSame(1, $component->get('cart')[$product->id]['qty']);
        $this->assertSame(7, $component->get('cart')[$product->id]['stok_sekarang']);
    }

    public function test_scan_barcode_sama_dua_kali_qty_bertambah(): void
    {
        $product = $this->product(['barcode' => '111222333']);

        $component = Livewire::actingAs($this->spg())->test('booking')
            ->call('addByBarcode', '111222333')
            ->call('addByBarcode', '111222333');

        $this->assertSame(2, $component->get('cart')[$product->id]['qty']);
    }

    public function test_scan_barcode_tak_dikenal_ditolak(): void
    {
        $component = Livewire::actingAs($this->spg())->test('booking')
            ->call('addByBarcode', '000000000000')
            ->assertReturned([
                'status' => 'rejected',
                'reason' => 'Barcode tidak dikenal: 000000000000',
            ]);

        $this->assertSame([], $component->get('cart'));
    }

    // --- Tambah item via pencarian nama (DEC-22: SPG boleh lihat stok) ---

    public function test_cari_nama_menampilkan_produk_dan_stok(): void
    {
        $this->product(['nama_produk' => 'POKÉMON Booster Pack', 'stok_sekarang' => 12]);

        Livewire::actingAs($this->spg())->test('booking')
            ->set('search', 'booster')
            ->assertSee('POKÉMON Booster Pack')
            ->assertSee('stok 12');
    }

    public function test_add_product_dari_hasil_cari_masuk_keranjang(): void
    {
        $product = $this->product(['nama_produk' => 'Plush Pikachu']);

        $component = Livewire::actingAs($this->spg())->test('booking')
            ->set('search', 'plush')
            ->call('addProduct', $product->id);

        $this->assertSame(1, $component->get('cart')[$product->id]['qty']);
        $this->assertSame('', $component->get('search'));
    }

    // --- Keranjang: qty & hapus ---

    public function test_ubah_qty_dan_hapus_item(): void
    {
        $product = $this->product();

        $component = Livewire::actingAs($this->spg())->test('booking')
            ->call('addByBarcode', $product->barcode)
            ->call('incrementQty', $product->id)
            ->call('incrementQty', $product->id);

        $this->assertSame(3, $component->get('cart')[$product->id]['qty']);

        $component->call('decrementQty', $product->id);
        $this->assertSame(2, $component->get('cart')[$product->id]['qty']);

        $component->call('setQty', $product->id, 0);
        $this->assertSame(1, $component->get('cart')[$product->id]['qty'], 'qty minimal 1');

        $component->call('setQty', $product->id, 15);
        $this->assertSame(15, $component->get('cart')[$product->id]['qty']);

        $component->call('removeItem', $product->id);
        $this->assertSame([], $component->get('cart'));
    }

    public function test_decrement_tidak_turun_di_bawah_satu(): void
    {
        $product = $this->product();

        $component = Livewire::actingAs($this->spg())->test('booking')
            ->call('addByBarcode', $product->barcode)
            ->call('decrementQty', $product->id);

        $this->assertSame(1, $component->get('cart')[$product->id]['qty']);
    }

    // --- Simpan booking ---

    public function test_simpan_booking_membuat_booking_dan_item(): void
    {
        $spg = $this->spg();
        $productA = $this->product();
        $productB = $this->product();

        Livewire::actingAs($spg)->test('booking')
            ->call('addByBarcode', $productA->barcode)
            ->call('addByBarcode', $productA->barcode)
            ->call('addByBarcode', $productB->barcode)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $booking = Booking::first();

        $this->assertNotNull($booking);
        $this->assertMatchesRegularExpression('/^BK-\d{6}-[A-Z0-9]{4}$/', $booking->booking_code);
        $this->assertSame(1, $booking->nomor_urut);
        $this->assertSame($spg->id, $booking->user_id);
        $this->assertSame(Booking::STATUS_PRINTED, $booking->status);
        $this->assertSame(2, $booking->items()->count());
        $this->assertSame(2, $booking->items()->where('product_id', $productA->id)->first()->qty);
        $this->assertSame(1, $booking->items()->where('product_id', $productB->id)->first()->qty);
    }

    public function test_simpan_booking_tidak_menyentuh_stok(): void
    {
        $product = $this->product(['stok_sekarang' => 9]);

        Livewire::actingAs($this->spg())->test('booking')
            ->call('addByBarcode', $product->barcode)
            ->call('setQty', $product->id, 4)
            ->call('save');

        $this->assertSame(1, Booking::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(9, $product->refresh()->stok_sekarang);
    }

    public function test_simpan_keranjang_kosong_ditolak(): void
    {
        Livewire::actingAs($this->spg())->test('booking')
            ->call('save')
            ->assertHasErrors('cart');

        $this->assertSame(0, Booking::count());
    }

    public function test_simpan_dengan_produk_terhapus_ditolak_tanpa_booking_yatim(): void
    {
        $product = $this->product();

        $component = Livewire::actingAs($this->spg())->test('booking')
            ->call('addByBarcode', $product->barcode);

        $product->delete();

        $component->call('save')->assertHasErrors('cart');

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, BookingItem::count());
        $this->assertSame([], $component->get('cart'));
    }

    // --- Layar struk (print thermal 58mm, BO-2) ---

    public function test_struk_bisa_dilihat_pemilik_dan_keeper(): void
    {
        $spg = $this->spg();
        $product = $this->product();
        $booking = Booking::factory()->create(['user_id' => $spg->id, 'nomor_urut' => 7]);
        $booking->items()->create(['product_id' => $product->id, 'qty' => 2]);

        $url = route('booking.struk', $booking);

        $this->actingAs($spg)->get($url)
            ->assertOk()
            ->assertSee('007')
            ->assertSee($booking->booking_code)
            ->assertSee($product->nama_produk)
            // Elemen barcode Code128 (dirender client-side oleh struk.js, DEC-24)
            ->assertSee('data-barcode="'.$booking->booking_code.'"', false)
            ->assertSee($spg->name)
            ->assertSee('TOTAL ITEM: 2')
            ->assertSee('window.print()', false);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($url)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'operator']))->get($url)->assertOk();
    }

    public function test_struk_spg_lain_403(): void
    {
        $booking = Booking::factory()->create(['user_id' => $this->spg()->id]);

        $this->actingAs($this->spg())->get(route('booking.struk', $booking))->assertForbidden();
    }
}
