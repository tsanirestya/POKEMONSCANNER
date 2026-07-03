<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $sampai = isset($validated['sampai'])
            ? CarbonImmutable::parse($validated['sampai'])
            : CarbonImmutable::today();
        $dari = isset($validated['dari'])
            ? CarbonImmutable::parse($validated['dari'])
            : $sampai->subDays(29);

        $filename = sprintf('laporan-stok-%s_sd_%s.xlsx', $dari->format('Y-m-d'), $sampai->format('Y-m-d'));

        return Excel::download(new ReportExport($dari, $sampai), $filename);
    }
}
