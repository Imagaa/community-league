<?php

use App\Http\Middleware\EnsureAdmin;
use App\Support\FinanceReport;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Keuangan')] class extends Component {
    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    #[Url(as: 'periode')]
    public string $period = '30-hari';

    #[Computed]
    public function report(): FinanceReport
    {
        return new FinanceReport($this->period);
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, FinanceReport::PERIODS) ? $period : '30-hari';
        unset($this->report);
    }
}; ?>

@php
    $r = $this->report;
    $s = $r->summary();
    $konversi = $r->conversion();
    $tertahan = $r->pending();
    $refund = $r->refundNeeded();
    $chart = $r->chart();
    $max = max(1, (int) $chart->max('nominal'));
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-white">Keuangan</h1>
            <p class="text-xs text-zinc-400">
                Dari pembayaran lunas berdasarkan waktu bayar (WIB).
                @if (($sandbox = $r->sandboxCount()) > 0)
                    <span class="text-amber-400">{{ $sandbox }} transaksi sandbox (uji coba) tidak dihitung.</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.keuangan.csv', ['periode' => $r->period]) }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-navy-700 px-3 py-2 text-xs font-semibold text-zinc-200 hover:bg-navy-800">
            <flux:icon name="arrow-down-tray" variant="micro" /> Unduh CSV
        </a>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach (FinanceReport::PERIODS as $key => $label)
            <x-admin.tab :active="$r->period === $key" wire:click="setPeriod('{{ $key }}')">{{ $label }}</x-admin.tab>
        @endforeach
    </div>

    {{-- Angka utama --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="col-span-2 rounded-2xl border border-orange-600/50 bg-navy-900 p-4">
            <p class="text-xs text-zinc-400">Uang masuk</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ Money::rupiah($s['uang_masuk']) }}</p>
            <p class="mt-1 text-xs text-zinc-400">tiket + biaya admin + kode unik · {{ $s['transaksi'] }} transaksi</p>
        </div>
        <div class="col-span-2 rounded-2xl border border-navy-800 bg-navy-900 p-4">
            <p class="text-xs text-zinc-400">Pendapatan bersih (estimasi)</p>
            <p class="mt-1 text-2xl font-bold text-emerald-400">{{ Money::rupiah($s['bersih']) }}</p>
            <p class="mt-1 text-xs text-zinc-400">uang masuk &minus; potongan Sumopod {{ Money::rupiah($s['potongan_sumopod']) }}</p>
        </div>

        @foreach ([
            ['Pendapatan tiket', Money::rupiah($s['tiket']), null],
            ['Biaya admin', Money::rupiah($s['biaya_admin']), null],
            ['Kode unik', Money::rupiah($s['kode_unik']), 'dari QRIS manual'],
            ['Slot terjual', number_format($s['slot'], 0, ',', '.'), null],
            ['Rata-rata / transaksi', Money::rupiah($s['rata_rata']), null],
            ['Konversi booking', $konversi['persen'].'%', $konversi['lunas'].' lunas dari '.$konversi['dibuat'].' booking'],
        ] as [$label, $value, $hint])
            <div class="rounded-2xl border border-navy-800 bg-navy-900 p-4">
                <p class="text-xs text-zinc-400">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold text-white">{{ $value }}</p>
                @if ($hint) <p class="text-[11px] text-zinc-500">{{ $hint }}</p> @endif
            </div>
        @endforeach
        @if ($s['membership'] > 0)
            <div class="rounded-2xl border border-navy-800 bg-navy-900 p-4">
                <p class="text-xs text-zinc-400">Membership</p>
                <p class="mt-1 text-lg font-bold text-white">{{ Money::rupiah($s['membership']) }}</p>
                <p class="text-[11px] text-zinc-500">tidak termasuk uang masuk tiket</p>
            </div>
        @endif
    </div>

    {{-- Perlu tindakan (bukan per periode -- kondisi saat ini) --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <a href="{{ route('admin.verifikasi') }}" wire:navigate class="flex items-center gap-3 rounded-2xl border border-navy-800 bg-navy-900 p-4 hover:border-amber-700">
            <flux:icon name="clock" class="size-8 shrink-0 text-amber-400" />
            <div>
                <p class="text-xs text-zinc-400">Menunggu verifikasi</p>
                <p class="font-bold text-white">{{ $tertahan['jumlah'] }} bukti · {{ Money::rupiah($tertahan['nominal']) }}</p>
            </div>
        </a>
        <div @class(['flex items-center gap-3 rounded-2xl border bg-navy-900 p-4', 'border-red-900' => $refund['jumlah'] > 0, 'border-navy-800' => $refund['jumlah'] === 0])>
            <flux:icon name="arrow-uturn-left" @class(['size-8 shrink-0', 'text-red-400' => $refund['jumlah'] > 0, 'text-zinc-600' => $refund['jumlah'] === 0]) />
            <div>
                <p class="text-xs text-zinc-400">Perlu refund (match dibatalkan)</p>
                <p class="font-bold text-white">{{ $refund['jumlah'] }} pembeli · {{ Money::rupiah($refund['nominal']) }}</p>
            </div>
        </div>
    </div>

    {{-- Grafik uang masuk --}}
    <section class="rounded-2xl border border-navy-800 bg-navy-900 p-4">
        <h2 class="mb-3 text-sm font-semibold text-white">Uang masuk {{ $r->period === 'semua' ? 'per bulan' : 'per hari' }}</h2>
        @if ($chart->isEmpty() || $chart->sum('nominal') === 0)
            <p class="py-6 text-center text-xs text-zinc-500">Belum ada pembayaran di periode ini.</p>
        @else
            <div class="flex h-40 items-end gap-1 overflow-x-auto">
                @foreach ($chart as $bar)
                    <div class="group relative flex h-full min-w-3 flex-1 flex-col justify-end" title="{{ $bar['label'] }}: {{ Money::rupiah($bar['nominal']) }}">
                        <div class="rounded-t bg-orange-600/80 group-hover:bg-orange-500" style="height: {{ max($bar['nominal'] > 0 ? 3 : 0, round($bar['nominal'] / $max * 100)) }}%"></div>
                        <span class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 rounded bg-navy-950 px-1.5 py-0.5 text-[10px] whitespace-nowrap text-white group-hover:block">
                            {{ $bar['label'] }} · {{ Money::rupiah($bar['nominal']) }}
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-zinc-500">
                <span>{{ $chart->first()['label'] }}</span>
                <span>{{ $chart->last()['label'] }}</span>
            </div>
        @endif
    </section>

    {{-- Metode bayar --}}
    <section class="rounded-2xl border border-navy-800 bg-navy-900 p-4">
        <h2 class="mb-2 text-sm font-semibold text-white">Per metode bayar</h2>
        <x-admin.setting-row label="QRIS manual ({{ $s['manual_transaksi'] }} transaksi)">{{ Money::rupiah($s['manual_masuk']) }}</x-admin.setting-row>
        <x-admin.setting-row label="Bayar otomatis Sumopod ({{ $s['sumopod_transaksi'] }} transaksi)">{{ Money::rupiah($s['sumopod_masuk']) }}</x-admin.setting-row>
        <x-admin.setting-row label="Estimasi potongan Sumopod (0,7% + Rp300)">&minus;{{ Money::rupiah($s['potongan_sumopod']) }}</x-admin.setting-row>
        <x-admin.setting-row label="Booking ditolak / kedaluwarsa">{{ $konversi['ditolak'] }} / {{ $konversi['kedaluwarsa'] }}</x-admin.setting-row>
        <p class="mt-2 text-[11px] text-zinc-500">Biaya penarikan dana Sumopod (Rp2.500 per withdraw) tidak termasuk.</p>
    </section>

    {{-- Per match --}}
    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-zinc-400">Per match</h2>
        @forelse ($r->perMatch() as $m)
            <a href="{{ route('admin.match.pemesan', $m->id) }}" wire:navigate wire:key="fm-{{ $m->id }}"
               class="block rounded-2xl border border-navy-800 bg-navy-900 p-4 hover:border-navy-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-white">
                            {{ $m->title }}
                            @if ($m->status === 'cancelled') <span class="ml-1 rounded-full bg-red-950 px-2 py-0.5 text-[10px] text-red-300">Batal</span> @endif
                        </p>
                        <p class="text-xs text-zinc-400">{{ \Illuminate\Support\Carbon::parse($m->starts_at, 'UTC')->timezone(config('app.display_timezone'))->translatedFormat('d M Y · H.i') }}</p>
                    </div>
                    <p class="shrink-0 font-bold text-white">{{ Money::rupiah((int) $m->masuk) }}</p>
                </div>
                @php $isi = $m->capacity > 0 ? min(100, (int) round($m->slot * 100 / $m->capacity)) : 0; @endphp
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-navy-800">
                    <div class="h-full rounded-full bg-orange-600" style="width: {{ $isi }}%"></div>
                </div>
                <p class="mt-1 text-[11px] text-zinc-500">
                    {{ (int) $m->slot }}/{{ $m->capacity }} slot ({{ $isi }}%) · tiket {{ Money::rupiah((int) $m->tiket) }} · biaya admin {{ Money::rupiah((int) $m->biaya_admin) }}
                </p>
            </a>
        @empty
            <p class="rounded-2xl border border-dashed border-navy-800 p-8 text-center text-sm text-zinc-500">Belum ada penjualan di periode ini.</p>
        @endforelse
    </section>
</div>
