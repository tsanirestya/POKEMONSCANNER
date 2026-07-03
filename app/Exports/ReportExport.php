<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements WithMultipleSheets
{
    public function __construct(
        private CarbonImmutable $dari,
        private CarbonImmutable $sampai,
    ) {}

    public function sheets(): array
    {
        return [
            new StokSheet,
            new PergerakanSheet($this->dari, $this->sampai),
        ];
    }
}
