<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OddRadar — Monitoramento de odds</title>
        <meta name="theme-color" content="#020617">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('pwa-icon-192.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-8 lg:px-8">
            <header class="flex flex-col justify-between gap-6 border-b border-slate-800 pb-8 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold tracking-[0.2em] text-cyan-400">FIREBETS · USO INTERNO</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white sm:text-4xl">OddRadar</h1>
                    <p class="mt-2 text-slate-400">Monitore suas odds. Compare a concorrência.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap">
                    @if (auth()->user()?->isSuperAdmin())
                        <a class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-800" href="{{ route('users.index') }}">Usuários</a>
                    @endif
                    <a class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-800" href="{{ route('collection-history') }}">Histórico</a>
                    <button data-pwa-install class="inline-flex items-center justify-center rounded-lg border border-cyan-400/50 px-4 py-3 text-sm font-semibold text-cyan-200 hover:bg-cyan-400/10" type="button" hidden>Instalar app</button>
                    <form class="contents sm:block" method="POST" action="{{ route('odds.refresh') }}">
                        @csrf
                        <button data-loading-button class="inline-flex w-full items-center justify-center rounded-lg bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-wait disabled:opacity-70 sm:w-auto" type="submit">
                            <span data-loading-label>Atualizar odds agora</span>
                        </button>
                    </form>
                    <form class="contents sm:block" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-slate-800" type="submit">Sair</button>
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
                        @php($lastSuccessfulResult = $lastSuccessfulSourceResults->get($bookmaker->id))
                        <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                            <div class="flex flex-col items-start gap-2 sm:flex-row sm:justify-between sm:gap-3">
                                <div class="min-w-0">
                                    <h3 class="font-medium text-white">{{ $bookmaker->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-400">
                                        @if ($result?->status === 'completed')
                                            {{ $result->events_count }} eventos coletados
                                        @elseif ($result?->status === 'empty')
                                            Nenhum evento disponível nesta coleta
                                        @elseif ($result?->status === 'failed')
                                            Fonte indisponível nesta coleta
                                        @elseif ($result?->status === 'rate_limited')
                                            A fonte aplicou um limite temporário
                                        @elseif ($result?->status === 'deferred')
                                            Consulta adiada para proteger a fonte
                                        @else
                                            Aguardando coleta
                                        @endif
                                    </p>
                                </div>
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-400/15 text-emerald-300' => $result?->status === 'completed',
                                    'bg-amber-400/15 text-amber-200' => $result?->status === 'empty',
                                    'bg-rose-400/15 text-rose-300' => in_array($result?->status, ['failed', 'rate_limited'], true),
                                    'bg-amber-400/15 text-amber-200' => $result?->status === 'deferred',
                                    'bg-slate-800 text-slate-400' => $result === null,
                                ])>
                                    {{ $result?->status === 'completed' ? 'Disponível' : ($result?->status === 'empty' ? 'Sem eventos' : ($result?->status === 'rate_limited' ? 'Limitada' : ($result?->status === 'deferred' ? 'Em espera' : ($result?->status === 'failed' ? 'Falhou' : 'Sem dados')))) }}
                                </span>
                            </div>
                            @if ($result?->displayErrorMessage())
                                <p @class([
                                    'mt-3 text-xs leading-5',
                                    'text-amber-200' => $result->status === 'deferred',
                                    'text-rose-300' => $result->status !== 'deferred',
                                ])>{{ $result->displayErrorMessage() }}</p>
                            @endif
                            @if (in_array($result?->status, ['failed', 'rate_limited', 'deferred'], true) && $lastSuccessfulResult?->collected_at)
                                <p class="mt-2 text-xs text-slate-500">
                                    Último dado válido: {{ $lastSuccessfulResult->collected_at->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}
                                </p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-slate-400">As fontes serão registradas na primeira atualização.</p>
                    @endforelse
                </div>
            </section>

            <section class="mt-10" aria-labelledby="comparacao-de-odds">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <h2 id="comparacao-de-odds" class="text-xl font-semibold text-white">Comparação de odds</h2>
                        <p class="mt-1 text-sm text-slate-400">Somente jogos da semana e mercados definidos para análise.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                        <span class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1.5">
                            Semana: {{ $week['start']->format('d/m') }} a {{ $week['end']->format('d/m') }}
                        </span>
                        <span>Diferença: (Firebets − concorrente) ÷ concorrente</span>
                    </div>
                </div>

                <form data-filter-funnel class="mt-5 rounded-xl border border-slate-800 bg-slate-900 p-4" method="GET" action="{{ route('dashboard') }}">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="grid gap-1.5 text-xs font-medium text-slate-400">
                            1. Região
                            <select data-filter-region class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white" name="region">
                                <option value="">Todas as regiões</option>
                                @foreach ($filterOptions['regions'] as $region)
                                    <option value="{{ $region['value'] }}" @selected(($filters['region'] ?? '') === $region['value'])>{{ $region['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-xs font-medium text-slate-400">
                            2. País
                            <select data-filter-country class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white" name="country">
                                <option value="">Todos os países</option>
                                @foreach ($filterOptions['countries'] as $country)
                                    <option value="{{ $country['value'] }}" data-region="{{ $country['region'] }}" @selected(($filters['country'] ?? '') === $country['value'])>{{ $country['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-xs font-medium text-slate-400">
                            3. Campeonato
                            <select data-filter-competition class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white" name="competition">
                                <option value="">Todos os campeonatos</option>
                                @foreach ($filterOptions['competitions'] as $competition)
                                    <option value="{{ $competition['value'] }}" data-region="{{ $competition['region'] }}" data-country="{{ $competition['country'] }}" @selected(($filters['competition'] ?? '') === $competition['value'])>{{ $competition['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-xs font-medium text-slate-400">
                            4. Jogo
                            <select data-filter-game class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white" name="game">
                                <option value="">Todos os jogos</option>
                                @foreach ($filterOptions['games'] as $game)
                                    <option value="{{ $game['value'] }}" data-region="{{ $game['region'] }}" data-country="{{ $game['country'] }}" data-competition="{{ $game['competition'] }}" @selected((string) ($filters['game'] ?? '') === (string) $game['value'])>{{ $game['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                        <label class="grid gap-1.5 text-xs font-medium text-slate-400">
                            Buscar jogo ou time
                            <input class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ex.: Flamengo x Palmeiras">
                        </label>

                        <label class="grid gap-1.5 text-xs font-medium text-slate-400">
                            Ordenar
                            <select class="min-w-0 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white" name="sort">
                                <option value="time" @selected(($filters['sort'] ?? 'time') === 'time')>Horário</option>
                                <option value="difference_desc" @selected(($filters['sort'] ?? '') === 'difference_desc')>Maior diferença</option>
                                <option value="difference_asc" @selected(($filters['sort'] ?? '') === 'difference_asc')>Menor diferença</option>
                                <option value="team" @selected(($filters['sort'] ?? '') === 'team')>Time</option>
                            </select>
                        </label>

                        <div class="flex items-end gap-2">
                            <button class="w-full rounded-lg border border-cyan-400/50 px-4 py-2.5 text-sm font-semibold text-cyan-200 hover:bg-cyan-400/10 md:w-auto" type="submit">Aplicar</button>
                            @if ($filters !== [])
                                <a class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400 hover:text-white" href="{{ route('dashboard') }}">Limpar</a>
                            @endif
                        </div>
                    </div>
                </form>

                @forelse ($comparisons as $comparison)
                    @php($defaultMarketKey = $comparison['markets']->firstWhere('key', 'match_winner')['key'] ?? $comparison['markets']->first()['key'])
                    <article data-event-analysis class="mt-5 overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                        <header class="grid gap-4 border-b border-slate-800 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,22rem)] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-white">{{ $comparison['event']->home_team }} <span class="text-slate-500">x</span> {{ $comparison['event']->away_team }}</h3>
                                    @if (! $comparison['is_compared'])
                                        <span class="rounded-full bg-amber-400/15 px-2.5 py-1 text-xs font-semibold text-amber-200">Ainda não comparado</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-400">
                                    {{ collect([$comparison['event']->country, $comparison['event']->competition])->filter()->join(' · ') ?: 'Futebol' }}
                                    @if ($comparison['event']->starts_at)
                                        · {{ $comparison['event']->starts_at->setTimezone(config('app.display_timezone'))->format('d/m H:i') }}
                                    @endif
                                </p>
                            </div>

                            <label class="grid gap-1.5 text-xs font-medium text-slate-400">
                                Análise
                                <select data-market-picker class="min-w-0 rounded-lg border border-cyan-400/40 bg-slate-950 px-3 py-2.5 text-sm font-semibold text-cyan-100">
                                    @foreach ($comparison['markets'] as $market)
                                        <option value="{{ $market['key'] }}" @selected($market['key'] === $defaultMarketKey)>{{ $market['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </header>

                        @foreach ($comparison['markets'] as $market)
                            <section data-market-panel="{{ $market['key'] }}" @if ($market['key'] !== $defaultMarketKey) hidden @endif>
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/70 bg-slate-950/30 px-5 py-3">
                                    <h4 class="text-sm font-semibold text-white">{{ $market['name'] }}</h4>
                                    <span class="text-xs text-slate-500">{{ $market['selections']->count() }} possibilidades</span>
                                </div>
                                <p class="px-4 pt-3 text-xs text-slate-500 sm:hidden">Deslize a tabela para o lado para ver todas as casas.</p>
                                <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                                    <table class="min-w-[52rem] text-left text-sm sm:min-w-full">
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
                                            @foreach ($market['selections'] as $selection)
                                                <tr>
                                                    <th class="min-w-44 px-5 py-4 font-medium text-white">{{ $selection['label'] }}</th>
                                                    @foreach ($bookmakers as $bookmaker)
                                                        @php($odd = $selection['odds_by_source']->get($bookmaker->slug))
                                                        @php($individualDifference = $selection['difference_percent_by_source']->get($bookmaker->slug))
                                                        <td class="px-4 py-4">
                                                            <span class="font-mono text-slate-200">{{ $odd === null ? '—' : number_format((float) $odd, 2, ',', '.') }}</span>
                                                            @if ($individualDifference !== null)
                                                                <span @class([
                                                                    'mt-1 block whitespace-nowrap text-[0.68rem] font-semibold',
                                                                    'text-emerald-300' => $individualDifference > 0,
                                                                    'text-rose-300' => $individualDifference < 0,
                                                                    'text-slate-400' => $individualDifference == 0,
                                                                ])>
                                                                    Firebets {{ $individualDifference > 0 ? '↑ +' : ($individualDifference < 0 ? '↓ ' : '= ') }}{{ number_format($individualDifference, 2, ',', '.') }}%
                                                                </span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="px-4 py-4 text-slate-300">
                                                        @if ($selection['best_reference'])
                                                            <span class="font-mono text-white">{{ number_format($selection['best_reference']['odd'], 2, ',', '.') }}</span>
                                                            <span class="ml-1 text-xs text-slate-500">{{ $bookmakers->firstWhere('slug', $selection['best_reference']['source'])?->name }}</span>
                                                        @else
                                                            <span class="text-slate-500">Sem referência</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        @if ($selection['difference_percent'] === null)
                                                            <span class="text-slate-500">—</span>
                                                        @else
                                                            <span @class([
                                                                'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold',
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
                            </section>
                        @endforeach
                    </article>
                @empty
                    <div class="mt-5 rounded-xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-12 text-center">
                        <h3 class="text-base font-semibold text-white">Nenhum jogo encontrado nesta semana</h3>
                        <p class="mt-2 text-sm text-slate-400">Ajuste os filtros ou use “Atualizar odds agora” para consultar as fontes públicas.</p>
                    </div>
                @endforelse

                @if ($comparisons->hasPages())
                    <div class="mt-6">{{ $comparisons->links() }}</div>
                @endif
            </section>
        </main>
    </body>
</html>
