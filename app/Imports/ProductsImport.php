<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ProductsImport implements ToCollection, WithCustomValueBinder, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    /** @var array<int, array{row: int, reason: string}> */
    public array $errors = [];

    private int $rowNumber = 1;

    public function bindValue(Cell $cell, $value): bool
    {
        // Force every cell to string so BARCODE keeps leading zeros / never
        // becomes scientific notation (DEC-06).
        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->rowNumber++;

            $barcode = trim((string) ($row['barcode'] ?? ''));
            $brand = $this->cleanMojibake((string) ($row['brand'] ?? ''));
            $namaProduk = $this->cleanMojibake((string) ($row['product_name'] ?? ''));

            if ($barcode === '') {
                $this->errors[] = ['row' => $this->rowNumber, 'reason' => 'Barcode kosong'];

                continue;
            }

            if ($namaProduk === '') {
                $this->errors[] = ['row' => $this->rowNumber, 'reason' => 'Nama produk kosong'];

                continue;
            }

            if ($brand === '') {
                $this->errors[] = ['row' => $this->rowNumber, 'reason' => 'Brand kosong'];

                continue;
            }

            $vendor = Vendor::firstOrCreate(['nama' => $brand]);

            $product = Product::where('barcode', $barcode)->first();

            if ($product) {
                $product->update([
                    'nama_produk' => $namaProduk,
                    'vendor_id' => $vendor->id,
                ]);
                $this->updated++;
            } else {
                Product::create([
                    'barcode' => $barcode,
                    'nama_produk' => $namaProduk,
                    'vendor_id' => $vendor->id,
                    'stok_sekarang' => 0,
                ]);
                $this->created++;
            }
        }
    }

    /**
     * Repair text that was UTF-8 encoded, misread as Latin-1, then re-encoded as UTF-8
     * (e.g. "POKÃÂ©MON" -> "POKÉMON"). Applied up to twice for double-encoded cases.
     */
    private function cleanMojibake(string $value): string
    {
        for ($i = 0; $i < 2; $i++) {
            $repaired = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');

            if ($repaired === false || $repaired === $value || ! mb_check_encoding($repaired, 'UTF-8')) {
                break;
            }

            $value = $repaired;
        }

        return trim($value);
    }
}
