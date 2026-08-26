<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollectionHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_renders_the_collection_history_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/historico-coletas')
            ->assertOk()
            ->assertSee('Histórico de coletas');
    }
}
