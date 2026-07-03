<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dc2626">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <title>{{ $title ?? 'PokemonScanner' }}</title>
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar">
        <span class="navbar-brand"><span class="pokeball-dot"></span> PokemonScanner</span>
        @auth
            <div class="hidden md:flex md:items-center md:gap-x-1">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                    <a href="{{ route('admin.vendors') }}" class="{{ request()->routeIs('admin.vendors') ? 'active' : '' }}">Vendor</a>
                    <a href="{{ route('admin.products') }}" class="{{ request()->routeIs('admin.products') ? 'active' : '' }}">Produk</a>
                @endif
                <a href="{{ route('scan') }}" class="{{ request()->routeIs('scan') ? 'active' : '' }}">Scan</a>
                @if (auth()->user()->isAdmin())
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" class="navbar-more {{ request()->routeIs('admin.products.import', 'admin.users') ? 'active' : '' }}" @click="open = !open">
                            Lainnya
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 transition" :class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="navbar-dropdown" x-show="open" x-cloak @click="open = false">
                            <a href="{{ route('admin.products.import') }}" class="{{ request()->routeIs('admin.products.import') ? 'active' : '' }}">Import Produk</a>
                            <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}">User</a>
                        </div>
                    </div>
                @endif
                @if (auth()->user()->isOperator())
                    <a href="{{ route('laporan') }}" class="{{ request()->routeIs('laporan') ? 'active' : '' }}">Laporan</a>
                @endif
            </div>
            <form method="POST" action="{{ route('logout') }}" class="navbar-spacer">
                @csrf
                <button type="submit" class="btn-ghost text-white/85! hover:bg-white/10!">Logout</button>
            </form>
        @endauth
    </nav>

    <main class="page">
        {{ $slot }}
    </main>

    @auth
        <nav class="bottom-nav" x-data="{ more: false }" @click.outside="more = false">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('admin.products') }}" class="{{ request()->routeIs('admin.products') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
                    Produk
                </a>
            @endif

            <a href="{{ route('scan') }}" class="nav-scan {{ request()->routeIs('scan') ? 'active' : '' }}">
                <span class="nav-scan-icon"><span class="pokeball-dot"></span></span>
                Scan
            </a>

            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.vendors') }}" class="{{ request()->routeIs('admin.vendors') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9 4.5 4h15L21 9"/><path d="M3 9h18v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9Z"/><path d="M9 20v-6h6v6"/></svg>
                    Vendor
                </a>
                <button type="button" class="bottom-nav-more-btn {{ request()->routeIs('admin.products.import', 'admin.users') ? 'active' : '' }}" @click="more = !more">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
                    Lainnya
                </button>

                <div class="bottom-nav-more" x-show="more" x-cloak x-transition.opacity.duration.150ms @click="more = false">
                    <a href="{{ route('admin.products.import') }}" class="{{ request()->routeIs('admin.products.import') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 19h16"/></svg>
                        Import Produk
                    </a>
                    <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                        User
                    </a>
                </div>
            @endif

            @if (auth()->user()->isOperator())
                <a href="{{ route('laporan') }}" class="{{ request()->routeIs('laporan') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5a1 1 0 0 1 1-1h9l5 5v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z"/><path d="M9 12h6M9 16h6M9 8h3"/></svg>
                    Laporan
                </a>
            @endif
        </nav>
    @endauth

    @livewireScripts
    @stack('scripts')
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset('sw.js') }}');
            });
        }
    </script>
</body>
</html>
