<?php

namespace App\Services\Normalization;

use Illuminate\Support\Str;

final class RegionResolver
{
    private const array AMERICA_CODES = [
        'ARG', 'BOL', 'BRA', 'CAN', 'CHL', 'COL', 'CRI', 'ECU', 'MEX', 'PAN', 'PER', 'PRY', 'URY', 'USA', 'VEN',
    ];

    private const array ASIA_CODES = [
        'ARE', 'ARM', 'AZE', 'CHN', 'CYP', 'GEO', 'IDN', 'IND', 'IRN', 'IRQ', 'ISR', 'JPN', 'KAZ', 'KOR', 'QAT',
        'SAU', 'THA', 'TJK', 'TKM', 'TUR', 'UZB', 'VNM',
    ];

    private const array EUROPE_CODES = [
        'ALB', 'AUT', 'BEL', 'BGR', 'BIH', 'BLR', 'CHE', 'CZE', 'DEU', 'DNK', 'ENG', 'ESP', 'EST', 'FIN', 'FRA',
        'GBR', 'GRC', 'HRV', 'HUN', 'IRL', 'ISL', 'ITA', 'LTU', 'LUX', 'LVA', 'MDA', 'MKD', 'MLT', 'MNE', 'NIR',
        'NLD', 'NOR', 'POL', 'PRT', 'ROU', 'RUS', 'SCO', 'SRB', 'SVK', 'SVN', 'SWE', 'UKR', 'WAL',
    ];

    public function resolve(?string $countryCode, ?string $country, ?string $competition): ?string
    {
        $code = Str::upper($countryCode ?? '');

        if (in_array($code, self::AMERICA_CODES, true)) {
            return 'america';
        }

        if (in_array($code, self::ASIA_CODES, true)) {
            return 'asia';
        }

        if (in_array($code, self::EUROPE_CODES, true)) {
            return 'europe';
        }

        $description = Str::of(($country ?? '').' '.($competition ?? ''))->ascii()->lower();

        return match (true) {
            $description->contains(['libertadores', 'sul-americana', 'sul americana', 'concacaf', 'campeones cup']) => 'america',
            $description->contains(['afc', 'asian', 'asia']) => 'asia',
            $description->contains(['uefa', 'europa']) => 'europe',
            default => null,
        };
    }
}
