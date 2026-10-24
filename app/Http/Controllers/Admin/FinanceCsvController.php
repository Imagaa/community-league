<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Support\FinanceReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Transaksi lunas per periode untuk pembukuan (dibuka di Excel/Google Sheets). */
class FinanceCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $report = new FinanceReport((string) $request->query('periode', '30-hari'));
        $tz = (string) config('app.display_timezone');
        $filename = 'keuangan-cl-'.$report->period.'-'.now($tz)->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($report, $tz) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // BOM supaya Excel membaca UTF-8 dengan benar; pemisah titik koma untuk Excel lokal Indonesia.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kode booking', 'Waktu bayar (WIB)', 'Match', 'Jadwal match', 'Pembeli', 'Email', 'WhatsApp', 'Metode',
                'Harga tiket', 'Biaya admin', 'Kode unik', 'Total dibayar'], ';');

            $report->paidBookings()->with('match', 'user')->orderBy('paid_at')
                ->each(function (Booking $b) use ($out, $tz) {
                    $sumopod = $b->sumopod_payment_id !== null;
                    fputcsv($out, [
                        $b->booking_code,
                        $b->paid_at?->timezone($tz)->format('Y-m-d H:i'),
                        $b->match->title,
                        $b->match->starts_at->timezone($tz)->format('Y-m-d H:i'),
                        $b->buyerName(),
                        $b->buyerEmail(),
                        $b->buyerPhone(),
                        $sumopod ? 'Sumopod' : 'QRIS manual',
                        $b->total,
                        $b->admin_fee,
                        $sumopod ? 0 : (int) $b->unique_code,
                        $b->paidAmount(),
                    ], ';');
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
