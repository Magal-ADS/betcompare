<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OddRadar — Monitoramento de odds</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col justify-between gap-6 border-b border-slate-800 pb-8 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold tracking-[0.2em] text-cyan-400">FIREBETS · USO INTERNO</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">OddRadar</h1>
                    <p class="mt-2 text-slate-400">Monitore suas odds. Compare a concorrência.</p>
                </div>

                <div class="flex gap-3">
                    <a class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-800" href="{{ route('collection-history') }}">Histórico</a>
                <form method="POST" action="{{ route('odds.refresh') }}">
                    @csrf
                    <button data-loading-button class="inline-flex w-full items-center justify-center rounded-lg bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-wait disabled:opacity-70 sm:w-auto" type="submit">
                        <span data-loading-label>Atualizar odds agora</span>
                    </button>
                </form>
                </div>
            </header>

            @if (session('status'))
                <div class="mt-6 rounded-lg border border-cyan-400/30 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <section class="mt-8" aria-labelledby="status-das-fontes">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <h2 id="status-das-fontes" class="text-lg font-semibold text-white">Status das fontes</h2>
                    <p class="text-sm text-slate-400">
                        @if ($collectionRun?->finished_at)
                            Última atualização: {{ $collectionRun->finished_at->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}
                        @else
                            Nenhuma coleta realizada.
                        @endif
                    </p>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse ($bookmakers as $bookmaker)
                        @php($result = $sourceResults->get($bookmaker->id))
                        <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-medium text-white">{{ $bookmaker->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-400">
                                        @if ($result?->status === 'completed')
                                            {{ $result->events_count }} eventos coletados
                                        @elseif ($result?->status === 'failed')
                                            Fonte indisponível nesta coleta
                                        @else
                                            Aguardando coleta
                                        @endif
                                    </p>
                                </div>
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-400/15 text-emerald-300' => $result?->status === 'completed',
                                    'bg-rose-400/15 text-rose-300' => $result?->status === 'failed',
                                    'bg-slate-800 text-slate-400' => $result === null,
                                ])>
                                    {{ $result?->status === 'completed' ? 'Disponível' : ($result?->status === 'failed' ? 'Falhou' : 'Sem dados') }}
                                </span>
                            </div>
                            @if ($result?->error_message)
                                <p class="mt-3 text-xs leading-5 text-rose-300">{{ $result->error_message }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-slate-400">As fontes serão registradas na primeira atualização.</p>
                    @endforelse
                </div>
            </section>

            <section class="mt-10" aria-labelledby="comparacao-de-odds">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h2 id="comparacao-de-odds" class="text-xl font-semibold text-white">Comparação de odds</h2>
                        <p class="mt-1 text-sm text-slate-400">A referência é a maior odd concorrente válida por seleção.</p>
                    </div>
                    <p class="text-xs text-slate-500">Diferença: (Firebets − melhor concorrente) ÷ melhor concorrente</p>
                </div>
                <form class="mt-5 grid gap-3 rounded-xl border border-slate-800 bg-slate-900 p-4 sm:grid-cols-4" method="GET" action="{{ route('dashboard') }}">
                    <input class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-500" name="team" value="{{ $filters['team'] ?? '' }}" placeholder="Filtrar por time">
                    <input class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" type="date" name="date" value="{{ $filters['date'] ?? '' }}" aria-label="Filtrar por data">
                    <select class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" name="sort">
                        <option value="time" @selected(($filters['sort'] ?? 'time') === 'time')>Data e horário</option>
                        <option value="difference_desc" @selected(($filters['sort'] ?? '') === 'difference_desc')>Maior diferença</option>
                        <option value="difference_asc" @selected(($filters['sort'] ?? '') === 'difference_asc')>Menor diferença</option>
                        <option value="team" @selected(($filters['sort'] ?? '') === 'team')>Time</option>
                    </select>
                    <button class="rounded-lg border border-cyan-400/50 px-4 py-2 text-sm font-semibold text-cyan-200 hover:bg-cyan-400/10" type="submit">Aplicar filtros</button>
                </form>

                @forelse ($comparisons as $comparison)
                    <article class="mt-5 overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                        <header class="flex flex-col justify-between gap-2 border-b border-slate-800 px-5 py-4 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-white">{{ $comparison['event']->home_team }} <span class="text-slate-500">x</span> {{ $comparison['event']->away_team }}</h3>
                                <p class="mt-1 text-sm text-slate-400">
                                    Futebol · Resultado final (1X2)
                                    @if ($comparison['event']->event_date || $comparison['event']->event_time)
                                        · {{ collect([$comparison['event']->event_date, $comparison['event']->event_time])->filter()->join(' ') }}
                                    @endif
                                </p>
                            </div>
                            @if (! $comparison['is_compared'])
                                <span class="w-fit rounded-full bg-amber-400/15 px-2.5 py-1 text-xs font-semibold text-amber-200">Ainda não comparado</span>
                            @endif
                        </header>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950/50 text-xs uppercase tracking-wide text-slate-400">
                                    <tr>
                                        <th class="px-5 py-3 font-medium">Seleção</th>
                                        @foreach ($bookmakers as $bookmaker)
                                            <th class="px-4 py-3 font-medium">{{ $bookmaker->name }}</th>
                                        @endforeach
                                        <th class="px-4 py-3 font-medium">Melhor concorrente</th>
                                        <th class="px-4 py-3 font-medium">Diferença</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    @foreach ($comparison['selections'] as $selection)
                                        <tr>
                                            <th class="whitespace-nowrap px-5 py-4 font-medium text-white">{{ $selection['label'] }}</th>
                                            @foreach ($bookmakers as $bookmaker)
                                                @php($odd = $selection['odds_by_source']->get($bookmaker->slug))
                                                <td class="px-4 py-4 font-mono text-slate-200">{{ $odd === null ? '—' : number_format((float) $odd, 2, ',', '.') }}</td>
                                            @endforeach
                                            <td class="px-4 py-4 text-slate-300">
                                                @if ($selection['best_reference'])
                                                    <span class="font-mono text-white">{{ number_format($selection['best_reference']['odd'], 2, ',', '.') }}</span>
                                                    <span class="ml-1 text-xs text-slate-500">{{ $bookmakers->firstWhere('slug', $selection['best_reference']['source'])->name }}</span>
                                                @else
                                                    <span class="text-slate-500">Sem referência</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4">
                                                @if ($selection['difference_percent'] === null)
                                                    <span class="text-slate-500">—</span>
                                                @else
                                                    <span @class([
                                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold',
                                                        'bg-emerald-400/15 text-emerald-300' => $selection['state'] === 'above',
                                                        'bg-rose-400/15 text-rose-300' => $selection['state'] === 'below',
                                                        'bg-slate-800 text-slate-300' => $selection['state'] === 'equal',
                                                    ])>
                                                        {{ $selection['state'] === 'above' ? '↑ Acima' : ($selection['state'] === 'below' ? '↓ Abaixo' : '= Igual') }}
                                                        {{ number_format($selection['difference_percent'], 2, ',', '.') }}%
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </article>
                @empty
                    <div class="mt-5 rounded-xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-12 text-center">
                        <h3 class="text-base font-semibold text-white">Nenhuma odd disponível ainda</h3>
                        <p class="mt-2 text-sm text-slate-400">Use “Atualizar odds agora” para coletar as fontes públicas configuradas.</p>
                    </div>
                @endforelse
                @if ($comparisons->hasPages())
                    <div class="mt-6">{{ $comparisons->links() }}</div>
                @endif
            </section>
        </main>
    </body>
</html>
