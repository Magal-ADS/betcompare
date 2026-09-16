<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('odds')
            ->join('source_events', 'source_events.id', '=', 'odds.source_event_id')
            ->join('events', 'events.id', '=', 'source_events.event_id')
            ->select([
                'odds.id',
                'odds.source_event_id',
                'odds.collection_run_id',
                'odds.home_odd',
                'odds.draw_odd',
                'odds.away_odd',
                'odds.collected_at',
                'odds.created_at',
                'odds.updated_at',
            ])
            ->orderBy('odds.id')
            ->chunkById(200, function (Collection $legacyOdds): void {
                $rows = $legacyOdds->flatMap(fn (object $legacyOdd): array => [
                    $this->marketOddRow($legacyOdd, 'casa', 'Casa', $legacyOdd->home_odd),
                    $this->marketOddRow($legacyOdd, 'empate', 'Empate', $legacyOdd->draw_odd),
                    $this->marketOddRow($legacyOdd, 'fora', 'Fora', $legacyOdd->away_odd),
                ])->all();

                DB::table('market_odds')->insertOrIgnore($rows);
            }, 'odds.id', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('market_odds')
            ->where('market_key', 'match_winner')
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('odds')
                    ->whereColumn('odds.source_event_id', 'market_odds.source_event_id')
                    ->whereColumn('odds.collection_run_id', 'market_odds.collection_run_id');
            })
            ->delete();
    }

    /** @return array<string, mixed> */
    private function marketOddRow(object $legacyOdd, string $selectionKey, string $selectionName, mixed $odd): array
    {
        return [
            'source_event_id' => $legacyOdd->source_event_id,
            'collection_run_id' => $legacyOdd->collection_run_id,
            'market_key' => 'match_winner',
            'market_name' => 'Vencedor do Encontro',
            'selection_key' => $selectionKey,
            'selection_name' => $selectionName,
            'odd' => $odd,
            'collected_at' => $legacyOdd->collected_at,
            'created_at' => $legacyOdd->created_at,
            'updated_at' => $legacyOdd->updated_at,
        ];
    }
};
