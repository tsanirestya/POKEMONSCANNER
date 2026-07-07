<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PRINTED = 'printed';

    public const STATUS_VOID = 'void';

    public const STATUS_CHECKED_OK = 'checked_ok';

    public const STATUS_CHECKED_SELISIH = 'checked_selisih';

    protected $fillable = [
        'booking_code',
        'nomor_urut',
        'user_id',
        'status',
        'catatan_keeper',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Generate booking_code unik format BK-YYMMDD-XXXX (X = alfanumerik acak).
     * Retry saat tabrakan; UNIQUE constraint DB tetap jadi backstop terakhir.
     */
    public static function generateCode(): string
    {
        $prefix = 'BK-'.now()->format('ymd').'-';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $prefix.strtoupper(Str::random(4));

            if (! static::where('booking_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal menghasilkan booking_code unik setelah 10 percobaan.');
    }

    /**
     * Nomor urut harian 1–999, reset tiap hari (WIB), berputar ke 1 setelah 999 (DEC-25).
     * Panggil di dalam transaksi DB saat menyimpan booking agar lockForUpdate efektif.
     */
    public static function nextNomorUrut(): int
    {
        $last = static::whereDate('created_at', today())->lockForUpdate()->max('nomor_urut');

        return ($last === null || $last >= 999) ? 1 : $last + 1;
    }

    /**
     * Nomor urut 3 digit untuk struk, mis. 7 => "007".
     */
    public function nomorUrutPadded(): string
    {
        return str_pad((string) $this->nomor_urut, 3, '0', STR_PAD_LEFT);
    }
}
