<?php

namespace Tests\Feature;

use Tests\TestCase;

class CollectionHistoryTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_renders_the_collection_history_page(): void
    {
        $this->get('/historico-coletas')->assertSee('Histórico de coletas');
    }
}
