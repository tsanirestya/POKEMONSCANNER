<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingRiwayatTest extends TestCase
{
    use RefreshDatabase;

    protected function spg(): User
    {
        return User::factory()->create(['role' => 'spg']);
    }

    protected function bookingFor(User $user, array $attributes = []): Booking
    {
        return Booking::factory()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    // --- Guard komponen & route (pola Fase 9) ---

    public function test_komponen_riwayat_menolak_guest(): void
    {
        Livewire::test('booking-riwayat')->assertForbidden();
    }

    public function test_semua_role_bisa_render_komponen_riwayat(): void
    {
        foreach (['admin', 'operator', 'spg'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            Livewire::actingAs($user)->test('booking-riwayat')->assertOk();
        }
    }

    public function test_route_riwayat_guest_redirect_login(): void
    {
        $this->get(route('booking.riwayat'))->assertRedirect(route('login'));
    }

    public function test_route_riwayat_bisa_diakses_semua_role(): void
    {
        foreach (['admin', 'operator', 'spg'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('booking.riwayat'))->assertOk();
        }
    }

    // --- Daftar booking (FR-BOOK-03) ---

    public function test_spg_hanya_melihat_booking_miliknya(): void
    {
        $spg = $this->spg();
        $milikSendiri = $this->bookingFor($spg);
        $milikOrangLain = $this->bookingFor($this->spg());

        Livewire::actingAs($spg)->test('booking-riwayat')
            ->assertSee($milikSendiri->booking_code)
            ->assertDontSee($milikOrangLain->booking_code);
    }

    public function test_admin_dan_operator_melihat_semua_booking_beserta_nama_spg(): void
    {
        $spg = $this->spg();
        $booking = $this->bookingFor($spg);

        foreach (['admin', 'operator'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))->test('booking-riwayat')
                ->assertSee($booking->booking_code)
                ->assertSee($spg->name);
        }
    }

    public function test_default_hari_ini_dan_filter_tanggal(): void
    {
        $spg = $this->spg();
        $hariIni = $this->bookingFor($spg);
        $kemarin = $this->bookingFor($spg, ['created_at' => now()->subDay()]);

        $component = Livewire::actingAs($spg)->test('booking-riwayat');

        $this->assertSame(today()->toDateString(), $component->get('tanggal'));

        $component
            ->assertSee($hariIni->booking_code)
            ->assertDontSee($kemarin->booking_code)
            ->set('tanggal', now()->subDay()->toDateString())
            ->assertSee($kemarin->booking_code)
            ->assertDontSee($hariIni->booking_code);
    }

    public function test_tanggal_tidak_valid_jatuh_ke_hari_ini(): void
    {
        $spg = $this->spg();
        $booking = $this->bookingFor($spg);

        Livewire::actingAs($spg)->test('booking-riwayat')
            ->set('tanggal', 'bukan-tanggal')
            ->assertOk()
            ->assertSee($booking->booking_code);
    }

    public function test_riwayat_menampilkan_item_status_dan_link_cetak_ulang(): void
    {
        $spg = $this->spg();
        $product = Product::factory()->create(['vendor_id' => Vendor::factory()]);
        $booking = $this->bookingFor($spg, ['nomor_urut' => 7]);
        $booking->items()->create(['product_id' => $product->id, 'qty' => 3]);

        Livewire::actingAs($spg)->test('booking-riwayat')
            ->assertSee('007')
            ->assertSee($product->nama_produk)
            ->assertSee('Tercetak')
            ->assertSee(route('booking.struk', $booking));
    }

    // --- Void (FR-BOOK-03: ubah status, bukan hapus) ---

    public function test_void_booking_printed_mengubah_status_bukan_hapus(): void
    {
        $spg = $this->spg();
        $booking = $this->bookingFor($spg);

        Livewire::actingAs($spg)->test('booking-riwayat')
            ->call('void', $booking->id)
            ->assertHasNoErrors();

        $this->assertSame(1, Booking::count());
        $this->assertSame(Booking::STATUS_VOID, $booking->refresh()->status);
    }

    public function test_spg_tidak_bisa_void_booking_spg_lain(): void
    {
        $booking = $this->bookingFor($this->spg());

        Livewire::actingAs($this->spg())->test('booking-riwayat')
            ->call('void', $booking->id)
            ->assertForbidden();

        $this->assertSame(Booking::STATUS_PRINTED, $booking->refresh()->status);
    }

    public function test_admin_dan_operator_bisa_void_booking_spg(): void
    {
        foreach (['admin', 'operator'] as $role) {
            $booking = $this->bookingFor($this->spg());

            Livewire::actingAs(User::factory()->create(['role' => $role]))->test('booking-riwayat')
                ->call('void', $booking->id)
                ->assertHasNoErrors();

            $this->assertSame(Booking::STATUS_VOID, $booking->refresh()->status);
        }
    }

    public function test_booking_sudah_dicek_keeper_atau_void_tidak_bisa_divoid(): void
    {
        $spg = $this->spg();

        foreach ([Booking::STATUS_CHECKED_OK, Booking::STATUS_CHECKED_SELISIH, Booking::STATUS_VOID] as $status) {
            $booking = $this->bookingFor($spg, ['status' => $status]);

            Livewire::actingAs($spg)->test('booking-riwayat')
                ->call('void', $booking->id)
                ->assertHasErrors('void');

            $this->assertSame($status, $booking->refresh()->status);
        }
    }

    public function test_void_tidak_menyentuh_stok(): void
    {
        $spg = $this->spg();
        $product = Product::factory()->create(['vendor_id' => Vendor::factory(), 'stok_sekarang' => 5]);
        $booking = $this->bookingFor($spg);
        $booking->items()->create(['product_id' => $product->id, 'qty' => 2]);

        Livewire::actingAs($spg)->test('booking-riwayat')->call('void', $booking->id);

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(5, $product->refresh()->stok_sekarang);
        $this->assertSame(1, $booking->items()->count(), 'item booking tidak ikut terhapus saat void');
    }
}
