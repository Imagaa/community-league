<?php

use App\Models\NotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use NotificationChannels\WebPush\PushSubscription;

new #[Title('Notifikasi')] class extends Component {
    public bool $match_reminders = true;
    public bool $payment_confirmations = true;
    public bool $promotions = false;
    public bool $browser_push_enabled = false;

    /** @var Collection<int, PushSubscription> */
    public Collection $pushSubscriptions;

    public function mount(): void
    {
        $pref = NotificationPreference::forUser(Auth::user());

        $this->match_reminders = $pref->match_reminders;
        $this->payment_confirmations = $pref->payment_confirmations;
        $this->promotions = $pref->promotions;
        $this->browser_push_enabled = $pref->browser_push_enabled;
        $this->loadPushSubscriptions();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['match_reminders', 'payment_confirmations', 'promotions', 'browser_push_enabled'], true)) {
            return;
        }

        NotificationPreference::forUser(Auth::user())->update([$property => $this->{$property}]);
    }

    public function subscribeToPush(string $endpoint, string $key, string $token, ?string $userAgent = null): void
    {
        $subscription = Auth::user()->updatePushSubscription($endpoint, $key, $token);
        $subscription->user_agent = $userAgent;
        $subscription->save();

        $this->browser_push_enabled = true;
        NotificationPreference::forUser(Auth::user())->update(['browser_push_enabled' => true]);
        $this->loadPushSubscriptions();
    }

    public function unsubscribeFromPush(int $subscriptionId): void
    {
        Auth::user()->pushSubscriptions()->where('id', $subscriptionId)->delete();
        $this->loadPushSubscriptions();

        if ($this->pushSubscriptions->isEmpty()) {
            $this->browser_push_enabled = false;
            NotificationPreference::forUser(Auth::user())->update(['browser_push_enabled' => false]);
        }
    }

    private function loadPushSubscriptions(): void
    {
        $this->pushSubscriptions = Auth::user()->pushSubscriptions()->latest()->get();
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-akun-page-header :title="__('Notifikasi')" />

    <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
        <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Kategori Notifikasi') }}</h2>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Pengingat match') }}</span>
                <flux:switch wire:model.live="match_reminders" />
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Konfirmasi pembayaran') }}</span>
                <flux:switch wire:model.live="payment_confirmations" />
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Promo & info umum') }}</span>
                <flux:switch wire:model.live="promotions" />
            </div>
        </div>
    </section>

    <section
        class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900"
        x-data="{
            vapidPublicKey: @js(config('webpush.vapid.public_key')),
            subscribedHere: false,
            currentEndpoint: null,
            error: null,
            subscribing: false,
            removingId: null,
            async init() {
                if (!('serviceWorker' in navigator)) { return; }
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                this.subscribedHere = !!subscription;
                this.currentEndpoint = subscription?.endpoint ?? null;
            },
            urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);
                return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
            },
            async subscribe() {
                if (this.subscribing) { return; }
                this.subscribing = true;
                this.error = null;
                try {
                    if (!('Notification' in window) || !('serviceWorker' in navigator) || !this.vapidPublicKey) {
                        this.error = 'Browser ini tidak mendukung notifikasi push.';
                        return;
                    }
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') { return; }
                    const registration = await navigator.serviceWorker.ready;
                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey),
                    });
                    const json = subscription.toJSON();
                    await $wire.subscribeToPush(json.endpoint, json.keys.p256dh, json.keys.auth, navigator.userAgent);
                    this.subscribedHere = true;
                    this.currentEndpoint = json.endpoint;
                } catch (e) {
                    this.error = 'Gagal mengaktifkan notifikasi push.';
                } finally {
                    this.subscribing = false;
                }
            },
            async remove(subscriptionId, endpoint) {
                if (this.removingId !== null) { return; }
                this.removingId = subscriptionId;
                this.error = null;
                try {
                    await $wire.call('unsubscribeFromPush', subscriptionId);
                    if (endpoint === this.currentEndpoint) {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.getSubscription();
                        if (subscription) { await subscription.unsubscribe(); }
                        this.subscribedHere = false;
                        this.currentEndpoint = null;
                    }
                } catch (e) {
                    this.error = 'Gagal menghapus perangkat.';
                } finally {
                    this.removingId = null;
                }
            },
        }"
    >
        <h2 class="mb-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Notifikasi Browser') }}</h2>
        <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Terima notifikasi lewat sistem Android/browser walau aplikasi sedang tidak dibuka.') }}</p>

        <template x-if="!subscribedHere">
            <button
                type="button"
                x-on:click="subscribe()"
                x-bind:disabled="subscribing"
                class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-navy-700 dark:text-zinc-300 dark:hover:bg-navy-800"
            >
                <span x-show="subscribing" class="size-3.5 animate-spin rounded-full border-2 border-zinc-400 border-t-transparent dark:border-zinc-500"></span>
                <span x-text="subscribing ? @js(__('Mengaktifkan...')) : @js(__('Aktifkan Notifikasi Browser'))"></span>
            </button>
        </template>
        <template x-if="subscribedHere">
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ __('Notifikasi browser aktif di perangkat ini.') }}</p>
        </template>
        <template x-if="error"><p class="mt-2 text-xs text-red-600 dark:text-red-400" x-text="error"></p></template>

        @if ($pushSubscriptions->isNotEmpty())
            <div class="mt-4 flex flex-col gap-2 border-t border-zinc-100 pt-4 dark:border-navy-800">
                <h3 class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Perangkat dengan notifikasi aktif') }}</h3>
                @foreach ($pushSubscriptions as $subscription)
                    <div class="flex items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-navy-800">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-zinc-700 dark:text-zinc-300">{{ \App\Support\DeviceLabel::fromUserAgent($subscription->user_agent) }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Terdaftar :date', ['date' => $subscription->created_at?->translatedFormat('d M Y')]) }}</p>
                        </div>
                        <button
                            type="button"
                            x-on:click="remove({{ $subscription->id }}, @js($subscription->endpoint))"
                            x-bind:disabled="removingId === {{ $subscription->id }}"
                            class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-60 dark:text-red-400"
                        >
                            <span x-show="removingId === {{ $subscription->id }}" class="size-3 animate-spin rounded-full border-2 border-red-400 border-t-transparent"></span>
                            <span x-text="removingId === {{ $subscription->id }} ? @js(__('Menghapus...')) : @js(__('Hapus'))"></span>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <x-akun-tampilan-section />

    <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
        <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Pintasan') }}</h2>
        <x-add-to-homescreen-button />
    </section>
</div>
