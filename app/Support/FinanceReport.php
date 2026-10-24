<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\MembershipPurchase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Angka keuangan dashboard admin (halaman Keuangan + unduhan CSV). Uang masuk dihitung dari
 * booking LUNAS berdasarkan waktu bayar (paid_at), dalam zona WIB.
 */
class FinanceReport
{
    public const PERIODS = [
        'hari-ini' => 'Hari ini',
        '7-hari' => '7 hari',
        '30-hari' => '30 hari',
        'bulan-ini' => 'Bulan ini',
        'bulan-lalu' => 'Bulan lalu',
        'semua' => 'Semua',
    ];

    /**
     * Estimasi potongan Sumopod per transaksi QRIS otomatis (dashboard Sumopod: 0,7% + Rp300).
     * Biaya penarikan dana (Rp2.500/withdraw) tidak per transaksi, jadi tidak dihitung di sini.
     */
    public const SUMOPOD_FEE_PERCENT = 0.7;

    public const SUMOPOD_FEE_FLAT = 300;

    /** Nominal yang benar-benar dibayar -- sama dengan Booking::paidAmount(), versi SQL. */
    private const PAID_SQL = 'total + admin_fee + CASE WHEN sumopod_payment_id IS NULL THEN COALESCE(unique_code, 0) ELSE 0 END';

    public readonly string $period;

    public readonly ?CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    public function __construct(string $period)
    {
        $this->period = array_key_exists($period, self::PERIODS) ? $period : '30-hari';
        $now = CarbonImmutable::now((string) config('app.display_timezone'));

        [$from, $to] = match ($this->period) {
            'hari-ini' => [$now->startOfDay(), $now],
            '7-hari' => [$now->subDays(6)->startOfDay(), $now],
            'bulan-ini' => [$now->startOfMonth(), $now],
            'bulan-lalu' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'semua' => [null, $now],
            default => [$now->subDays(29)->startOfDay(), $now],
        };

        $this->from = $from?->utc();
        $this->to = $to->utc();
    }

    /** @return Builder<Booking> booking lunas yang dibayar dalam periode */
    public function paidBookings(): Builder
    {
        // Kolom dikualifikasi -- perMatch() menggabungkan tabel matches yang juga punya kolom status.
        return Booking::query()
            ->where('bookings.is_sandbox', false)
            ->where('bookings.status', 'paid')
            ->when($this->from, fn ($q) => $q->where('bookings.paid_at', '>=', $this->from))
            ->where('bookings.paid_at', '<=', $this->to);
    }

    /** @return array<string, int|float> */
    public function summary(): array
    {
        $feeSql = 'CEIL((total + admin_fee) * '.self::SUMOPOD_FEE_PERCENT.' / 100) + '.self::SUMOPOD_FEE_FLAT;

        $row = (array) $this->paidBookings()->toBase()->selectRaw(
            'COUNT(*) AS transaksi, COALESCE(SUM(quantity), 0) AS slot, COALESCE(SUM(total), 0) AS tiket, '
            .'COALESCE(SUM(admin_fee), 0) AS biaya_admin, '
            .'COALESCE(SUM(CASE WHEN sumopod_payment_id IS NULL THEN COALESCE(unique_code, 0) ELSE 0 END), 0) AS kode_unik, '
            .'COALESCE(SUM(CASE WHEN sumopod_payment_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS sumopod_transaksi, '
            .'COALESCE(SUM(CASE WHEN sumopod_payment_id IS NOT NULL THEN total + admin_fee ELSE 0 END), 0) AS sumopod_masuk, '
            ."COALESCE(SUM(CASE WHEN sumopod_payment_id IS NOT NULL THEN {$feeSql} ELSE 0 END), 0) AS potongan_sumopod"
        )->first();

        $s = array_map('intval', $row);
        $s['uang_masuk'] = $s['tiket'] + $s['biaya_admin'] + $s['kode_unik'];
        $s['bersih'] = $s['uang_masuk'] - $s['potongan_sumopod'];
        $s['manual_transaksi'] = $s['transaksi'] - $s['sumopod_transaksi'];
        $s['manual_masuk'] = $s['uang_masuk'] - $s['sumopod_masuk'];
        $s['rata_rata'] = $s['transaksi'] > 0 ? intdiv($s['uang_masuk'], $s['transaksi']) : 0;
        $s['membership'] = (int) MembershipPurchase::query()
            ->where('status', 'paid')
            ->when($this->from, fn ($q) => $q->where('paid_at', '>=', $this->from))
            ->where('paid_at', '<=', $this->to)
            ->sum('amount');

        return $s;
    }

    /** @return array{dibuat: int, lunas: int, ditolak: int, kedaluwarsa: int, persen: int} booking yang DIBUAT dalam periode */
    public function conversion(): array
    {
        $counts = Booking::query()
            ->where('is_sandbox', false)
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from))
            ->where('created_at', '<=', $this->to)
            ->selectRaw('status, COUNT(*) AS jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $dibuat = (int) $counts->sum();
        $lunas = (int) ($counts['paid'] ?? 0);

        return [
            'dibuat' => $dibuat,
            'lunas' => $lunas,
            'ditolak' => (int) ($counts['rejected'] ?? 0),
            'kedaluwarsa' => (int) ($counts['expired'] ?? 0),
            'persen' => $dibuat > 0 ? (int) round($lunas * 100 / $dibuat) : 0,
        ];
    }

    /** @return array{jumlah: int, nominal: int} bukti bayar yang menunggu verifikasi (saat ini, bukan per periode) */
    public function pending(): array
    {
        $row = Booking::query()->where('status', 'awaiting_verification')->where('is_sandbox', false)->toBase()
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM('.self::PAID_SQL.'), 0) AS nominal')->first();

        return ['jumlah' => (int) $row->jumlah, 'nominal' => (int) $row->nominal];
    }

    /** @return array{jumlah: int, nominal: int} sudah lunas tapi match-nya dibatalkan -- perlu dikembalikan */
    public function refundNeeded(): array
    {
        $row = Booking::query()->where('status', 'paid')->where('is_sandbox', false)
            ->whereHas('match', fn ($q) => $q->where('status', 'cancelled'))
            ->toBase()
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM('.self::PAID_SQL.'), 0) AS nominal')->first();

        return ['jumlah' => (int) $row->jumlah, 'nominal' => (int) $row->nominal];
    }

    /**
     * Uang masuk per hari (per bulan kalau periodenya "Semua"), tanggal dalam WIB.
     *
     * @return Collection<int, array{label: string, nominal: int}>
     */
    public function chart(): Collection
    {
        $tz = (string) config('app.display_timezone');
        $monthly = $this->period === 'semua';

        // Zona & format lewat binding (bukan disisipkan ke SQL); GROUP BY memakai alias kolom.
        $rows = $this->paidBookings()->toBase()
            ->selectRaw("to_char((bookings.paid_at AT TIME ZONE 'UTC') AT TIME ZONE ?, ?) AS bucket, SUM(".self::PAID_SQL.') AS nominal', [$tz, $monthly ? 'YYYY-MM' : 'YYYY-MM-DD'])
            ->groupBy('bucket')
            ->pluck('nominal', 'bucket');

        if ($monthly) {
            return $rows->map(fn ($nominal, $bucket) => [
                'label' => CarbonImmutable::createFromFormat('Y-m', (string) $bucket)->translatedFormat('M Y'),
                'nominal' => (int) $nominal,
            ])->values();
        }

        // Isi hari tanpa transaksi dengan 0 supaya grafiknya tidak bolong.
        $days = collect();
        $day = $this->from?->setTimezone($tz)->startOfDay() ?? CarbonImmutable::now($tz)->startOfDay();
        $last = $this->to->setTimezone($tz)->startOfDay();
        while ($day->lte($last)) {
            $days->push(['label' => $day->translatedFormat('d M'), 'nominal' => (int) ($rows[$day->format('Y-m-d')] ?? 0)]);
            $day = $day->addDay();
        }

        return $days;
    }

    /** @return Collection<int, \stdClass> pendapatan per match dari booking lunas dalam periode */
    public function perMatch(): Collection
    {
        return $this->paidBookings()->toBase()
            ->join('matches', 'matches.id', '=', 'bookings.match_id')
            ->selectRaw('matches.id, matches.title, matches.starts_at, matches.capacity, matches.status, '
                .'SUM(bookings.quantity) AS slot, SUM(bookings.total) AS tiket, SUM(bookings.admin_fee) AS biaya_admin, '
                .'SUM(bookings.total + bookings.admin_fee + CASE WHEN bookings.sumopod_payment_id IS NULL THEN COALESCE(bookings.unique_code, 0) ELSE 0 END) AS masuk')
            ->groupBy('matches.id', 'matches.title', 'matches.starts_at', 'matches.capacity', 'matches.status')
            ->orderByDesc('matches.starts_at')
            ->get();
    }

    /** Transaksi sandbox lunas di periode ini -- ditampilkan sebagai catatan, tidak dihitung. */
    public function sandboxCount(): int
    {
        return Booking::query()
            ->where('is_sandbox', true)
            ->where('status', 'paid')
            ->when($this->from, fn ($q) => $q->where('paid_at', '>=', $this->from))
            ->where('paid_at', '<=', $this->to)
            ->count();
    }

    public function label(): string
    {
        return self::PERIODS[$this->period];
    }
}
