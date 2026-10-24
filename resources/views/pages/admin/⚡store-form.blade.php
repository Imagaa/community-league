<?php

use App\Http\Middleware\EnsureAdmin;
use App\Models\RewardItem;
use App\Support\CompressedImageUpload;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin')] #[Title('Produk Store')] class extends Component {
    use WithFileUploads;

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    public ?RewardItem $item = null;

    public string $name = '';
    public string $description = '';
    public ?int $point_cost = null;
    /** Kosong = stok tak terbatas. */
    public ?int $stock = null;
    public bool $active = true;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public function mount(?RewardItem $item = null): void
    {
        if (! $item?->exists) {
            return;
        }

        $this->item = $item;
        $this->fill([
            'name' => $item->name,
            'description' => (string) $item->description,
            'point_cost' => $item->point_cost,
            'stock' => $item->stock,
            'active' => $item->active,
        ]);
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'point_cost' => ['required', 'integer', 'min:1', 'max:10000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], ['image.max' => 'Ukuran gambar maksimal 4 MB.'], [
            'name' => 'nama', 'description' => 'deskripsi', 'point_cost' => 'harga poin', 'stock' => 'stok', 'image' => 'gambar',
        ]);

        $imagePath = $this->item?->image_path;
        if ($this->image) {
            $newPath = CompressedImageUpload::compressAndStoreContents((string) $this->image->get(), 'reward-items', 1200);
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
            'name' => trim($this->name),
            'description' => trim($this->description) ?: null,
            'point_cost' => $this->point_cost,
            'stock' => $this->stock,
            'active' => $this->active,
            'image_path' => $imagePath,
        ];

        if ($this->item) {
            $this->item->update($data);
        } else {
            RewardItem::create($data + ['sort_order' => (int) RewardItem::max('sort_order') + 1]);
        }

        session()->flash('status', "Produk \"{$data['name']}\" tersimpan.");

        return $this->redirect(route('admin.membership', ['tab' => 'store']), navigate: true);
    }

    public function previewUrl(): ?string
    {
        if ($this->image instanceof TemporaryUploadedFile && $this->image->isPreviewable()) {
            return $this->image->temporaryUrl();
        }

        return $this->item?->image_path ? Storage::disk('public')->temporaryUrl($this->item->image_path, now()->addHour()) : null;
    }
}; ?>

<form wire:submit="save" class="mx-auto max-w-2xl space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.membership', ['tab' => 'store']) }}" wire:navigate class="rounded-full bg-navy-900 p-2 text-zinc-400 hover:text-white" aria-label="Kembali">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <h1 class="text-xl font-bold text-white">{{ $item ? 'Ubah Produk' : 'Produk Baru' }}</h1>
    </div>

    @php $preview = $this->previewUrl(); @endphp
    <div class="space-y-1.5">
        <p class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">Gambar</p>
        <label class="flex cursor-pointer items-center gap-4 rounded-xl border-2 border-dashed border-navy-700 p-3 text-zinc-400 hover:border-orange-500">
            <span class="relative size-20 shrink-0 overflow-hidden rounded-lg bg-navy-800">
                @if ($preview)
                    <img src="{{ $preview }}" alt="" class="size-full object-cover">
                @else
                    <flux:icon name="gift" class="absolute inset-0 m-auto size-7 text-zinc-600" />
                @endif
            </span>
            <span class="text-sm">
                <span wire:loading.remove wire:target="image">{{ $preview ? 'Ganti gambar' : 'Pilih gambar' }}</span>
                <span wire:loading wire:target="image">Mengunggah...</span>
                <span class="block text-xs text-zinc-500">Persegi lebih rapi. JPG/PNG/WEBP maks 4 MB, otomatis dikompres.</span>
            </span>
            <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="sr-only">
        </label>
        @error('image') <span class="block text-xs text-red-400">{{ $message }}</span> @enderror
    </div>

    <x-admin.field label="Nama produk" name="name">
        <input type="text" wire:model="name" maxlength="120" class="admin-input" placeholder="Jersey CL">
    </x-admin.field>

    <x-admin.field label="Deskripsi" name="description" hint="Opsional, tampil di halaman detail produk.">
        <textarea wire:model="description" rows="3" class="admin-input"></textarea>
    </x-admin.field>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-admin.field label="Harga (poin)" name="point_cost">
            <div class="relative">
                <input type="number" inputmode="numeric" min="1" wire:model="point_cost" class="admin-input pr-14" placeholder="500">
                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-zinc-500">poin</span>
            </div>
        </x-admin.field>
        <x-admin.field label="Stok" name="stock" hint="Kosongkan untuk stok tak terbatas.">
            <input type="number" inputmode="numeric" min="0" wire:model="stock" class="admin-input" placeholder="Tak terbatas">
        </x-admin.field>
    </div>

    <x-admin.switch wire:model="active" label="Tampilkan di store" description="Nonaktif = disembunyikan dari member, riwayat penukaran tetap ada." />

    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-orange-600 py-3 font-bold text-white hover:bg-orange-700 disabled:opacity-50">
        {{ $item ? 'Simpan perubahan' : 'Simpan' }}
    </button>
</form>
