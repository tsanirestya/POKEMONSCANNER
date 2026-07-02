<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate();

        if (! Auth::attempt($credentials, $this->remember)) {
            $this->addError('email', 'Email atau password salah.');

            return;
        }

        session()->regenerate();

        $user = Auth::user();

        $this->redirect($user->isAdmin() ? route('dashboard') : route('scan'), navigate: true);
    }
};
?>

<div class="login-card card">
    <h1><span class="pokeball-dot"></span> PokemonScanner</h1>

    <form wire:submit="login">
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" wire:model="email" autofocus>
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" wire:model="password">
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" wire:model="remember"> Ingat saya
            </label>
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="login">
            Masuk
        </button>
    </form>
</div>
