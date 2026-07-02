<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileStock extends Command
{
    protected $signature = 'stock:reconcile {--fix : Perbaiki stok_sekarang yang menyimpang dari ledger}';

    protected $description = 'Bandingkan cache stok_sekarang tiap produk dengan agregasi ledger stock_movements';

    public function handle(): int
    {
        $ledgerTotals = StockMovement::query()
            ->select('product_id', DB::raw("SUM(CASE WHEN tipe = 'in' THEN qty ELSE -qty END) as total"))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $mismatches = [];

        Product::query()->orderBy('nama_produk')->each(function (Product $product) use ($ledgerTotals, &$mismatches) {
            $expected = (int) ($ledgerTotals[$product->id] ?? 0);

            if ($expected !== $product->stok_sekarang) {
                $mismatches[] = [$product->id, $product->nama_produk, $product->stok_sekarang, $expected];
            }
        });

        if (empty($mismatches)) {
            $this->info('Semua stok_sekarang cocok dengan ledger.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Produk', 'Cache (stok_sekarang)', 'Ledger (agregasi)'], $mismatches);

        if ($this->option('fix')) {
            DB::transaction(function () use ($mismatches) {
                foreach ($mismatches as [$id, , , $expected]) {
                    Product::whereKey($id)->lockForUpdate()->update(['stok_sekarang' => $expected]);
                }
            });

            $this->info(count($mismatches).' produk diperbaiki.');
        } else {
            $this->warn(count($mismatches).' produk menyimpang. Jalankan dengan --fix untuk memperbaiki.');
        }

        return self::SUCCESS;
    }
}
