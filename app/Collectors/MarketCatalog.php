<?php

namespace App\Collectors;

use Illuminate\Support\Str;

final class MarketCatalog
{
    /**
     * Only markets explicitly approved by the client belong in this catalog.
     *
     * @var array<string, array{name: string, source_names: array<int, string>}>
     */
    private const array MARKETS = [
        'match_winner' => ['name' => 'Vencedor do Encontro', 'source_names' => ['Vencedor do Encontro']],
        'total_goals' => ['name' => 'Total de Gols no Jogo', 'source_names' => ['Total de Gols no Jogo']],
        'both_teams_score' => ['name' => 'Ambas as equipes marcam', 'source_names' => ['Ambas as equipes marcam']],
        'double_chance' => ['name' => 'Chance Dupla', 'source_names' => ['Chance Dupla']],
        'draw_no_bet' => ['name' => 'Empate não tem aposta', 'source_names' => ['Empate não tem aposta']],
        'first_half_winner' => ['name' => 'Vencedor do 1º tempo', 'source_names' => ['Vencedor do 1º tempo']],
        'first_half_double_chance' => ['name' => '1º Tempo - chance dupla', 'source_names' => ['1º Tempo - chance dupla']],
        'first_half_both_teams_score' => ['name' => '1º Tempo - ambas marcam', 'source_names' => ['1º Tempo - ambas as equipes marcam']],
        'second_half_both_teams_score' => ['name' => '2º Tempo - ambas marcam', 'source_names' => ['2º Tempo - ambas as equipes marcam']],
        'half_with_most_goals' => ['name' => 'Tempo do Jogo com Mais Gols', 'source_names' => ['Tempo do Jogo com Mais Gols']],
        'home_score_both_halves' => ['name' => 'Casa para marcar em ambos os tempos', 'source_names' => ['Casa para marcar em ambos os tempos']],
        'away_score_both_halves' => ['name' => 'Fora para marcar em ambos os tempos', 'source_names' => ['Fora para marcar em ambos os tempos']],
        'away_total_goals' => ['name' => 'Fora - Total de gols no jogo', 'source_names' => ['Fora - Total de gols no jogo']],
        'home_total_goals' => ['name' => 'Casa - Total de gols no jogo', 'source_names' => ['Casa - Total de gols no jogo']],
        'correct_score' => ['name' => 'Resultado exato', 'source_names' => ['Resultado exato']],
        'team_to_score' => ['name' => 'Qual equipe vai marcar', 'source_names' => ['Qual equipe vai marcar']],
        'total_corners' => ['name' => 'Total de escanteios', 'source_names' => ['Total de escanteios']],
        'corners_1x2' => ['name' => 'Escanteios 1x2', 'source_names' => ['Escanteios 1x2']],
        'first_half_corners_1x2' => ['name' => '1º tempo - escanteios 1x2', 'source_names' => ['1ª tempo - escanteios 1x2', '1º tempo - escanteios 1x2']],
        'home_refund_bet' => ['name' => 'Casa devolve aposta', 'source_names' => ['Casa devolve aposta']],
        'away_refund_bet' => ['name' => 'Fora devolve aposta', 'source_names' => ['Fora devolve aposta']],
        'total_and_both_teams_score' => ['name' => 'Total e ambas as equipes para marcar', 'source_names' => ['Total e ambas as equipes para marcar']],
        'first_half_correct_score' => ['name' => '1º Tempo - resultado exato', 'source_names' => ['1º Tempo - resultado exato']],
        'second_half_total_goals' => ['name' => 'Total de Gols no 2º Tempo', 'source_names' => ['Total de Gols no 2º Tempo']],
        'home_win_both_halves' => ['name' => 'Casa para vencer ambos os tempos', 'source_names' => ['Casa para vencer ambos tempos', 'Casa para vencer ambos os tempos']],
        'away_win_both_halves' => ['name' => 'Fora para vencer ambos os tempos', 'source_names' => ['Fora para vencer ambos os tempos']],
        'winner_and_total_goals' => ['name' => 'Vencedor do Encontro e Total de Gols', 'source_names' => ['Vencedor do Encontro e Total de Gols']],
        'both_teams_score_by_half' => ['name' => 'Ambas marcam no 1º e 2º tempo', 'source_names' => ['Ambas marcam 1º tempo/Ambas marcam 2º tempo']],
        'winner_and_both_teams_score' => ['name' => 'Vencedor do Encontro e Ambas Marcam', 'source_names' => ['Vencedor do Encontro e Ambas Marcam']],
        'first_half_first_goal' => ['name' => '1º Tempo - 1º gol', 'source_names' => ['1º Tempo - 1º gol']],
        'anytime_scorer' => ['name' => 'Marca um Gol em Qualquer Momento', 'source_names' => ['Marca um Gol em Qualquer Momento do Jogo']],
        'first_half_total_goals' => ['name' => 'Total de Gols no 1º Tempo', 'source_names' => ['Total de Gols no 1º Tempo']],
        'first_scorer' => ['name' => 'Jogador que Marca o 1º Gol', 'source_names' => ['Que Jogador Marca o 1º Gol?']],
    ];

    /** @return array{key: string, name: string}|null */
    public function find(string $sourceName): ?array
    {
        $normalizedSourceName = $this->normalize($sourceName);

        foreach (self::MARKETS as $key => $market) {
            foreach ($market['source_names'] as $candidate) {
                $normalizedCandidate = $this->normalize($candidate);

                if ($normalizedSourceName === $normalizedCandidate
                    || ($key === 'correct_score' && Str::startsWith($normalizedSourceName, $normalizedCandidate.' '))) {
                    return ['key' => $key, 'name' => $market['name']];
                }
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function names(): array
    {
        return collect(self::MARKETS)
            ->mapWithKeys(fn (array $market, string $key): array => [$key => $market['name']])
            ->all();
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
