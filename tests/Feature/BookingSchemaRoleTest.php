<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BookingSchemaRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    // --- Role SPG ---

    public function test_spg_login_diarahkan_ke_halaman_booking(): void
    {
        $spg = User::factory()->create([
            'password' => bcrypt('secret123'),
            'role' => 'spg',
        ]);

        Livewire::test('auth.login')
            ->set('email', $spg->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('booking'));

        $this->assertAuthenticatedAs($spg);
    }

    public function test_semua_role_bisa_akses_halaman_booking(): void
    {
        foreach (['admin', 'operator', 'spg'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/booking')->assertOk();
        }
    }

    public function test_spg_403_di_scan_laporan_dashboard_admin(): void
    {
        $spg = User::factory()->create(['role' => 'spg']);

        $this->actingAs($spg)->get('/scan')->assertForbidden();
        $this->actingAs($spg)->get('/laporan')->assertForbidden();
        $this->actingAs($spg)->get('/dashboard')->assertForbidden();
        $this->actingAs($spg)->get('/admin/users')->assertForbidden();
        $this->actingAs($spg)->post('/scan/submit')->assertForbidden();
        $this->actingAs($spg)->get('/scan/master-cache')->assertForbidden();
    }

    public function test_guest_diarahkan_ke_login_dari_booking(): void
    {
        $this->get('/booking')->assertRedirect(route('login'));
    }

    // --- CRUD user role SPG ---

    public function test_admin_bisa_membuat_user_spg(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test('admin.users')
            ->set('name', 'SPG Toko')
            ->set('email', 'spg@pokemonscanner.test')
            ->set('password', 'rahasia-123')
            ->set('role', 'spg')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('spg', User::where('email', 'spg@pokemonscanner.test')->first()?->role);
    }

    public function test_role_di_luar_daftar_ditolak(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test('admin.users')
            ->set('name', 'Siapa Saja')
            ->set('email', 'aneh@pokemonscanner.test')
            ->set('password', 'rahasia-123')
            ->set('role', 'super')
            ->call('save')
            ->assertHasErrors('role');
    }

    public function test_user_dengan_riwayat_booking_tidak_bisa_dihapus(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $spg = User::factory()->create(['role' => 'spg']);
        Booking::factory()->create(['user_id' => $spg->id]);

        Livewire::actingAs($admin)->test('admin.users')
            ->call('delete', $spg->id)
            ->assertHasErrors('delete');

        $this->assertNotNull(User::find($spg->id));
    }

    // --- Generator booking_code ---

    public function test_booking_code_sesuai_format(): void
    {
        $code = Booking::generateCode();

        $this->assertMatchesRegularExpression('/^BK-\d{6}-[A-Z0-9]{4}$/', $code);
        $this->assertSame('BK-'.now()->format('ymd'), substr($code, 0, 9));
    }

    public function test_booking_code_retry_saat_tabrakan(): void
    {
        $spg = User::factory()->create(['role' => 'spg']);

        Booking::create([
            'booking_code' => 'BK-'.now()->format('ymd').'-AAAA',
            'nomor_urut' => 1,
            'user_id' => $spg->id,
            'status' => Booking::STATUS_PRINTED,
        ]);

        Str::createRandomStringsUsingSequence(['aaaa', 'bbbb']);

        $this->assertSame('BK-'.now()->format('ymd').'-BBBB', Booking::generateCode());
    }

    // --- Nomor urut harian (DEC-25) ---

    public function test_nomor_urut_berurutan_dan_reset_harian(): void
    {
        Booking::factory()->create(['nomor_urut' => 7, 'created_at' => now()->subDay()]);

        $this->assertSame(1, Booking::nextNomorUrut());

        Booking::factory()->create(['nomor_urut' => Booking::nextNomorUrut()]);

        $this->assertSame(2, Booking::nextNomorUrut());
    }

    public function test_nomor_urut_berputar_ke_satu_setelah_999(): void
    {
        Booking::factory()->create(['nomor_urut' => 999]);

        $this->assertSame(1, Booking::nextNomorUrut());
    }

    public function test_nomor_urut_padded_tiga_digit(): void
    {
        $this->assertSame('007', Booking::factory()->create(['nomor_urut' => 7])->nomorUrutPadded());
        $this->assertSame('999', Booking::factory()->create(['nomor_urut' => 999])->nomorUrutPadded());
    }

    // --- Skema & isolasi stok ---

    public function test_booking_dan_item_tidak_menyentuh_stok(): void
    {
        $spg = User::factory()->create(['role' => 'spg']);
        $product = Product::factory()->create(['vendor_id' => Vendor::factory(), 'stok_sekarang' => 5]);

        $booking = Booking::create([
            'booking_code' => Booking::generateCode(),
            'nomor_urut' => Booking::nextNomorUrut(),
            'user_id' => $spg->id,
            'status' => Booking::STATUS_PRINTED,
        ]);

        $booking->items()->create(['product_id' => $product->id, 'qty' => 3]);

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(5, $product->refresh()->stok_sekarang);
        $this->assertSame(1, $booking->items()->count());
        $this->assertSame($spg->id, $booking->user->id);
        $this->assertSame(1, $product->bookingItems()->count());
    }

    public function test_hapus_booking_menghapus_item_cascade(): void
    {
        $product = Product::factory()->create(['vendor_id' => Vendor::factory()]);
        $booking = Booking::factory()->create();
        $booking->items()->create(['product_id' => $product->id, 'qty' => 2]);

        $booking->delete();

        $this->assertSame(0, BookingItem::count());
    }
}
