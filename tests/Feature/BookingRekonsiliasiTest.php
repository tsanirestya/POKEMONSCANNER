<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class BookingRekonsiliasiTest extends TestCase
{
    use RefreshDatabase;

    protected function keeper(string $role = 'operator'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(['vendor_id' => Vendor::factory()], $attributes));
    }

    protected function bookingWithItem(Product $product, int $qty, array $attributes = []): Booking
    {
        $booking = Booking::factory()->create(array_merge(
            ['user_id' => User::factory()->create(['role' => 'spg'])->id],
            $attributes,
        ));
        $booking->items()->create(['product_id' => $product->id, 'qty' => $qty]);

        return $booking;
    }

    protected function movementOut(Product $product, int $qty): StockMovement
    {
        return StockMovement::create([
            'product_id' => $product->id,
            'tipe' => 'out',
            'qty' => $qty,
            'metode' => 'scan',
            'user_id' => User::factory()->create(['role' => 'operator'])->id,
        ]);
    }

    // --- Guard komponen & route (FR-BOOK-04 AC: SPG 403, pola Fase 9) ---

    public function test_komponen_rekonsiliasi_menolak_guest_dan_spg(): void
    {
        Livewire::test('booking-rekonsiliasi')->assertForbidden();

        Livewire::actingAs(User::factory()->create(['role' => 'spg']))
            ->test('booking-rekonsiliasi')
            ->assertForbidden();
    }

    public function test_admin_dan_operator_bisa_render_komponen_rekonsiliasi(): void
    {
        foreach (['admin', 'operator'] as $role) {
            Livewire::actingAs($this->keeper($role))->test('booking-rekonsiliasi')->assertOk();
        }
    }

    public function test_route_rekonsiliasi_guard_per_role(): void
    {
        $this->get(route('booking.rekonsiliasi'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'spg']))
            ->get(route('booking.rekonsiliasi'))->assertForbidden();

        foreach (['admin', 'operator'] as $role) {
            $this->actingAs($this->keeper($role))
                ->get(route('booking.rekonsiliasi'))->assertOk();
        }
    }

    // --- Agregat per produk (FR-BOOK-04) ---

    public function test_agregat_qty_terbooking_vs_keluar_ledger(): void
    {
        $product = $this->product(['nama_produk' => 'Produk Agregat']);
        $this->bookingWithItem($product, 2);
        $this->bookingWithItem($product, 1);
        $this->movementOut($product, 2);

        Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
            ->assertSee('Produk Agregat')
            ->assertSeeHtml('>3<')   // ter-booking 2+1
            ->assertSeeHtml('>2<')   // keluar ledger
            ->assertSeeHtml('>+1<'); // selisih
    }

    public function test_agregat_memuat_produk_yang_hanya_ada_di_satu_sisi(): void
    {
        $hanyaBooking = $this->product(['nama_produk' => 'Hanya Dibooking']);
        $hanyaLedger = $this->product(['nama_produk' => 'Hanya Keluar Ledger']);
        $this->bookingWithItem($hanyaBooking, 2);
        $this->movementOut($hanyaLedger, 4);

        Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
            ->assertSee('Hanya Dibooking')
            ->assertSee('Hanya Keluar Ledger')
            ->assertSeeHtml('>-4<'); // barang keluar tanpa booking
    }

    public function test_booking_void_tidak_dihitung_di_agregat(): void
    {
        $product = $this->product(['nama_produk' => 'Produk Void']);
        $this->bookingWithItem($product, 5, ['status' => Booking::STATUS_VOID]);
        $this->bookingWithItem($product, 1);

        Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
            ->assertSee('Produk Void')
            ->assertSeeHtml('>1<')
            ->assertDontSeeHtml('>6<');
    }

    public function test_filter_tanggal_default_hari_ini(): void
    {
        $produkHariIni = $this->product(['nama_produk' => 'Booking Hari Ini']);
        $produkKemarin = $this->product(['nama_produk' => 'Booking Kemarin']);
        $this->bookingWithItem($produkHariIni, 1);
        $kemarin = $this->bookingWithItem($produkKemarin, 1, ['created_at' => now()->subDay()]);

        $component = Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi');

        $this->assertSame(today()->toDateString(), $component->get('tanggal'));

        $component
            ->assertSee('Booking Hari Ini')
            ->assertDontSee('Booking Kemarin')
            ->set('tanggal', now()->subDay()->toDateString())
            ->assertSee('Booking Kemarin')
            ->assertSee($kemarin->booking_code)
            ->assertDontSee('Booking Hari Ini');
    }

    // --- Tandai hasil rekonsiliasi ---

    public function test_tandai_checked_ok_dan_selisih_dengan_catatan(): void
    {
        $product = $this->product();

        foreach ([Booking::STATUS_CHECKED_OK, Booking::STATUS_CHECKED_SELISIH] as $status) {
            $booking = $this->bookingWithItem($product, 1);

            Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
                ->set("catatan.{$booking->id}", 'kurang 1 di rak')
                ->call('tandai', $booking->id, $status)
                ->assertHasNoErrors();

            $booking->refresh();
            $this->assertSame($status, $booking->status);
            $this->assertSame('kurang 1 di rak', $booking->catatan_keeper);
        }
    }

    public function test_tandai_tanpa_catatan_menyimpan_null(): void
    {
        $booking = $this->bookingWithItem($this->product(), 1);

        Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
            ->call('tandai', $booking->id, Booking::STATUS_CHECKED_OK)
            ->assertHasNoErrors();

        $this->assertNull($booking->refresh()->catatan_keeper);
    }

    public function test_booking_void_tidak_bisa_ditandai(): void
    {
        $booking = $this->bookingWithItem($this->product(), 1, ['status' => Booking::STATUS_VOID]);

        Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
            ->call('tandai', $booking->id, Booking::STATUS_CHECKED_OK)
            ->assertHasErrors('tandai');

        $this->assertSame(Booking::STATUS_VOID, $booking->refresh()->status);
    }

    public function test_tandai_menolak_status_di_luar_checked(): void
    {
        $booking = $this->bookingWithItem($this->product(), 1);

        foreach ([Booking::STATUS_VOID, Booking::STATUS_PRINTED, 'ngawur'] as $status) {
            Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
                ->call('tandai', $booking->id, $status)
                ->assertStatus(400);
        }

        $this->assertSame(Booking::STATUS_PRINTED, $booking->refresh()->status);
    }

    public function test_tandai_tidak_menyentuh_stok(): void
    {
        $product = $this->product(['stok_sekarang' => 7]);
        $booking = $this->bookingWithItem($product, 3);

        Livewire::actingAs($this->keeper())->test('booking-rekonsiliasi')
            ->call('tandai', $booking->id, Booking::STATUS_CHECKED_SELISIH);

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(7, $product->refresh()->stok_sekarang);
    }

    // --- Export Excel (reuse Maatwebsite, DEC-19) ---

    public function test_export_rekonsiliasi_terunduh_dengan_nama_file_bertanggal(): void
    {
        Excel::fake();

        $this->bookingWithItem($this->product(), 1);

        $this->actingAs($this->keeper())
            ->get(route('booking.rekonsiliasi.export', ['tanggal' => today()->toDateString()]))
            ->assertOk();

        Excel::assertDownloaded('rekonsiliasi-booking-'.today()->toDateString().'.xlsx');
    }

    public function test_export_nyata_menghasilkan_file_xlsx(): void
    {
        $product = $this->product();
        $this->bookingWithItem($product, 2);
        $this->movementOut($product, 1);

        $this->actingAs($this->keeper())
            ->get(route('booking.rekonsiliasi.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_ditolak_untuk_spg_dan_guest(): void
    {
        $this->get(route('booking.rekonsiliasi.export'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'spg']))
            ->get(route('booking.rekonsiliasi.export'))->assertForbidden();
    }

    // --- Metrik dashboard (FR-BOOK-05) ---

    public function test_metrik_dashboard_menghitung_booking_dan_item_hari_ini(): void
    {
        $product = $this->product();
        $this->bookingWithItem($product, 2);
        $this->bookingWithItem($product, 3);
        // Void & kemarin tidak dihitung.
        $this->bookingWithItem($product, 10, ['status' => Booking::STATUS_VOID]);
        $this->bookingWithItem($product, 10, ['created_at' => now()->subDay()]);

        Livewire::actingAs($this->keeper('admin'))->test('dashboard.metrics')
            ->assertSee('Booking Hari Ini')
            ->assertSee('Item Ter-booking Hari Ini')
            ->assertViewHas('bookingHariIni', 2)
            ->assertViewHas('itemTerbookingHariIni', 5);
    }
}
