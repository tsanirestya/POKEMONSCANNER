<div class="card mt-4">
    <h2 class="font-bold mb-2">Export Report (Excel)</h2>
    <p class="text-sm text-black/60 mb-3">Sheet 1: stok saat ini. Sheet 2: pergerakan stok pada rentang tanggal (default 30 hari terakhir).</p>

    <form method="GET" action="{{ route('laporan.export') }}" class="flex flex-wrap items-end gap-4">
        <div class="field mb-0!">
            <label for="export-dari">Dari</label>
            <input type="date" id="export-dari" name="dari" value="{{ now()->subDays(29)->format('Y-m-d') }}">
        </div>
        <div class="field mb-0!">
            <label for="export-sampai">Sampai</label>
            <input type="date" id="export-sampai" name="sampai" value="{{ now()->format('Y-m-d') }}">
        </div>
        <button type="submit" class="btn-primary">Download .xlsx</button>
    </form>
</div>
