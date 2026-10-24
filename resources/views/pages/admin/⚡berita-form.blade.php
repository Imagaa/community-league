<?php

use App\Http\Middleware\EnsureAdmin;
use App\Models\Post;
use App\Support\CompressedImageUpload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin')] #[Title('Berita')] class extends Component {
    use WithFileUploads;

    public const TYPES = [
        'news' => ['label' => 'Berita', 'icon' => 'newspaper', 'sub' => 'Klik membuka halaman artikel'],
        'promo' => ['label' => 'Promo', 'icon' => 'megaphone', 'sub' => 'Klik membuka link tujuan'],
    ];

    public ?Post $post = null;

    public string $type = 'news';
    public string $title = '';
    public string $excerpt = '';
    /** Teks biasa; paragraf dipisah baris kosong (disimpan sebagai <p> di kolom body). */
    public string $body = '';
    public string $link_url = '';
    public string $published_at = '';
    public bool $is_active = true;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    public function mount(?Post $post = null): void
    {
        if (! $post?->exists) {
            return;
        }

        $this->post = $post;
        $this->fill([
            'type' => $post->type === 'promo' ? 'promo' : 'news',
            'title' => $post->title,
            'excerpt' => (string) $post->excerpt,
            'body' => self::htmlToText((string) $post->body),
            'link_url' => (string) $post->link_url,
            'published_at' => $post->published_at?->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i') ?? '',
            'is_active' => $post->is_active,
        ]);
    }

    public function save()
    {
        $this->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'title' => ['required', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'link_url' => [Rule::requiredIf($this->type === 'promo'), 'nullable', 'url:http,https', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'image' => [Rule::requiredIf(! $this->post?->image_path), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'image.required' => 'Pilih gambar untuk kartu carousel.',
            'image.max' => 'Ukuran gambar maksimal 4 MB.',
            'link_url.required' => 'Link tujuan wajib untuk Promo.',
        ], ['title' => 'judul', 'excerpt' => 'ringkasan', 'body' => 'isi', 'link_url' => 'link tujuan', 'image' => 'gambar']);

        $imagePath = $this->post?->image_path;

        if ($this->image) {
            $newPath = CompressedImageUpload::compressAndStoreContents((string) $this->image->get(), 'posts', 1600);

            if ($newPath === null) {
                $this->addError('image', 'Gambar tidak bisa dibaca. Coba file lain.');

                return null;
            }

            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $newPath;
        }

        $data = [
            'type' => $this->type,
            'title' => trim($this->title),
            'excerpt' => trim($this->excerpt) ?: null,
            'body' => $this->type === 'news' ? self::textToHtml($this->body) : null,
            'link_url' => $this->type === 'promo' ? trim($this->link_url) : null,
            'image_path' => $imagePath,
            'published_at' => $this->published_at !== '' ? Carbon::parse($this->published_at, config('app.display_timezone'))->utc() : null,
            'is_active' => $this->is_active,
        ];

        if ($this->post) {
            // Slug sengaja tidak diubah -- link /berita/{slug} yang sudah dibagikan tetap hidup.
            $this->post->update($data);
        } else {
            // Post baru masuk paling depan carousel; urutan bisa digeser di daftar.
            Post::create($data + [
                'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
                'sort_order' => (int) Post::min('sort_order') - 1,
            ]);
        }

        session()->flash('status', "\"{$data['title']}\" tersimpan.");

        return $this->redirectRoute('admin.berita', navigate: true);
    }

    public function previewUrl(): ?string
    {
        if ($this->image instanceof TemporaryUploadedFile && $this->image->isPreviewable()) {
            return $this->image->temporaryUrl();
        }

        return $this->post?->image_path
            ? Storage::disk('public')->temporaryUrl($this->post->image_path, now()->addHour())
            : null;
    }

    public static function textToHtml(string $text): ?string
    {
        $paragraphs = preg_split('/\R\s*\R/', trim($text)) ?: [];
        $html = collect($paragraphs)
            ->map(fn (string $p) => trim($p))
            ->filter()
            ->map(fn (string $p) => '<p>'.nl2br(e($p), false).'</p>')
            ->implode('');

        return $html !== '' ? $html : null;
    }

    public static function htmlToText(string $html): string
    {
        $text = preg_replace(['#</p>\s*<p[^>]*>#i', '#<br\s*/{0,1}>#i'], ["\n\n", "\n"], $html) ?? $html;

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
    }
}; ?>

<form wire:submit="save" class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.berita') }}" wire:navigate class="rounded-full bg-navy-900 p-2 text-zinc-400 hover:text-white" aria-label="Kembali">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <h1 class="text-xl font-bold text-white">{{ $post ? 'Ubah Berita' : 'Berita Baru' }}</h1>
    </div>

    {{-- Pratinjau kartu carousel: rasio & potongan gambar sama dengan homepage (object-cover). --}}
    @php $preview = $this->previewUrl(); @endphp
    <div class="space-y-1.5">
        <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Pratinjau carousel</p>
        <div class="relative flex aspect-[21/9] items-end overflow-hidden rounded-2xl bg-navy-800">
            @if ($preview)
                <img src="{{ $preview }}" alt="" class="absolute inset-0 size-full object-cover">
            @else
                <flux:icon name="photo" class="absolute inset-0 m-auto size-10 text-zinc-600" />
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>
            <span class="absolute top-3 left-3 rounded-full bg-orange-600 px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">{{ $type === 'news' ? 'Berita' : 'Promo' }}</span>
            <div class="relative z-10 w-full min-w-0 p-4 text-white">
                <p class="truncate font-display text-lg font-bold tracking-wide">{{ $title ?: 'Judul berita' }}</p>
                @if ($excerpt)
                    <p class="line-clamp-2 text-xs text-white/80">{{ $excerpt }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2">
        @foreach ($this::TYPES as $key => $t)
            <x-admin.tile :active="$type === $key" :icon="$t['icon']" :sub="$t['sub']" wire:click="$set('type', '{{ $key }}')">{{ $t['label'] }}</x-admin.tile>
        @endforeach
    </div>

    <div class="space-y-1.5">
        <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Gambar</p>
        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed border-navy-700 p-4 text-center text-zinc-400 hover:border-orange-500">
            <flux:icon name="arrow-up-tray" class="size-6" />
            <span wire:loading.remove wire:target="image">{{ $preview ? 'Ganti gambar' : 'Pilih gambar' }}</span>
            <span wire:loading wire:target="image">Mengunggah...</span>
            <span class="text-xs text-zinc-500">Rasio 21:9 (mis. 2100×900 px) supaya tidak terpotong. JPG/PNG/WEBP maks 4 MB, otomatis dikompres.</span>
            <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="sr-only">
        </label>
        @error('image') <span class="block text-xs text-red-400">{{ $message }}</span> @enderror
    </div>

    <x-admin.field label="Judul" name="title">
        <input type="text" wire:model.live.debounce.400ms="title" maxlength="120" class="admin-input">
    </x-admin.field>

    <x-admin.field label="Ringkasan" name="excerpt" hint="Teks pendek di kartu carousel, maks 200 karakter.">
        <textarea wire:model.live.debounce.400ms="excerpt" rows="2" maxlength="200" class="admin-input"></textarea>
    </x-admin.field>

    @if ($type === 'news')
        <x-admin.field label="Isi artikel" name="body" hint="Pisahkan paragraf dengan satu baris kosong.">
            <textarea wire:model="body" rows="10" class="admin-input"></textarea>
        </x-admin.field>
    @else
        <x-admin.field label="Link tujuan" name="link_url">
            <input type="url" wire:model="link_url" class="admin-input" placeholder="https://...">
        </x-admin.field>
    @endif

    <x-admin.field label="Terbit pada (WIB)" name="published_at" hint="Kosongkan untuk langsung tampil. Diisi waktu mendatang = terjadwal.">
        <input type="datetime-local" wire:model="published_at" class="admin-input [color-scheme:dark]">
    </x-admin.field>

    <x-admin.switch wire:model="is_active" label="Aktif" description="Nonaktif = tidak tampil di carousel maupun halaman artikel." />

    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-orange-600 py-3 font-bold text-white hover:bg-orange-700 disabled:opacity-50">
        {{ $post ? 'Simpan perubahan' : 'Terbitkan' }}
    </button>
</form>
