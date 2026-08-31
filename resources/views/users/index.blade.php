<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Usuários — OddRadar</title>
        <meta name="theme-color" content="#020617">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('pwa-icon-192.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <main class="mx-auto max-w-5xl px-4 py-5 sm:px-6 sm:py-8 lg:px-8">
            <header class="flex flex-col justify-between gap-5 border-b border-slate-800 pb-7 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold tracking-[0.2em] text-cyan-400">FIREBETS · USO INTERNO</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">Usuários</h1>
                    <p class="mt-2 text-sm text-slate-400">Apenas o super administrador pode criar e editar operadores.</p>
                </div>
                <a class="inline-flex w-full items-center justify-center rounded-lg border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-800 sm:w-auto" href="{{ route('dashboard') }}">Voltar ao painel</a>
            </header>

            @if (session('status'))
                <div class="mt-6 rounded-lg border border-cyan-400/30 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100" role="status">{{ session('status') }}</div>
            @endif

            <section class="mt-8 rounded-xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white">Adicionar operador</h2>
                <form class="mt-5 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('users.store') }}">
                    @csrf
                    <div>
                        <label class="mb-2 block text-sm text-slate-300" for="new-name">Nome</label>
                        <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="new-name" name="name" value="{{ old('name') }}" required>
                        @error('name') <p class="mt-1 text-xs text-rose-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm text-slate-300" for="new-email">E-mail</label>
                        <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="new-email" name="email" type="email" value="{{ old('email') }}" required>
                        @error('email') <p class="mt-1 text-xs text-rose-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm text-slate-300" for="new-password">Senha</label>
                        <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="new-password" name="password" type="password" autocomplete="new-password" required>
                        @error('password') <p class="mt-1 text-xs text-rose-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm text-slate-300" for="new-password-confirmation">Confirmar senha</label>
                        <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="new-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                    <div class="sm:col-span-2">
                        <button class="rounded-lg bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-cyan-300" type="submit">Criar operador</button>
                    </div>
                </form>
            </section>

            <section class="mt-8 space-y-4" aria-labelledby="usuarios-cadastrados">
                <h2 class="text-lg font-semibold text-white" id="usuarios-cadastrados">Usuários cadastrados</h2>
                @foreach ($users as $user)
                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                        @if ($user->isSuperAdmin())
                            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <div>
                                    <h3 class="font-semibold text-white">{{ $user->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-400">{{ $user->email }}</p>
                                </div>
                                <span class="rounded-full bg-cyan-400/15 px-2.5 py-1 text-xs font-semibold text-cyan-200">Super admin</span>
                            </div>
                        @else
                            <form class="grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('users.update', $user) }}">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="mb-2 block text-sm text-slate-300" for="name-{{ $user->id }}">Nome</label>
                                    <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="name-{{ $user->id }}" name="name" value="{{ $user->name }}" required>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm text-slate-300" for="email-{{ $user->id }}">E-mail</label>
                                    <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="email-{{ $user->id }}" name="email" type="email" value="{{ $user->email }}" required>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm text-slate-300" for="password-{{ $user->id }}">Nova senha <span class="text-slate-500">(opcional)</span></label>
                                    <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="password-{{ $user->id }}" name="password" type="password" autocomplete="new-password">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm text-slate-300" for="password-confirmation-{{ $user->id }}">Confirmar nova senha</label>
                                    <input class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" id="password-confirmation-{{ $user->id }}" name="password_confirmation" type="password" autocomplete="new-password">
                                </div>
                                <div class="sm:col-span-2">
                                    <button class="w-full rounded-lg border border-cyan-400/50 px-4 py-2.5 text-sm font-semibold text-cyan-200 hover:bg-cyan-400/10 sm:w-auto" type="submit">Salvar alterações</button>
                                </div>
                            </form>
                        @endif
                    </article>
                @endforeach
            </section>
        </main>
    </body>
</html>
