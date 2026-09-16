<?php

namespace Tests\Unit\Services\Normalization;

use App\Services\Normalization\RegionResolver;
use Tests\TestCase;

class RegionResolverTest extends TestCase
{
    public function test_resolves_international_competition_region_from_its_name(): void
    {
        $regionResolver = new RegionResolver;

        $this->assertSame(
            'america',
            $regionResolver->resolve('INT', 'Clubes Internacionais', 'Copa Libertadores'),
        );
    }
}
