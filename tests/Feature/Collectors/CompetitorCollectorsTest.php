<?php

namespace Tests\Feature\Collectors;

use App\Collectors\A2BetsCollector;
use App\Collectors\Chute13Collector;
use App\Collectors\GBGoldBetCollector;
use App\Collectors\OddsCollector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompetitorCollectorsTest extends TestCase
{
    /**
     * @param  class-string<OddsCollector>  $collectorClass
     */
    #[DataProvider('collectors')]
    public function test_returns_normalized_events_for_each_competitor_collector(
        string $collectorClass,
        string $configKey,
        string $url,
        string $source,
    ): void {
        config()->set($configKey, $url);
        Http::preventStrayRequests();
        Http::fake([
            $url => Http::response($this->competitorHtml()),
        ]);

        $event = app($collectorClass)->collect()->sole();

        $this->assertSame($source, $event->source);
        $this->assertSame('CR Vasco da Gama RJ', $event->homeTeam);
        $this->assertSame('EC Vitória BA', $event->awayTeam);
        $this->assertSame(1.55, $event->markets['match_winner']->selections[0]['odd']);
        $this->assertSame(3.37, $event->markets['match_winner']->selections[1]['odd']);
        $this->assertSame(5.1, $event->markets['match_winner']->selections[2]['odd']);

        Http::assertSent(fn (Request $request): bool => $request->url() === $url && $request->method() === 'GET');
    }

    /**
     * @return array<string, array{class-string<OddsCollector>, string, string, string}>
     */
    public static function collectors(): array
    {
        return [
            'Chute13' => [Chute13Collector::class, 'services.bookmakers.chute13.games_url', 'https://chute13.test/games', 'chute13'],
            'A2Bets' => [A2BetsCollector::class, 'services.bookmakers.a2bets.games_url', 'https://a2bets.test/games', 'a2bets'],
            'GB Gold Bet' => [GBGoldBetCollector::class, 'services.bookmakers.gbgoldbet.games_url', 'https://gbgoldbet.test/games', 'gbgoldbet'],
        ];
    }

    private function competitorHtml(): string
    {
        return <<<'HTML'
<article class="cardItem">
    <span class="date">26/ago</span>
    <span class="hour">21:30</span>
    <div class="teams">
        <div class="nameTeam"><span>CR Vasco da Gama RJ</span></div>
        <div class="nameTeam"><span>EC Vitória BA</span></div>
    </div>
    <div class="outcomesMain">
        <a class="odd">1,55</a>
        <a class="odd">3,37</a>
        <a class="odd">5,10</a>
    </div>
</article>
HTML;
    }
}
