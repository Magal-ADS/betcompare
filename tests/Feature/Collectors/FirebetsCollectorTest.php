<?php

namespace Tests\Feature\Collectors;

use App\Collectors\FirebetsCollector;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebetsCollectorTest extends TestCase
{
    public function test_returns_normalized_1x2_events_from_public_firebets_html(): void
    {
        $this->freezeTime();
        config()->set('services.bookmakers.firebets.games_url', 'https://firebets.test/games');
        Http::preventStrayRequests();
        Http::fake([
            'https://firebets.test/games' => Http::response($this->firebetsHtml()),
        ]);

        $events = app(FirebetsCollector::class)->collect();

        $this->assertCount(1, $events);

        $event = $events->sole();

        $this->assertSame('firebets', $event->source);
        $this->assertSame('1x2', $event->market);
        $this->assertSame('Flamengo RJ', $event->homeTeam);
        $this->assertSame('Palmeiras SP', $event->awayTeam);
        $this->assertSame('26/ago', $event->eventDate);
        $this->assertSame('21:30', $event->eventTime);
        $this->assertSame(1.85, $event->homeOdd);
        $this->assertSame(3.4, $event->drawOdd);
        $this->assertSame(4.2, $event->awayOdd);
        $this->assertSame(now()->toAtomString(), $event->collectedAt->toAtomString());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://firebets.test/games'
                && $request->method() === 'GET';
        });
    }

    public function test_throws_when_the_firebets_page_cannot_be_collected(): void
    {
        config()->set('services.bookmakers.firebets.games_url', 'https://firebets.test/games');
        Http::preventStrayRequests();
        Http::fake([
            'https://firebets.test/games' => Http::response('', 503),
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

    private function firebetsHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="pt-BR">
    <body>
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
        </article>
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
}
