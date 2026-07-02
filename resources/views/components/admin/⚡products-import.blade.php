<?php

use App\Imports\ProductsImport;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?int $created = null;

    public ?int $updated = null;

    /** @var array<int, array{row: int, reason: string}> */
    public array $errors_list = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function import(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $import = new ProductsImport;

        Excel::import($import, $this->file);

        $this->created = $import->created;
        $this->updated = $import->updated;
        $this->errors_list = $import->errors;

        $this->reset('file');
    }
};
?>

<div>
    <h1 class="page-title"><span class="pokeball-dot"></span> Import Produk</h1>

    <div class="card">
        <h2 class="text-lg font-bold mb-2">Import Master Produk (Excel)</h2>

        <p class="text-sm text-black/60 mb-3">Kolom wajib: <code class="rounded bg-black/5 px-1">NO</code>, <code class="rounded bg-black/5 px-1">BRAND</code>, <code class="rounded bg-black/5 px-1">BARCODE</code>, <code class="rounded bg-black/5 px-1">PRODUCT NAME</code>. Import tidak pernah mengubah stok.</p>

        <form wire:submit="import" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="field mb-0! w-full sm:w-auto">
                <label for="file">File Excel</label>
                <input type="file" id="file" wire:model="file" accept=".xlsx,.xls,.csv" class="w-full">
                @error('file') <span class="error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="import">
                Import
            </button>
        </form>
    </div>

    @if (! is_null($created))
        <div class="card mt-4">
            <h3 class="text-lg font-bold mb-2">Ringkasan</h3>
            <p class="mb-3">
                <span class="badge-in">{{ $created }} dibuat</span>
                <span class="badge bg-black/5 text-black/70">{{ $updated }} diupdate</span>
                <span class="badge-out">{{ count($errors_list) }} error</span>
            </p>

            @if (count($errors_list) > 0)
                <div class="table-wrap responsive-cards">
                    <table>
                        <thead>
                            <tr>
                                <th>Baris</th>
                                <th>Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($errors_list as $error)
                                <tr>
                                    <td data-label="Baris">{{ $error['row'] }}</td>
                                    <td data-label="Alasan">{{ $error['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
