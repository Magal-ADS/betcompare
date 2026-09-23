<?php

namespace Tests\Feature\Collectors;

use App\Collectors\FirebetsCollector;
use App\Collectors\OddsCollectorRegistry;
use App\Collectors\PartialCollectionRateLimited;
use App\Services\Collection\CollectOddsAction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebetsCollectorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_approved_markets_and_location_from_public_firebets_html(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-26 16:26:00', 'UTC'));
        config()->set('services.bookmakers.firebets.games_url', 'https://firebets.test/simulador/jogos.aspx?idcampeonato=old');
        Http::preventStrayRequests();
        Http::fake([
            'https://firebets.test/simulador/jogos.aspx?idcampeonato=old' => Http::response($this->firebetsDailyMenuHtml()),
            'https://firebets.test/simulador/jogos.aspx?idesporte=102&idcampeonato=today' => Http::response($this->firebetsHtml()),
            'https://firebets.test/simulador/Apostas.aspx?idesporte=102&idpartida=123' => Http::response($this->marketDetailsHtml()),
        ]);

        $events = app(FirebetsCollector::class)->collect();

        $this->assertCount(1, $events);

        $event = $events->sole();

        $this->assertSame('firebets', $event->source);
        $this->assertSame('Flamengo RJ', $event->homeTeam);
        $this->assertSame('Palmeiras SP', $event->awayTeam);
        $this->assertSame('26/ago', $event->eventDate);
        $this->assertSame('21:30', $event->eventTime);
        $this->assertSame('2026-08-27 00:30:00', $event->startsAt?->toDateTimeString());
        $this->assertSame('UTC', $event->startsAt?->timezoneName);
        $this->assertSame('america', $event->region);
        $this->assertSame('Brasil', $event->country);
        $this->assertSame('BRA', $event->countryCode);
        $this->assertSame('Brasileirão Série A', $event->competition);
        $this->assertSame(['match_winner', 'total_goals'], array_keys($event->markets));
        $this->assertSame(1.85, $event->markets['match_winner']->selections[0]['odd']);
        $this->assertSame('Mais de 1,5', $event->markets['total_goals']->selections[0]['label']);
        $this->assertSame(1.7, $event->markets['total_goals']->selections[0]['odd']);
        $this->assertSame(now()->toAtomString(), $event->collectedAt->toAtomString());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://firebets.test/simulador/jogos.aspx?idcampeonato=old'
                && $request->method() === 'GET';
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://firebets.test/simulador/jogos.aspx?idesporte=102&idcampeonato=today'
                && $request->method() === 'GET';
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://firebets.test/simulador/Apostas.aspx?idesporte=102&idpartida=123'
                && $request->method() === 'GET';
        });
        Http::assertSentCount(3);
    }

    public function test_throws_when_the_firebets_page_cannot_be_collected(): void
    {
        config()->set('services.bookmakers.firebets.games_url', 'https://firebets.test/simulador/jogos.aspx?idcampeonato=old');
        Http::preventStrayRequests();
        Http::fake([
            'https://firebets.test/simulador/jogos.aspx?idcampeonato=old' => Http::response('', 503),
        ]);

        $exception = null;

        try {
            app(FirebetsCollector::class)->collect();
        } catch (RequestException $caughtException) {
            $exception = $caughtException;
        }

        $this->assertInstanceOf(RequestException::class, $exception);

        Http::assertSentCount(3);
    }

    public function test_keeps_available_weekly_pages_when_another_day_reaches_the_source_limit(): void
    {
        config()->set('services.bookmakers.firebets.games_url', 'https://firebets.test/simulador/jogos.aspx?idcampeonato=old');
        Http::preventStrayRequests();
        Http::fake([
            'https://firebets.test/simulador/jogos.aspx?idcampeonato=old' => Http::response($this->firebetsTwoDayMenuHtml()),
            'https://firebets.test/simulador/jogos.aspx?idesporte=102&idcampeonato=today' => Http::response($this->firebetsHtml()),
            'https://firebets.test/simulador/jogos.aspx?idesporte=102&idcampeonato=tomorrow' => Http::response('error code: 1015', 429),
            'https://firebets.test/simulador/Apostas.aspx?idesporte=102&idpartida=123' => Http::response($this->marketDetailsHtml()),
        ]);

        $exception = null;

        try {
            app(FirebetsCollector::class)->collect();
        } catch (PartialCollectionRateLimited $caughtException) {
            $exception = $caughtException;
        }

        $this->assertInstanceOf(PartialCollectionRateLimited::class, $exception);
        $this->assertCount(1, $exception->events);
        $this->assertSame('Flamengo RJ', $exception->events->sole()->homeTeam);
        $this->assertSame(429, $exception->rateLimitException->response->status());
        Http::assertSentCount(3);
    }

    public function test_persists_available_odds_and_defers_a_source_after_a_weekly_page_is_limited(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-26 16:26:00', 'UTC'));
        config()->set('services.bookmakers.firebets.games_url', 'https://firebets.test/simulador/jogos.aspx?idcampeonato=old');
        Http::preventStrayRequests();
        Http::fake([
            'https://firebets.test/simulador/jogos.aspx?idcampeonato=old' => Http::response($this->firebetsTwoDayMenuHtml()),
            'https://firebets.test/simulador/jogos.aspx?idesporte=102&idcampeonato=today' => Http::response($this->firebetsHtml()),
            'https://firebets.test/simulador/jogos.aspx?idesporte=102&idcampeonato=tomorrow' => Http::response('error code: 1015', 429, ['Retry-After' => '7200']),
        ]);
        $collector = app(FirebetsCollector::class);
        $this->mock(OddsCollectorRegistry::class, function ($mock) use ($collector): void {
            $mock->shouldReceive('all')->twice()->andReturn([$collector]);
        });

        $limitedRun = app(CollectOddsAction::class)->execute();
        $deferredRun = app(CollectOddsAction::class)->execute();

        $limitedResult = $limitedRun->sourceResults()->firstOrFail();
        $this->assertSame('failed', $limitedRun->status);
        $this->assertSame('rate_limited', $limitedResult->status);
        $this->assertSame(1, $limitedResult->events_count);
        $this->assertSame(now()->addHours(2)->toDateTimeString(), $limitedResult->retry_at->toDateTimeString());
        $this->assertSame('deferred', $deferredRun->status);
        $this->assertDatabaseCount('market_odds', 3);
        $this->assertDatabaseHas('events', [
            'home_team' => 'Flamengo RJ',
            'starts_at' => '2026-08-27 00:30:00',
        ]);
        Http::assertSentCount(3);
    }

    public function test_keeps_listed_odds_when_a_details_page_is_rate_limited(): void
    {
        config()->set('services.bookmakers.firebets.games_url', 'https://firebets.test/simulador/jogos.aspx?idcampeonato=old');
        Http::preventStrayRequests();
        Http::fake([
            'https://firebets.test/simulador/jogos.aspx?idcampeonato=old' => Http::response($this->firebetsDailyMenuHtml()),
            'https://firebets.test/simulador/jogos.aspx?idesporte=102&idcampeonato=today' => Http::response($this->firebetsHtml()),
            'https://firebets.test/simulador/Apostas.aspx?idesporte=102&idpartida=123' => Http::response('error code: 1015', 403),
        ]);

        try {
            app(FirebetsCollector::class)->collect();
            $this->fail('Expected the source limit to be reported.');
        } catch (PartialCollectionRateLimited $exception) {
            $this->assertSame(403, $exception->rateLimitException->response->status());
            $this->assertSame(['match_winner'], array_keys($exception->events->sole()->markets));
            $this->assertSame(1.85, $exception->events->sole()->markets['match_winner']->selections[0]['odd']);
        }

        Http::assertSentCount(3);
    }

    private function firebetsDailyMenuHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<div class="submenuItem">
    <div class="submenuItem-main"><span class="name">Jogos do Dia</span></div>
    <div class="submenuItem-level3">
        <a href="jogos.aspx?idesporte=102&amp;idcampeonato=today">Quinta-Feira</a>
    </div>
</div>
HTML;
    }

    private function firebetsTwoDayMenuHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<div class="submenuItem">
    <div class="submenuItem-main"><span class="name">Jogos do Dia</span></div>
    <div class="submenuItem-level3">
        <a href="jogos.aspx?idesporte=102&amp;idcampeonato=today">Quinta-Feira</a>
        <a href="jogos.aspx?idesporte=102&amp;idcampeonato=tomorrow">Sexta-Feira</a>
    </div>
</div>
HTML;
    }

    private function firebetsHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="pt-BR">
    <body>
        <div class="pais">
            <div class="eventlist-country">
                <span class="flag flag_small flag-BRA"></span>
                <span class="name">Brasil - Brasileirão Série A</span>
            </div>
            <article class="cardItem">
                <span class="date">26/ago</span>
                <span class="hour">21:30</span>
                <div class="teams">
                    <div class="team"><div class="nameTeam"><span>Flamengo RJ</span></div></div>
                    <div class="team"><div class="nameTeam"><span>Palmeiras SP</span></div></div>
                </div>
                <div class="outcomesMain">
                    <a class="odd">1,85</a>
                    <a class="odd">3,40</a>
                    <a class="odd">4,20</a>
                </div>
                <a class="totalOutcomes-button" href="./Apostas.aspx?idesporte=102&amp;idpartida=123">+30</a>
            </article>
        </div>
        <div class="pais">
            <div class="eventlist-country">
                <span class="flag flag_small flag-BRA"></span>
                <span class="name">Brasil - ⚽CHUTES AO GOL⚽</span>
            </div>
            <article class="cardItem">
                <span class="date">26/ago</span>
                <span class="hour">21:30</span>
                <div class="teams">
                    <div class="team"><div class="nameTeam"><span>Bernardo Silva (REAL MADRID)</span></div></div>
                    <div class="team"><div class="nameTeam"><span>+0,5 / +1,5 / +2,0</span></div></div>
                </div>
                <div class="outcomesMain">
                    <a class="odd">1,25</a>
                    <a class="odd">2,55</a>
                    <a class="odd">4,50</a>
                </div>
            </article>
        </div>
        <div class="pais">
            <div class="eventlist-country">
                <span class="flag flag_small flag-BRA"></span>
                <span class="name">Brasil - +3,5 DEFESAS DE GOLEIRO</span>
            </div>
            <article class="cardItem">
                <span class="date">26/ago</span>
                <span class="hour">21:30</span>
                <div class="teams">
                    <div class="team"><div class="nameTeam"><span>Dituro (ELCHE)</span></div></div>
                    <div class="team"><div class="nameTeam"><span>Courtois (REAL MADRID)</span></div></div>
                </div>
                <div class="outcomesMain">
                    <a class="odd">1,10</a>
                    <a class="odd">2,00</a>
                    <a class="odd">3,00</a>
                </div>
            </article>
        </div>
        <article class="cardItem">
            <div class="teams">
                <div class="team"><div class="nameTeam"><span>Ignored Team</span></div></div>
                <div class="team"><div class="nameTeam"><span>Other Team</span></div></div>
            </div>
            <div class="outcomesMain">
                <a class="odd">1,50</a>
                <a class="odd">3,20</a>
            </div>
        </article>
    </body>
</html>
HTML;
    }

    private function marketDetailsHtml(): string
    {
        return <<<'HTML'
<div class="eventdetail-market">
    <div class="eventdetail-market-header"><span class="name">Vencedor do Encontro</span></div>
    <div class="eventdetail-market-body">
        <div class="eventdetail-optionItem"><span class="name">Casa</span><a class="odd">1,85</a></div>
        <div class="eventdetail-optionItem"><span class="name">Empate</span><a class="odd">3,40</a></div>
        <div class="eventdetail-optionItem"><span class="name">Fora</span><a class="odd">4,20</a></div>
    </div>
</div>
<div class="eventdetail-market">
    <div class="eventdetail-market-header"><span class="name">Total de Gols no Jogo</span></div>
    <div class="eventdetail-market-body">
        <div class="eventdetail-optionItem"><span class="name">Mais de 1,5</span><a class="odd">1,70</a></div>
        <div class="eventdetail-optionItem"><span class="name">Menos de 2,5</span><a class="odd">1,90</a></div>
    </div>
</div>
<div class="eventdetail-market">
    <div class="eventdetail-market-header"><span class="name">Handicap 0:1</span></div>
    <div class="eventdetail-market-body">
        <div class="eventdetail-optionItem"><span class="name">Casa</span><a class="odd">2,00</a></div>
    </div>
</div>
HTML;
    }
}
