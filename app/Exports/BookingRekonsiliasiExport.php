<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BookingRekonsiliasiExport implements WithMultipleSheets
{
    public function __construct(
        private CarbonImmutable $tanggal,
    ) {}

    public function sheets(): array
    {
        return [
            new BookingAgregatSheet($this->tanggal),
            new BookingDaftarSheet($this->tanggal),
        ];
    }
}
