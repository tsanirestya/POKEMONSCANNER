<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ScanService
{
    /**
     * @return array{status: string, reason?: string, barcode?: string, namaProduk?: string, stok?: int}
     */
    public function record(string $barcode, string $tipe, string $scanUuid, int $userId): array
    {
        $product = Product::where('barcode', $barcode)->first();

        if (! $product) {
            return ['status' => 'rejected', 'reason' => 'Barcode tidak dikenal: '.$barcode];
        }

        try {
            $result = DB::transaction(function () use ($product, $tipe, $scanUuid, $userId) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'tipe' => $tipe,
                    'qty' => 1,
                    'metode' => 'scan',
                    'scan_uuid' => $scanUuid,
                    'user_id' => $userId,
                ]);

                $locked = Product::whereKey($product->id)->lockForUpdate()->first();
                $locked->stok_sekarang = $tipe === 'in'
                    ? $locked->stok_sekarang + 1
                    : $locked->stok_sekarang - 1;
                $locked->save();

                return $locked;
            });
        } catch (QueryException $e) {
            // scan_uuid UNIQUE clash = replay/duplikat sync, idempotent: abaikan diam-diam
            if (str_contains($e->getMessage(), 'scan_uuid')) {
                return ['status' => 'duplicate'];
            }

            throw $e;
        }

        return [
            'status' => 'success',
            'barcode' => $barcode,
            'namaProduk' => $result->nama_produk,
            'stok' => $result->stok_sekarang,
        ];
    }
}
