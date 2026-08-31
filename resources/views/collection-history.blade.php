<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OddRadar — Histórico</title>
        <meta name="theme-color" content="#020617">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('pwa-icon-192.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <main class="mx-auto max-w-6xl px-4 py-5 sm:px-6 sm:py-8 lg:px-8">
            <header class="flex flex-col justify-between gap-5 border-b border-slate-800 pb-6 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold tracking-[.2em] text-cyan-400">ODDRADAR</p>
                    <h1 class="mt-1 text-2xl font-semibold text-white sm:text-3xl">Histórico de coletas</h1>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:flex">
                    <a class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-800" href="{{ route('dashboard') }}">Voltar ao painel</a>
                    <form class="contents sm:block" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="inline-flex w-full items-center justify-center rounded-lg border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-slate-800" type="submit">Sair</button>
                    </form>
                </div>
            </header>

            <p class="mt-6 text-xs text-slate-500 sm:hidden">Deslize a tabela para o lado para ver os detalhes das fontes.</p>
            <div class="-mx-4 mt-3 overflow-x-auto border-y border-slate-800 bg-slate-900 sm:mx-0 sm:mt-8 sm:rounded-xl sm:border sm:px-0">
                <table class="min-w-[36rem] text-left text-sm sm:min-w-full">
                    <thead class="bg-slate-950/50 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Atualização</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Fontes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($collectionRuns as $run)
                            <tr>
                                <td class="px-5 py-4">{{ $run->finished_at?->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') ?? 'Em processamento' }}</td>
                                <td class="px-4 py-4">{{ $run->status }}</td>
                                <td class="px-4 py-4">
                                    @foreach ($run->sourceResults as $result)
                                        <span class="mr-2 inline-block">{{ $result->bookmaker->name }}: {{ $result->status === 'completed' ? $result->events_count.' eventos' : ($result->status === 'empty' ? 'sem eventos' : 'falhou') }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-8 text-slate-400" colspan="3">Nenhuma coleta registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $collectionRuns->links() }}</div>
        </main>
    </body>
</html>
