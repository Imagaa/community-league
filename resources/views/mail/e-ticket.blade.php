<x-mail::message>
{{-- E-ticket lengkap di badan email: pembeli cukup menunjukkan email ini di venue. QR tampil
     inline lewat CID (bukan lampiran) -- lihat BookingPaidNotification::toMail(). --}}
<div style="text-align:center">
<span style="display:inline-block;background:#16a34a;color:#fff;font-weight:700;letter-spacing:.1em;border-radius:999px;padding:4px 14px;font-size:12px">LUNAS</span>

# {{ $booking->match->title }}

{{ $booking->match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('l, d F Y · H:i') }} WIB
</div>

<x-mail::panel>
**{{ $booking->match->venue->name }}**<br>
{{ $booking->match->venue->address }}
</x-mail::panel>

<x-mail::button :url="$booking->match->venue->mapsUrl()" color="success">
Buka di Google Maps
</x-mail::button>

<x-mail::table>
| | |
|:--|--:|
| Pemesan | **{{ $booking->buyerName() }}** |
| Kode booking | **{{ $booking->booking_code }}** |
| Jumlah slot | {{ $booking->quantity }} |
| Dibayar | **{{ \App\Support\Money::rupiah($booking->paidAmount()) }}** |
</x-mail::table>

<div style="text-align:center">
<img src="cid:{{ $qrCid }}" alt="QR tiket {{ $booking->booking_code }}" width="200" height="200" style="width:200px;height:200px">

Tunjukkan QR atau email ini ke panitia di venue.
</div>

@if ($poinTotal > 0)
Anda berhak atas {{ $poinTotal }} poin untuk match ini kalau hadir.
@endif

<x-mail::button :url="$ticketUrl">
Lihat E-Ticket
</x-mail::button>

Sampai jumpa di lapangan!<br>
{{ config('app.name') }}
</x-mail::message>
