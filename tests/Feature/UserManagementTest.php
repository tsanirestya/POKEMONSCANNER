<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_membuat_user_baru(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test('admin.users')
            ->set('name', 'Operator Gudang')
            ->set('email', 'operator@pokemonscanner.test')
            ->set('password', 'rahasia-123')
            ->set('role', 'operator')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'operator@pokemonscanner.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('operator', $user->role);
        $this->assertTrue(Hash::check('rahasia-123', $user->password));
    }

    public function test_edit_tanpa_password_tidak_mengubah_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'operator', 'password' => 'password-lama']);

        Livewire::actingAs($admin)->test('admin.users')
            ->call('edit', $user->id)
            ->set('name', 'Nama Baru')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertTrue(Hash::check('password-lama', $user->password));
    }

    public function test_operator_tidak_bisa_akses_component_users(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        Livewire::actingAs($operator)->test('admin.users')->assertForbidden();
        $this->actingAs($operator)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_tidak_bisa_hapus_akun_sendiri(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test('admin.users')
            ->call('delete', $admin->id)
            ->assertHasErrors('delete');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_admin_tidak_bisa_menurunkan_role_sendiri(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test('admin.users')
            ->call('edit', $admin->id)
            ->set('role', 'operator')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertSame('admin', $admin->refresh()->role);
    }

    public function test_user_dengan_riwayat_movement_tidak_bisa_dihapus(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->create(['vendor_id' => Vendor::factory()]);

        StockMovement::create([
            'product_id' => $product->id,
            'tipe' => 'in',
            'qty' => 1,
            'metode' => 'manual',
            'user_id' => $operator->id,
        ]);

        Livewire::actingAs($admin)->test('admin.users')
            ->call('delete', $operator->id)
            ->assertHasErrors('delete');

        $this->assertNotNull(User::find($operator->id));
    }

    public function test_email_duplikat_ditolak(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'dupe@pokemonscanner.test']);

        Livewire::actingAs($admin)->test('admin.users')
            ->set('name', 'Siapa Saja')
            ->set('email', 'dupe@pokemonscanner.test')
            ->set('password', 'rahasia-123')
            ->set('role', 'operator')
            ->call('save')
            ->assertHasErrors('email');
    }
}
