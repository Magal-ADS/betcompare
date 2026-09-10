<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Entrar — OddRadar</title>
        <meta name="theme-color" content="#020617">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('pwa-icon-192.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-6 text-slate-100">
        <main class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-2xl shadow-slate-950/50 sm:p-8">
            <p class="text-sm font-semibold tracking-[0.2em] text-cyan-400">FIREBETS · USO INTERNO</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">OddRadar</h1>
            <p class="mt-2 text-sm text-slate-400">Entre para consultar o monitoramento de odds.</p>
            <button data-pwa-install class="mt-5 w-full rounded-lg border border-cyan-400/50 px-4 py-3 text-sm font-semibold text-cyan-200 hover:bg-cyan-400/10" type="button" hidden>Instalar app neste dispositivo</button>

            <form class="mt-7 space-y-5" method="POST" action="{{ route('login.store') }}">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200" for="email">E-mail</label>
                    <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none ring-cyan-400 focus:ring-2" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    @error('email')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200" for="password">Senha</label>
                    <div class="relative">
                        <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 pr-24 text-sm text-white outline-none ring-cyan-400 focus:ring-2" id="password" name="password" type="password" autocomplete="current-password" required>
                        <button aria-controls="password" aria-label="Mostrar senha" aria-pressed="false" class="absolute inset-y-0 right-0 flex items-center gap-1 px-3 text-xs font-medium text-slate-300 hover:text-cyan-300 focus:outline-none focus-visible:text-cyan-300" data-password-visibility-toggle type="button">
                            <svg aria-hidden="true" class="size-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24">
                                <path d="M2.25 12S5.75 5.25 12 5.25 21.75 12 21.75 12 18.25 18.75 12 18.75 2.25 12 2.25 12Z" />
                                <circle cx="12" cy="12" r="2.75" />
                            </svg>
                            <span data-password-visibility-label>Mostrar</span>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input class="rounded border-slate-700 bg-slate-950 text-cyan-400 focus:ring-cyan-400" name="remember" type="checkbox" value="1">
                    Manter sessão neste dispositivo
                </label>
                <button class="w-full rounded-lg bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" type="submit">Entrar</button>
            </form>
        </main>

        <script>
            const passwordInput = document.getElementById('password');
            const passwordVisibilityToggle = document.querySelector('[data-password-visibility-toggle]');
            const passwordVisibilityLabel = document.querySelector('[data-password-visibility-label]');

            passwordVisibilityToggle?.addEventListener('click', () => {
                if (passwordInput === null || passwordVisibilityLabel === null) {
                    return;
                }

                const isPasswordVisible = passwordInput.type === 'password';

                passwordInput.type = isPasswordVisible ? 'text' : 'password';
                passwordVisibilityToggle.setAttribute('aria-pressed', String(isPasswordVisible));
                passwordVisibilityToggle.setAttribute('aria-label', isPasswordVisible ? 'Ocultar senha' : 'Mostrar senha');
                passwordVisibilityLabel.textContent = isPasswordVisible ? 'Ocultar' : 'Mostrar';
            });
        </script>
    </body>
</html>
