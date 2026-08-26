<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Entrar — OddRadar</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-slate-950 px-4 text-slate-100">
        <main class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-7 shadow-2xl shadow-slate-950/50 sm:p-8">
            <p class="text-sm font-semibold tracking-[0.2em] text-cyan-400">FIREBETS · USO INTERNO</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">OddRadar</h1>
            <p class="mt-2 text-sm text-slate-400">Entre para consultar o monitoramento de odds.</p>

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
                    <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none ring-cyan-400 focus:ring-2" id="password" name="password" type="password" autocomplete="current-password" required>
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
    </body>
</html>
