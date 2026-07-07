<?php

use App\Models\Booking;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'operator';

    public ?int $editingId = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,operator,spg'],
        ]);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);

            if ($user->id === auth()->id() && $validated['role'] !== 'admin') {
                $this->addError('role', 'Tidak bisa menurunkan role akun sendiri.');

                return;
            }

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                ...($validated['password'] ? ['password' => $validated['password']] : []),
            ]);
        } else {
            User::create($validated);
        }

        $this->reset(['name', 'email', 'password', 'role', 'editingId']);
        $this->role = 'operator';
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->password = '';
    }

    public function cancelEdit(): void
    {
        $this->reset(['name', 'email', 'password', 'editingId']);
        $this->role = 'operator';
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->addError('delete', 'Tidak bisa menghapus akun sendiri.');

            return;
        }

        if (StockMovement::where('user_id', $user->id)->exists()) {
            $this->addError('delete', 'User tidak bisa dihapus, sudah punya riwayat pergerakan stok.');

            return;
        }

        if (Booking::where('user_id', $user->id)->exists()) {
            $this->addError('delete', 'User tidak bisa dihapus, sudah punya riwayat booking.');

            return;
        }

        $user->delete();
    }

    public function with(): array
    {
        return [
            'users' => User::orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <h1 class="page-title"><span class="pokeball-dot"></span> User</h1>

    <div class="card">
        <h2 class="text-lg font-bold mb-3">{{ $editingId ? 'Ubah User' : 'Tambah User' }}</h2>

        @error('delete') <p class="error mb-2">{{ $message }}</p> @enderror

        <form wire:submit="save" class="flex flex-col gap-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <div class="field mb-0! w-full flex-1 sm:min-w-48">
                    <label for="name">Nama</label>
                    <input type="text" id="name" wire:model="name">
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field mb-0! w-full flex-1 sm:min-w-48">
                    <label for="email">Email</label>
                    <input type="email" id="email" wire:model="email">
                    @error('email') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="field mb-0! w-full flex-1 sm:min-w-48">
                    <label for="password">{{ $editingId ? 'Password Baru (kosongkan jika tidak diganti)' : 'Password' }}</label>
                    <input type="password" id="password" wire:model="password" autocomplete="new-password">
                    @error('password') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field mb-0! w-full sm:w-auto">
                    <label for="role">Role</label>
                    <select id="role" wire:model="role">
                        <option value="operator">Operator</option>
                        <option value="admin">Admin</option>
                        <option value="spg">SPG</option>
                    </select>
                    @error('role') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="flex w-full gap-2 sm:w-auto">
                    <button type="submit" class="btn-primary flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="save">
                        {{ $editingId ? 'Simpan Perubahan' : 'Tambah User' }}
                    </button>

                    @if ($editingId)
                        <button type="button" class="btn-secondary flex-1 sm:flex-none" wire:click="cancelEdit">Batal</button>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="table-wrap responsive-cards mt-4">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td data-label="Nama">{{ $user->name }} @if ($user->id === auth()->id())<span class="text-black/40 text-xs">(kamu)</span>@endif</td>
                        <td data-label="Email">{{ $user->email }}</td>
                        <td data-label="Role">{{ ['admin' => 'Admin', 'operator' => 'Operator', 'spg' => 'SPG'][$user->role] ?? $user->role }}</td>
                        <td class="cards-actions flex gap-2">
                            <button type="button" class="btn-secondary btn-sm" wire:click="edit({{ $user->id }})">Ubah</button>
                            @if ($user->id !== auth()->id())
                                <button type="button" class="btn-secondary btn-sm text-poke-red" wire:click="delete({{ $user->id }})" wire:confirm="Hapus user ini?">Hapus</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Belum ada user.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
