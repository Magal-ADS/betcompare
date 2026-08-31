<?php

namespace Tests\Feature;

use App\Collectors\OddsCollectorRegistry;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RefreshOddsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_a_refresh_when_another_collection_is_running(): void
    {
        $lock = Cache::lock('oddradar:odds-collection', 180);
        $this->assertTrue($lock->get());

        try {
            $this->withoutMiddleware(PreventRequestForgery::class)
                ->actingAs(User::factory()->create())
                ->post(route('odds.refresh'))
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('status', 'Já existe uma atualização de odds em andamento.');
        } finally {
            $lock->release();
        }
    }

    public function test_starts_a_collection_when_no_refresh_is_running(): void
    {
        $this->mock(OddsCollectorRegistry::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([]);
        });

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs(User::factory()->create())
            ->post(route('odds.refresh'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Não foi possível coletar dados das fontes neste momento.');

        $this->assertDatabaseHas('collection_runs', ['status' => 'failed']);
    }
}
