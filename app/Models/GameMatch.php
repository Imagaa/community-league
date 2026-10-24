<?php

namespace App\Models;

use App\Notifications\MatchCancelledNotification;
use App\Support\PublicUrl;
use Carbon\CarbonInterface;
use Database\Factories\GameMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $field_name
 * @property string $type
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $poster_path
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $capacity
 * @property int $price_regular
 * @property int $price_member
 * @property int $points_reward
 * @property Carbon $member_opens_at
 * @property Carbon $public_opens_at
 * @property string $status
 * @property bool $hide_when_finished
 * @property int $last_completed_wizard_step
 * @property int $member_lead_minutes
 * @property string|null $scanner_token
 * @property int|null $score_a
 * @property int|null $score_b
 * @property string|null $team_a_name
 * @property string|null $team_b_name
 * @property string|null $team_a_logo_path
 * @property string|null $team_b_logo_path
 * @property string|null $drive_folder_url
 * @property list<string>|null $drive_photos
 * @property Carbon|null $documentation_sent_at
 */
#[Fillable([
    'venue_id', 'field_name', 'type', 'title', 'slug', 'description', 'poster_path',
    'starts_at', 'ends_at', 'capacity', 'price_regular', 'price_member', 'points_reward',
    'member_opens_at', 'public_opens_at', 'status', 'hide_when_finished',
    'last_completed_wizard_step', 'score_a', 'score_b', 'member_lead_minutes', 'scanner_token',
    'team_a_name', 'team_b_name', 'team_a_logo_path', 'team_b_logo_path',
    'drive_folder_url', 'drive_photos', 'documentation_sent_at',
])]
class GameMatch extends Model
{
    /** @use HasFactory<GameMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    /** Sama dengan default kolom -- supaya instance baru (belum di-refresh dari DB) tetap punya nilainya. */
    protected $attributes = ['member_lead_minutes' => 30];

    /** Status booking yang masih menahan slot (belum gagal/batal/kedaluwarsa). */
    public const ACTIVE_BOOKING_STATUSES = ['pending_payment', 'awaiting_verification', 'paid'];

    /**
     * Hanya transisi yang benar-benar butuh keputusan admin. open/ongoing/finished BUKAN
     * target transisi lagi -- itu status hitung (lihat computedStatus()), bukan nilai yang
     * pernah ditulis ke kolom status.
     */
    public const STATUS_TRANSITIONS = [
        'draft' => ['published', 'cancelled'],
        'published' => ['cancelled'],
    ];

    /** Label status TAMPILAN (6 nilai) -- bukan 1:1 lagi dengan kolom status yang tersimpan. */
    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'published' => 'Published',
        'open' => 'Open',
        'ongoing' => 'Ongoing',
        'finished' => 'Finished',
        'cancelled' => 'Cancelled',
    ];

    /** Warna Filament per status tampilan, dipakai badge grup admin & kartu match. */
    public const STATUS_COLORS = [
        'draft' => 'zinc',
        'published' => 'blue',
        'open' => 'emerald',
        'ongoing' => 'amber',
        'finished' => 'slate',
        'cancelled' => 'red',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'member_opens_at' => 'datetime',
            'public_opens_at' => 'datetime',
            'hide_when_finished' => 'boolean',
            'drive_photos' => 'array',
            'documentation_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'match_id');
    }

    /** @return HasMany<MatchReminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(MatchReminder::class, 'match_id');
    }

    /**
     * Slot terisi diturunkan dari booking aktif, tidak pernah disimpan sebagai kolom --
     * dua sumber kebenaran untuk hal yang sama akan berbeda suatu hari.
     */
    public function remainingSlots(): int
    {
        $taken = (int) $this->bookings()
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->sum('quantity');

        return max(0, $this->capacity - $taken);
    }

    public function isFull(): bool
    {
        return $this->remainingSlots() <= 0;
    }

    /**
     * Kirim notifikasi in-app ke semua user (login) dengan booking aktif untuk match ini --
     * dipanggil dari kedua tempat transisi status jadi 'cancelled' (EditGameMatch dan
     * ListGameMatches, dua Livewire page yang sama-sama punya transitionMatch()). Booking tamu
     * dilewati -- tidak ada akun buat disimpani notifikasinya.
     */
    public function notifyBookingsOfCancellation(): void
    {
        // Dipanggil dari dashboard admin -- link notifikasi harus domain publik.
        PublicUrl::scope(fn () => $this->bookings()
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->whereNotNull('user_id')
            ->with('user')
            ->get()
            ->each(fn (Booking $booking) => $booking->user->notify(new MatchCancelledNotification($this))));
    }

    /**
     * Status TAMPILAN, dihitung ulang setiap kali dipanggil -- bukan disimpan, bukan di-cache.
     * draft/cancelled selalu apa adanya; published dipecah lebih lanjut jadi
     * published/open/ongoing/finished berdasar public_opens_at/starts_at/ends_at.
     */
    public function computedStatus(): string
    {
        if ($this->status === 'draft') {
            return 'draft';
        }

        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        if (now()->lt($this->public_opens_at)) {
            return 'published';
        }

        if (now()->lt($this->starts_at)) {
            return 'open';
        }

        if (now()->lt($this->ends_at)) {
            return 'ongoing';
        }

        return 'finished';
    }

    /**
     * Bisa dibooking sekarang secara PUBLIK (tanpa mempertimbangkan akses awal member) --
     * murni berdasar computedStatus(). Untuk cek yang benar-benar menegakkan akses awal
     * member, pakai isBookableBy() di bawah.
     */
    public function isBookable(): bool
    {
        return $this->computedStatus() === 'open';
    }

    /**
     * Member akses lebih awal HANYA berlaku kalau member_opens_at beneran lebih awal dari
     * public_opens_at (admin BOLEH menyamakan keduanya -- itu artinya tidak ada keuntungan
     * akses awal untuk match ini). Member harus punya Membership yang masih aktif saat ini,
     * bukan cuma pernah beli.
     */
    public function hasEarlyMemberWindow(): bool
    {
        return $this->member_opens_at->lt($this->public_opens_at);
    }

    /** Link scanner check-in publik untuk panitia; token dibuat saat pertama diminta. */
    public function scannerUrl(): string
    {
        if ($this->scanner_token === null) {
            $this->update(['scanner_token' => Str::random(40)]);
        }

        return PublicUrl::route('checkin.scanner', $this->scanner_token);
    }

    /** Link lama langsung tidak berlaku -- untuk link yang bocor ke orang yang tidak berhak. */
    public function regenerateScannerToken(): void
    {
        $this->update(['scanner_token' => Str::random(40)]);
    }

    /**
     * Terbitkan sekarang. Membership aktif: member bisa beli saat ini juga, publik baru setelah
     * jeda akses awal (match tetap tampil sebagai "Segera dibuka"). Membership off: langsung dibuka.
     */
    public function publishNow(): void
    {
        $now = now();
        $lead = MembershipSettings::enabled() ? $this->member_lead_minutes : 0;

        $this->update([
            'status' => 'published',
            'member_opens_at' => $now,
            'public_opens_at' => $now->addMinutes($lead),
        ]);
    }

    /** Terbitkan terjadwal: publik bisa beli mulai $publicOpensAt, member lebih awal sesuai jeda. */
    public function publishAt(CarbonInterface $publicOpensAt): void
    {
        $this->update(['status' => 'published', 'public_opens_at' => $publicOpensAt] + $this->memberOpensFor($publicOpensAt));
    }

    /**
     * Ubah jeda akses awal member. Waktu buka publik tetap; waktu buka member dihitung ulang.
     * Draft cukup menyimpan jedanya -- waktu bukanya dihitung saat diterbitkan.
     */
    public function setMemberLead(int $minutes): void
    {
        $this->member_lead_minutes = $minutes;

        if ($this->status === 'published') {
            $this->fill($this->memberOpensFor($this->public_opens_at));
        }

        $this->save();
    }

    /** @return array{member_opens_at: CarbonInterface} */
    private function memberOpensFor(CarbonInterface $publicOpensAt): array
    {
        $lead = MembershipSettings::enabled() ? $this->member_lead_minutes : 0;

        return ['member_opens_at' => $publicOpensAt->copy()->subMinutes($lead)];
    }

    public function effectiveOpensAt(?User $user): CarbonInterface
    {
        // Saklar Membership off = tidak ada akses awal, walau user masih punya membership aktif.
        if ($user && $this->hasEarlyMemberWindow() && $user->membership?->isActive() && MembershipSettings::enabled()) {
            return $this->member_opens_at;
        }

        return $this->public_opens_at;
    }

    /**
     * Bisa dibooking sekarang OLEH USER INI -- versi member-aware dari isBookable(). Dipakai di
     * jalur yang beneran menegakkan akses awal member (CreateBooking, halaman detail match).
     * isBookable() (tanpa user) TETAP dipakai di tempat yang murni butuh status tampilan publik
     * (katalog, admin) -- tidak diubah supaya tidak ikut menegakkan aturan member di situ.
     */
    public function isBookableBy(?User $user): bool
    {
        // computedStatus() (bukan kolom status mentah) -- 'ongoing'/'finished' selalu ditolak,
        // 'draft'/'cancelled' juga. 'published' pun bisa lolos di sini kalau effectiveOpensAt()
        // (jendela member) sudah lewat walau public_opens_at belum -- itu inti akses awal member.
        if (in_array($this->computedStatus(), ['draft', 'cancelled', 'ongoing', 'finished'], true)) {
            return false;
        }

        return now()->gte($this->effectiveOpensAt($user)) && now()->lt($this->starts_at);
    }

    /**
     * Draft yang belum sampai fase terakhir wizard (Tim) tidak boleh di-publish -- field
     * jadwal/harga/kapasitas SUDAH diisi placeholder sejak fase 1 (bukan NULL), jadi cek "kolom
     * kosong" tidak cukup untuk mendeteksi draft yang belum benar-benar lengkap.
     */
    public function isCompleteForPublishing(): bool
    {
        return $this->last_completed_wizard_step >= 4;
    }

    /**
     * @param  Builder<GameMatch>  $query
     * @return Builder<GameMatch>
     */
    public function scopeFilterable(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('city'), fn ($q) => $q->whereHas(
                'venue',
                fn ($v) => $v->where('city_id', $request->integer('city'))
            ))
            ->when(
                $request->filled('type') && is_string($request->input('type')),
                fn ($q) => $q->where('type', $request->string('type'))
            )
            ->when($request->filled('venue'), fn ($q) => $q->where('venue_id', $request->integer('venue')))
            ->when(
                $request->filled('date_from') && is_string($request->input('date_from')) && strtotime($request->string('date_from')) !== false,
                fn ($q) => $q->whereDate('starts_at', '>=', $request->date('date_from'))
            )
            ->when(
                $request->filled('date_to') && is_string($request->input('date_to')) && strtotime($request->string('date_to')) !== false,
                fn ($q) => $q->whereDate('starts_at', '<=', $request->date('date_to'))
            )
            ->when($request->filled('q') && is_string($request->input('q')), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'ilike', $term)
                        ->orWhereHas('venue', fn ($v) => $v->where('name', 'ilike', $term));
                });
            })
            ->when($request->boolean('available_only'), fn ($q) => $q->whereRaw(
                'capacity > '.self::TAKEN_SLOTS_SQL, self::ACTIVE_BOOKING_STATUSES
            ));
    }

    /** Subquery slot terisi per match; binding-nya ACTIVE_BOOKING_STATUSES. */
    private const TAKEN_SLOTS_SQL = '(select coalesce(sum(quantity), 0) from bookings '
        .'where bookings.match_id = matches.id and bookings.status in (?, ?, ?))';

    /** Tab daftar match di admin -- dihitung otomatis, tidak ada kolom arsip. */
    public const ADMIN_TABS = ['aktif' => 'Aktif', 'penuh' => 'Penuh', 'selesai' => 'Selesai', 'batal' => 'Batal'];

    /**
     * aktif = belum selesai & masih ada slot; penuh = belum selesai tapi slot habis;
     * selesai = sudah lewat waktu selesai; batal = dibatalkan admin.
     *
     * @param  Builder<GameMatch>  $query
     * @return Builder<GameMatch>
     */
    public function scopeAdminTab(Builder $query, string $tab): Builder
    {
        if ($tab === 'batal') {
            return $query->where('status', 'cancelled');
        }

        $query->where('status', '!=', 'cancelled');

        return match ($tab) {
            'selesai' => $query->where('ends_at', '<=', now()),
            'penuh' => $query->where('ends_at', '>', now())
                ->whereRaw('capacity <= '.self::TAKEN_SLOTS_SQL, self::ACTIVE_BOOKING_STATUSES),
            default => $query->where('ends_at', '>', now())
                ->whereRaw('capacity > '.self::TAKEN_SLOTS_SQL, self::ACTIVE_BOOKING_STATUSES),
        };
    }

    /**
     * @param  Builder<GameMatch>  $query
     * @return Builder<GameMatch>
     */
    public function scopeWithTakenSlots(Builder $query): Builder
    {
        return $query->withSum(
            ['bookings as taken_slots' => fn ($q) => $q->whereIn('status', self::ACTIVE_BOOKING_STATUSES)],
            'quantity'
        );
    }
}
