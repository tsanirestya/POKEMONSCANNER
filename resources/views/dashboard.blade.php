<x-layouts.app title="Dashboard - PokemonScanner">
    <h1 class="page-title"><span class="pokeball-dot"></span> Dashboard</h1>
    <p class="mb-4 text-black/60">Halo, {{ auth()->user()->name }} (admin).</p>

    <livewire:dashboard.metrics />

    <livewire:admin.manual-input />
</x-layouts.app>
