<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_is_redirected_to_dashboard(): void
    {
        $admin = User::factory()->create([
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        Livewire::test('auth.login')
            ->set('email', $admin->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_operator_can_login_and_is_redirected_to_scan(): void
    {
        $operator = User::factory()->create([
            'password' => bcrypt('secret123'),
            'role' => 'operator',
        ]);

        Livewire::test('auth.login')
            ->set('email', $operator->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('scan'));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_operator_cannot_access_admin_dashboard(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $this->actingAs($operator)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_access_scan_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/scan')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/scan')->assertRedirect(route('login'));
    }

    /**
     * Regresi 403 "nyangkut": user login yang membuka `/` (ketik domain,
     * bookmark, PWA start_url) harus mendarat di halaman sesuai role-nya,
     * bukan dilempar ke /dashboard (403 untuk non-admin).
     */
    public function test_root_mengarahkan_user_login_ke_halaman_sesuai_role(): void
    {
        foreach (['admin' => 'dashboard', 'operator' => 'scan', 'spg' => 'booking'] as $role => $routeName) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/')
                ->assertRedirect(route($routeName));
        }
    }

    public function test_halaman_login_mengarahkan_user_login_ke_halaman_sesuai_role(): void
    {
        foreach (['admin' => 'dashboard', 'operator' => 'scan', 'spg' => 'booking'] as $role => $routeName) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/login')
                ->assertRedirect(route($routeName));
        }
    }
}
