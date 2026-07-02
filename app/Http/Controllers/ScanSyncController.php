<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanSyncController extends Controller
{
    public function submit(Request $request, ScanService $scanService): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'tipe' => ['required', 'in:in,out'],
            'scan_uuid' => ['required', 'uuid'],
        ]);

        $result = $scanService->record(
            $data['barcode'],
            $data['tipe'],
            $data['scan_uuid'],
            $request->user()->id,
        );

        return response()->json($result);
    }

    public function masterCache(): JsonResponse
    {
        $products = Product::query()
            ->select(['barcode', 'nama_produk', 'stok_sekarang'])
            ->get();

        return response()->json([
            'products' => $products,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}
