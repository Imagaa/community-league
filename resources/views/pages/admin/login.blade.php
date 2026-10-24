<!DOCTYPE html>
<html lang="id" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex, nofollow" />
        <title>Masuk Admin - {{ config('app.name') }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-navy-950 p-4 text-sm text-zinc-300 antialiased">
        <form method="POST" action="{{ route('admin.login.attempt') }}" class="w-full max-w-sm space-y-4 rounded-2xl border border-navy-800 bg-navy-900 p-6">
            @csrf
            <img src="{{ asset('images/secondary-logo-landscape.webp') }}" alt="{{ config('app.name') }}" class="mx-auto mb-2 h-10 w-auto object-contain">
            <h1 class="text-center text-lg font-semibold text-white">Masuk Admin</h1>

            @error('email')
                <p class="rounded-lg bg-red-950 px-3 py-2 text-red-300">{{ $message }}</p>
            @enderror

            <label class="block space-y-1">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="w-full rounded-lg border border-navy-700 bg-navy-950 px-3 py-2.5 text-white focus:border-orange-500 focus:outline-none">
            </label>
            <label class="block space-y-1">
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-navy-700 bg-navy-950 px-3 py-2.5 text-white focus:border-orange-500 focus:outline-none">
            </label>
            <label class="flex cursor-pointer items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="peer sr-only">
                <span class="flex size-5 items-center justify-center rounded-md border border-navy-700 bg-navy-950 text-transparent peer-checked:border-orange-600 peer-checked:bg-orange-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-orange-500">
                    <flux:icon name="check" variant="micro" />
                </span>
                Ingat saya
            </label>

            <button type="submit" class="w-full rounded-lg bg-orange-600 py-2.5 font-semibold text-white hover:bg-orange-700">Masuk</button>
        </form>
    </body>
</html>
