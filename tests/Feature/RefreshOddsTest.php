<?php

namespace Tests\Feature;

use App\Jobs\CollectOdds;
use App\Models\CollectionRun;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshOddsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_a_refresh_when_another_collection_is_running(): void
    {
        Queue::fake();
        Cache::put(CollectOdds::PENDING_CACHE_KEY, true, now()->addMinutes(20));

        try {
            $this->withoutMiddleware(PreventRequestForgery::class)
                ->actingAs(User::factory()->create())
                ->post(route('odds.refresh'))
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('status', 'Já existe uma atualização de odds em andamento.');
        } finally {
            Cache::forget(CollectOdds::PENDING_CACHE_KEY);
        }

        Queue::assertNothingPushed();
    }

    public function test_starts_a_collection_when_no_refresh_is_running(): void
    {
        Queue::fake();

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs(User::factory()->create())
            ->post(route('odds.refresh'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Atualização iniciada em segundo plano. Você pode continuar usando o painel enquanto as odds são coletadas.');

        Queue::assertPushed(CollectOdds::class, 1);
        $this->assertDatabaseCount('collection_runs', 0);
    }

    public function test_rejects_a_refresh_during_the_source_cooldown(): void
    {
        $this->freezeTime();
        config()->set('oddradar.collection_cooldown_minutes', 30);
        CollectionRun::create([
            'status' => 'partial',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
        ]);
        Queue::fake();

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs(User::factory()->create())
            ->post(route('odds.refresh'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(
                'status',
                'Aguarde pelo menos 30 minutos entre atualizações para respeitar o limite das fontes.',
            );

        $this->assertDatabaseCount('collection_runs', 1);
        Queue::assertNothingPushed();
    }

    public function test_starts_a_collection_after_the_source_cooldown(): void
    {
        $this->freezeTime();
        config()->set('oddradar.collection_cooldown_minutes', 30);
        CollectionRun::create([
            'status' => 'completed',
            'started_at' => now()->subMinutes(31),
            'finished_at' => now()->subMinutes(30),
        ]);
        Queue::fake();

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs(User::factory()->create())
            ->post(route('odds.refresh'))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('collection_runs', 1);
        Queue::assertPushed(CollectOdds::class, 1);
    }
}
