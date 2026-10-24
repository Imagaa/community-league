@props([
    'heading',
    'description',
    'confirmLabel' => 'Ya, Lanjutkan',
    'cancelLabel' => 'Batal',
    'seconds' => 3,
    'times' => 1,
    'onConfirm',
])

{{-- Trusted input only: $heading, $description, $confirmLabel, $cancelLabel, and especially
     $onConfirm are interpolated raw into client-side JS/HTML with no escaping. Never pass
     user-supplied data into any of these props — only hardcoded literals or values from a
     fixed, non-user-controlled set (e.g. status constants, record IDs). --}}
<div
    x-data="{
        open: false,
        step: 1,
        times: {{ (int) $times }},
        remaining: {{ (int) $seconds }},
        timer: null,
        startTimer() {
            clearInterval(this.timer);
            this.remaining = {{ (int) $seconds }};
            if (this.remaining > 0) {
                this.timer = setInterval(() => {
                    this.remaining = Math.max(0, this.remaining - 1);
                    if (this.remaining === 0) clearInterval(this.timer);
                }, 1000);
            }
        },
        openModal() {
            this.open = true;
            this.step = 1;
            this.startTimer();
        },
        closeModal() {
            this.open = false;
            clearInterval(this.timer);
        },
        confirmStep() {
            if (this.step < this.times) {
                this.step++;
                this.startTimer();
                return;
            }
            this.closeModal();
            {{ $onConfirm }};
        },
    }"
    class="contents"
>
    <span x-on:click="openModal()" class="contents">{{ $trigger }}</span>

    <div
        x-show="open"
        x-on:keydown.escape.window="closeModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        style="display: none;"
    >
        <div x-on:click.outside="closeModal()" class="w-full max-w-sm rounded-xl bg-white p-5 shadow-lg">
            <h2 class="text-base font-bold text-zinc-900">{{ $heading }}</h2>
            <p class="mt-2 text-sm text-zinc-600">{{ $description }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" x-on:click="closeModal()"
                        class="rounded-lg px-4 py-2 text-sm font-semibold text-zinc-600 hover:bg-zinc-100">
                    {{ $cancelLabel }}
                </button>
                <button
                    type="button"
                    x-bind:disabled="remaining > 0"
                    x-on:click="confirmStep()"
                    x-text="remaining > 0
                        ? `{{ $confirmLabel }} (${remaining})`
                        : (times > 1 ? `{{ $confirmLabel }} (${step}/${times})` : `{{ $confirmLabel }}`)"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                ></button>
            </div>
        </div>
    </div>
</div>
