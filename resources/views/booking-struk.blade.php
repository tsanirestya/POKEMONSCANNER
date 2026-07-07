<x-layouts.app title="Struk Booking - PokemonScanner">
    <div class="page max-w-lg!">
        <h1 class="page-title no-print"><span class="pokeball-dot"></span> Booking Tersimpan</h1>

        {{-- Struk thermal 58mm (FR-BOOK-02, DEC-24/25): saat print hanya blok ini yang tampil. --}}
        <div class="struk card">
            <p class="struk-toko">PokemonScanner</p>
            <p class="struk-judul">BOOKING ORDER</p>

            <hr class="struk-sep">

            <p class="struk-nomor">{{ $booking->nomorUrutPadded() }}</p>

            <div class="struk-barcode">
                <svg data-barcode="{{ $booking->booking_code }}"></svg>
            </div>
            <p class="struk-code">{{ $booking->booking_code }}</p>

            <hr class="struk-sep">

            <p>{{ $booking->created_at->format('d/m/Y H:i') }} WIB</p>
            <p>SPG: {{ $booking->user->name }}</p>

            <hr class="struk-sep">

            <table class="struk-items">
                @foreach ($booking->items as $item)
                    <tr>
                        <td>{{ $item->product->nama_produk }}</td>
                        <td class="qty">x{{ $item->qty }}</td>
                    </tr>
                @endforeach
            </table>

            <hr class="struk-sep">

            <p class="struk-total">TOTAL ITEM: {{ $booking->items->sum('qty') }}</p>

            <hr class="struk-sep">

            <p class="struk-footer">Bukan bukti pembayaran.<br>Bayar di kasir seperti biasa.</p>
        </div>

        <div class="no-print mt-4 flex gap-2">
            <button type="button" class="btn-primary flex-1" onclick="window.print()">Cetak Struk</button>
            <a href="{{ route('booking') }}" class="btn-secondary flex-1 text-center">+ Booking baru</a>
        </div>
    </div>

    <style>
        .struk {
            width: 100%;
            max-width: 260px;
            margin-inline: auto;
            background: #fff;
            color: #000;
            font-family: ui-monospace, 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            text-align: center;
        }

        .struk-toko { font-weight: 700; }
        .struk-judul { font-weight: 700; letter-spacing: 0.15em; }

        /* Nomor urut harian besar ala nomor antrian (DEC-25) */
        .struk-nomor {
            font-size: 46px;
            font-weight: 900;
            letter-spacing: 0.06em;
            line-height: 1.15;
        }

        .struk-barcode svg {
            display: block;
            width: 100%;
            height: auto;
        }

        .struk-code { letter-spacing: 0.08em; }

        .struk-sep {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .struk-items {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .struk-items td { padding: 1px 0; vertical-align: top; }
        .struk-items .qty { padding-left: 8px; text-align: right; white-space: nowrap; }

        .struk-total { font-weight: 700; }
        .struk-footer { font-size: 11px; }

        @media print {
            @page {
                size: 58mm auto;
                margin: 0;
            }

            /* Hanya struk yang tercetak: nav, tombol, dan chrome halaman disembunyikan */
            .navbar,
            .bottom-nav,
            .no-print {
                display: none !important;
            }

            body { background: #fff !important; }

            .page {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
            }

            .struk {
                width: 58mm;
                max-width: none;
                margin: 0;
                padding: 1mm 2mm 8mm; /* ruang bawah untuk sobek kertas */
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>

    @push('scripts')
        @vite(['resources/js/struk.js'])
    @endpush
</x-layouts.app>
