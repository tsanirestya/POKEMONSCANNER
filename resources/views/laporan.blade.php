<x-layouts.app title="Laporan - PokemonScanner">
    <h1 class="page-title"><span class="pokeball-dot"></span> Laporan</h1>
    <p class="mb-4 text-black/60">Halo, {{ auth()->user()->name }}. Ringkasan stok &amp; pergerakan (read-only).</p>

    <livewire:dashboard.metrics />

    <x-export-report />
</x-layouts.app>
