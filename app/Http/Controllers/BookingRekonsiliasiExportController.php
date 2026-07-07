<?php

namespace App\Http\Controllers;

use App\Exports\BookingRekonsiliasiExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookingRekonsiliasiExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = isset($validated['tanggal'])
            ? CarbonImmutable::parse($validated['tanggal'])
            : CarbonImmutable::today();

        $filename = sprintf('rekonsiliasi-booking-%s.xlsx', $tanggal->format('Y-m-d'));

        return Excel::download(new BookingRekonsiliasiExport($tanggal), $filename);
    }
}
