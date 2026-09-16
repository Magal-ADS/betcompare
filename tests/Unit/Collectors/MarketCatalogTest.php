<?php

namespace Tests\Unit\Collectors;

use App\Collectors\MarketCatalog;
use Tests\TestCase;

class MarketCatalogTest extends TestCase
{
    public function test_contains_exactly_the_approved_markets(): void
    {
        $marketKeys = array_keys((new MarketCatalog)->names());

        $this->assertSame([
            'match_winner',
            'total_goals',
            'both_teams_score',
            'double_chance',
            'draw_no_bet',
            'first_half_winner',
            'first_half_double_chance',
            'first_half_both_teams_score',
            'second_half_both_teams_score',
            'half_with_most_goals',
            'home_score_both_halves',
            'away_score_both_halves',
            'away_total_goals',
            'home_total_goals',
            'correct_score',
            'team_to_score',
            'total_corners',
            'corners_1x2',
            'first_half_corners_1x2',
            'home_refund_bet',
            'away_refund_bet',
            'total_and_both_teams_score',
            'first_half_correct_score',
            'second_half_total_goals',
            'home_win_both_halves',
            'away_win_both_halves',
            'winner_and_total_goals',
            'both_teams_score_by_half',
            'winner_and_both_teams_score',
            'first_half_first_goal',
            'anytime_scorer',
            'first_half_total_goals',
            'first_scorer',
        ], $marketKeys);
    }

    public function test_accepts_dynamic_correct_score_title_and_rejects_an_unapproved_market(): void
    {
        $catalog = new MarketCatalog;

        $this->assertSame(
            ['key' => 'correct_score', 'name' => 'Resultado exato'],
            $catalog->find('Resultado exato [0:0] (0:0)'),
        );
        $this->assertNull($catalog->find('Handicap 0:1'));
    }
}
